<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\Event;
use App\Models\Sale;
use App\Models\SaleOffer;
use App\Services\Flow\FlowEventTypes;
use App\Services\Flow\FlowRestClient;
use App\Services\Market\DustStorefrontProcessor;
use App\Services\Passkey\TemplateRegistry;
use App\Support\DustMarket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ĐUST marketplace (KB notes/dust-marketplace.md): indexer, flag-off safety, currency filters, API, passkey allowlist.
 * In-memory SQLite with the columns the code reads; the Flow REST client is faked (no network).
 */
class DustMarketTest extends TestCase
{
    const FL = '0x2c6a4c4342aeca55';

    const SF = '0x2d55b98eb200daef';

    const SELLER = '0x27407eda8e2bb47c';

    const BUYER = '0xc327c7079fd1b4a3';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'headless.enabled' => false,
            'services.flow.network' => 'testnet',
            'services.flow.address' => self::FL,
            'services.flow.storefrontaddress' => self::SF,
            'flovatar.blockedAddresses' => [],
            'flovatar.dustMarket.enabled' => false,
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        $nft = function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('series')->nullable();
            $t->longText('svg')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->integer('rare_count')->default(0);
            $t->integer('epic_count')->default(0);
            $t->integer('legendary_count')->default(0);
            $t->timestamps();
        };
        Schema::create('flovatars', $nft);
        Schema::create('flovatar_components', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->integer('flovatar_template_id')->nullable();
            $t->integer('mint')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->boolean('destroyed')->default(false);
            $t->boolean('packed')->default(false);
            $t->timestamps();
        });
        Schema::create('flovatar_templates', function (Blueprint $t) {
            $t->increments('id');
            foreach (['name', 'category', 'color', 'series', 'rarity', 'description', 'flow_address'] as $c) {
                $t->string($c)->nullable();
            }
            $t->longText('svg')->nullable();
            $t->integer('max_mintable')->default(0);
            $t->integer('burned')->default(0);
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('rendered_images', function (Blueprint $t) {
            $t->increments('id');
            $t->string('item_type');
            $t->unsignedBigInteger('flow_id');
            $t->string('hash')->nullable();
            $t->string('path')->nullable();
            $t->string('source')->nullable();
            $t->boolean('animated')->default(false);
            $t->json('variants')->nullable();
            $t->integer('bytes')->nullable();
            $t->integer('render_ms')->nullable();
            $t->json('warnings')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('rendered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('flagged_items', function (Blueprint $t) {
            $t->increments('id');
            $t->string('item_type');
            $t->unsignedBigInteger('flow_id');
        });
        Schema::create('sale_offers', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('item_id');
            $t->string('item_type');
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->string('currency', 16)->nullable();
            $t->boolean('is_completed')->default(false);
            $t->boolean('is_dapper')->default(false);
            $t->string('listing_id')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('item_id');
            $t->string('item_type');
            $t->decimal('price', 12, 2)->default(0);
            $t->string('currency', 16)->nullable();
            $t->string('transaction_id')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('flow_buyer_address')->nullable();
            $t->string('flow_seller_address')->nullable();
            $t->boolean('is_dapper')->default(false);
            $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->increments('id');
            $t->string('type');
            $t->json('flow_metadata');
            $t->string('transaction_id')->nullable();
            $t->unsignedBigInteger('block_id')->nullable();
            $t->unsignedBigInteger('index')->nullable();
            $t->boolean('synced')->default(false);
            $t->timestamp('event_date')->nullable();
            $t->timestamps();
        });
        DB::table('flovatars')->insert(['id' => 1, 'flow_id' => 1, 'flow_address' => self::SELLER, 'name' => 'One', 'flow_metadata' => '{}']);
        DB::table('flovatar_components')->insert(['id' => 1, 'flow_id' => 71, 'flow_address' => self::SELLER, 'flovatar_template_id' => 1, 'flow_metadata' => '{}']);
    }

    private function fakeRest(bool $valid = true): FlowRestClient
    {
        return new class($valid) extends FlowRestClient
        {
            public function __construct(public bool $valid)
            {
                parent::__construct('http://flow.invalid');
            }

            public function script(string $code, array $args = [], ?int $height = null)
            {
                $out = [];
                foreach ($args[1]['value'] as $id) {
                    $out[$id['value']] = $this->valid;
                }

                return $out;
            }

            public function transaction(string $id): array
            {
                return ['authorizers' => ['c327c7079fd1b4a3'], 'payer' => '32097235c152f4f6'];
            }
        };
    }

    private function ev(string $name, array $meta, string $tx = 'aa11'): Event
    {
        return new Event(['type' => 'A.'.substr(self::SF, 2).'.NFTStorefrontV2.'.$name, 'flow_metadata' => $meta, 'transaction_id' => $tx, 'event_date' => '2026-11-25 10:00:00']);
    }

    private function available(string $nft = 'Flovatar', int $id = 1, string $vault = 'FlovatarDustToken', $listing = 5551): Event
    {
        return $this->ev('ListingAvailable', [
            'storefrontAddress' => self::SELLER, 'listingResourceID' => $listing, 'nftType' => ['typeID' => 'A.'.substr(self::FL, 2).'.'.$nft.'.NFT'],
            'nftID' => $id, 'salePaymentVaultType' => ['typeID' => ($vault === 'FlowToken' ? 'A.7e60df042a9c0868' : 'A.'.substr(self::FL, 2)).'.'.$vault.'.Vault'],
            'salePrice' => 200, 'expiry' => 1827108572,
        ]);
    }

    public function test_event_filter_keeps_listing_destroyed_with_string_types(): void
    {
        $t = 'A.'.substr(self::SF, 2).'.NFTStorefrontV2.Listing.ResourceDestroyed';
        $this->assertContains($t, FlowEventTypes::storefrontTypes());
        $this->assertTrue(FlowEventTypes::keep($t, ['nftType' => 'A.'.substr(self::FL, 2).'.Flovatar.NFT', 'listingResourceID' => 1]));
        $this->assertFalse(FlowEventTypes::keep($t, ['nftType' => 'A.0b2a3299cc857e29.TopShot.NFT', 'listingResourceID' => 1]));
        $this->assertSame('DUST', DustMarket::currencyFromVault(['typeID' => 'A.'.substr(self::FL, 2).'.FlovatarDustToken.Vault']));
        $this->assertSame('FLOW', DustMarket::currencyFromVault('A.1111111111111111.FlovatarDustToken.Vault'), 'other contract address is not ĐUST');
        $this->assertSame('DUC', DustMarket::currencyFromVault(['typeID' => 'A.82ec283f88a62e65.FlowUtilityToken.Vault']));
    }

    public function test_flag_off_acknowledges_dust_events_without_writing(): void
    {
        $p = new DustStorefrontProcessor($this->fakeRest());
        $e = $this->available();
        $this->assertTrue($p->handles($e));
        $this->assertTrue($p->process($e));
        $this->assertSame(0, SaleOffer::count());
        // FLOW storefront listings stay with the legacy branches
        $this->assertFalse($p->handles($this->available('Flovatar', 1, 'FlowToken')));
    }

    public function test_listing_purchase_and_cancel_are_indexed_with_currency(): void
    {
        config(['flovatar.dustMarket.enabled' => true]);
        $p = new DustStorefrontProcessor($this->fakeRest());
        $this->assertTrue($p->process($this->available()));
        $offer = SaleOffer::first();
        $this->assertSame('DUST', $offer->currency);
        $this->assertSame('5551', (string) $offer->listing_id);
        $this->assertFalse((bool) $offer->is_dapper);
        $this->assertNotNull($offer->expires_at);

        $done = $this->ev('ListingCompleted', ['listingResourceID' => 5551, 'purchased' => true, 'nftType' => ['typeID' => 'A.'.substr(self::FL, 2).'.Flovatar.NFT'],
            'nftID' => 1, 'salePaymentVaultType' => ['typeID' => 'A.'.substr(self::FL, 2).'.FlovatarDustToken.Vault'], 'salePrice' => 200], 'bb22');
        $this->assertTrue($p->process($done));
        $this->assertSame(0, SaleOffer::count());
        $sale = Sale::first();
        $this->assertSame('DUST', $sale->currency);
        $this->assertSame(self::BUYER, $sale->flow_buyer_address);
        $this->assertSame(self::SELLER, $sale->flow_seller_address);
        $this->assertSame('bb22', $sale->transaction_id);
        $p->process($done); // replay: no duplicate sale
        $this->assertSame(1, Sale::count());

        // cancel (Cadence 1.0: only Listing.ResourceDestroyed, String types), any currency
        $p->process($this->available('FlovatarComponent', 71, 'FlovatarDustToken', 7771));
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\Flovatar::class, 'flow_id' => 1, 'price' => 3, 'currency' => 'FLOW', 'listing_id' => '8881']);
        $this->assertSame(2, SaleOffer::count());
        $gone = fn ($id, $vault) => $this->ev('Listing.ResourceDestroyed', ['listingResourceID' => $id, 'purchased' => false, 'nftType' => 'A.'.substr(self::FL, 2).'.FlovatarComponent.NFT', 'nftID' => 71, 'salePaymentVaultType' => $vault, 'salePrice' => 1]);
        $this->assertTrue($p->process($gone(7771, 'A.'.substr(self::FL, 2).'.FlovatarDustToken.Vault')));
        $this->assertTrue($p->process($gone(8881, 'A.7e60df042a9c0868.FlowToken.Vault')));
        $this->assertSame(0, SaleOffer::count());
    }

    public function test_listing_without_marketplace_fee_or_already_gone_is_not_shown(): void
    {
        config(['flovatar.dustMarket.enabled' => true]);
        $p = new DustStorefrontProcessor($this->fakeRest(false));
        $this->assertTrue($p->process($this->available()));
        $this->assertSame(0, SaleOffer::count());
    }

    public function test_currency_filters_and_flag_off_hide_dust(): void
    {
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\Flovatar::class, 'flow_id' => 1, 'price' => 200, 'currency' => 'DUST', 'listing_id' => '1']);
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\FlovatarComponent::class, 'flow_id' => 71, 'price' => 5, 'currency' => 'FLOW']);
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\FlovatarComponent::class, 'flow_id' => 72, 'price' => 6, 'currency' => null]);
        $this->assertSame(2, SaleOffer::currencyFilter('dust')->count(), 'flag off: DUST never visible');
        $this->assertSame(2, SaleOffer::marketVisible()->count());
        config(['flovatar.dustMarket.enabled' => true]);
        $this->assertSame(3, SaleOffer::currencyFilter('all')->count());
        $this->assertSame(2, SaleOffer::currencyFilter('flow')->count());
        $this->assertSame(1, SaleOffer::currencyFilter('dust')->count());
        $this->assertSame(2, SaleOffer::currencyFilter('dust', true)->count(), 'Dapper tab never shows ĐUST');
        $this->assertSame(2, SaleOffer::notDust()->count());
    }

    public function test_marketplace_api_currency_param(): void
    {
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\Flovatar::class, 'flow_id' => 1, 'price' => 200, 'currency' => 'DUST', 'listing_id' => '1', 'flow_address' => self::SELLER]);
        SaleOffer::create(['item_id' => 1, 'item_type' => \App\Models\FlovatarComponent::class, 'flow_id' => 71, 'price' => 5, 'currency' => 'FLOW', 'flow_address' => self::SELLER]);

        $off = $this->getJson('/api/v2/marketplace?currency=dust&nosvg=1')->assertOk()->json();
        $this->assertSame(1, $off['total']);
        $this->assertArrayNotHasKey('currency', $off['data'][0], 'flag off: JSON unchanged');

        config(['flovatar.dustMarket.enabled' => true]);
        $on = $this->getJson('/api/v2/marketplace?currency=dust&nosvg=1&x=1')->assertOk()->json();
        $this->assertSame(1, $on['total']);
        $this->assertSame('DUST', $on['data'][0]['currency']);
        $all = $this->getJson('/api/v2/marketplace?nosvg=1&x=2')->assertOk()->json();
        $this->assertSame(2, $all['total']);
    }

    public function test_dust_templates_are_allowlisted_for_passkey_users(): void
    {
        $r = new TemplateRegistry();
        foreach (['dust-sale-create', 'dust-sale-buy', 'dust-sale-cancel', 'dust-sale-cleanup'] as $id) {
            $t = $r->find($id);
            $this->assertSame($id, $t['id']);
            $this->assertStringContainsString('NFTStorefrontV2', $t['cadence']);
        }
        $create = $r->find('dust-sale-create')['cadence'];
        $this->assertStringContainsString('Type<@FlovatarDustToken.Vault>()', $create);
        $this->assertStringContainsString('Flovatar.getMarketplaceCut()', $create);
        $this->assertStringNotContainsString('FlowUtilityToken', $create, 'no Dapper/DUC code in the ĐUST templates');
    }

    /**
     * Ghost index fix: NFTStorefrontV2.cleanupGhostListings leaves the destroyed listing's id in getExistingListingIDs, and
     * removeListing panics "missing Listing" on it (seen on testnet: user1, Flobit #72, tx 57a9591f…). The listing tx must
     * only remove listings it can still borrow, and the passkey pin must be the hash of that exact Cadence.
     */
    public function test_dust_sale_create_skips_index_ids_without_a_listing(): void
    {
        $t = (new TemplateRegistry())->find('dust-sale-create');
        $c = $t['cadence'];
        $loop = 'for listingID in self.storefront.getExistingListingIDs(nftType: self.nftType, nftID: saleItemID) {';
        $this->assertStringContainsString($loop, $c);
        $body = substr($c, strpos($c, $loop) + strlen($loop), 200);
        $this->assertMatchesRegularExpression(
            '/^\s*if self\.storefront\.borrowListing\(listingResourceID: listingID\) != nil \{\s*self\.storefront\.removeListing\(listingResourceID: listingID\)\s*\}/',
            $body
        );
        $this->assertSame(1, substr_count($c, 'removeListing('), 'no unguarded removeListing');
        $pin = (require base_path('config/passkey-templates.php'))['dust-sale-create']['sha256'];
        $this->assertSame($pin, hash('sha256', $c));
        $this->assertSame('20c5abf0797c909d7a0f86e17d5314dde8260ce36585081dca6916ceed4fd9c1', $pin);
    }

    public function test_layout_prints_dust_config_only_when_enabled(): void
    {
        $this->assertFalse(DustMarket::enabled());
        $html = view('partials.dust-market', ['dustKind' => 'flovatar', 'dustId' => 1])->render();
        $this->assertSame('', trim($html));
        config(['flovatar.dustMarket.enabled' => true]);
        $html = view('partials.dust-market', ['dustKind' => 'flovatar', 'dustId' => 1])->render();
        $this->assertStringContainsString("dustMarket('flovatar', 1)", $html);
        $this->assertSame(['enabled' => true, 'listingTtl' => 31536000, 'feeAddress' => self::FL], DustMarket::bootstrap());
    }
}
