<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Jobs\RegenerateStaticItem;
use App\Models\Flovatar;
use App\Models\SaleOffer;
use App\Support\Headless;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Headless frontend contract: API v2 (public cache headers, CORS, JSON shapes), auth endpoints (private),
 * static build (per-item page with snapshot + canonical + og) and debounced regeneration.
 * Runs on an in-memory SQLite schema with the columns the code reads (no MySQL needed).
 */
class HeadlessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'headless.frontend_url' => 'https://next-testnet.flovatar.com',
            'headless.enabled' => false,
            'render.legacy_fallback' => false,
            'flovatar.blockedAddresses' => ['0x0c7deb7a0c9cb424'],
            'services.flow.network' => 'testnet',
        ]);
        DB::purge();
        $this->withoutMiddleware(ThrottleRequestsPerRoute::class);
        $this->schema();
        $this->seedRows();
    }

    private function schema(): void
    {
        $nft = function (Blueprint $t, array $extra = []) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->string('series')->nullable();
            $t->longText('svg')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->timestamps();
        };
        Schema::create('flovatars', function (Blueprint $t) use ($nft) {
            $nft($t);
            $t->integer('rare_count')->default(0);
            $t->integer('epic_count')->default(0);
            $t->integer('legendary_count')->default(0);
        });
        Schema::create('flobots', function (Blueprint $t) use ($nft) {
            $nft($t);
            $t->string('rarity')->nullable();
        });
        Schema::create('dust_collectibles', function (Blueprint $t) use ($nft) {
            $nft($t);
            $t->integer('mint')->default(0);
            $t->integer('dust_series_id')->default(1);
            $t->integer('rare_count')->default(0);
            $t->integer('epic_count')->default(0);
            $t->integer('legendary_count')->default(0);
            $t->float('rarity_score')->nullable();
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
        Schema::create('flovatar_components', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('flovatar_template_id')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->integer('mint')->default(0);
            $t->boolean('destroyed')->default(false);
            $t->boolean('packed')->default(false);
            $t->timestamps();
        });
        Schema::create('sale_offers', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('item_id');
            $t->string('item_type');
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->boolean('is_completed')->default(false);
            $t->boolean('is_dapper')->default(false);
            $t->string('listing_id')->nullable();
            $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('item_id');
            $t->string('item_type');
            $t->decimal('price', 12, 2)->default(0);
            $t->string('transaction_id')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('flow_buyer_address')->nullable();
            $t->string('flow_seller_address')->nullable();
            $t->boolean('is_dapper')->default(false);
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
            $t->string('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('packs', function (Blueprint $t) {
            $t->increments('id');
            $t->string('secret')->nullable();
            $t->string('signature')->nullable();
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->integer('series')->nullable();
            $t->string('name')->nullable();
            $t->string('flow_address')->nullable();
            $t->json('flow_metadata')->nullable();
            $t->boolean('opened')->default(false);
            $t->timestamps();
        });
        Schema::create('dust_templates', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->integer('dust_series_id')->default(1);
            $t->unsignedBigInteger('flow_id')->nullable();
            $t->string('rarity')->nullable();
            $t->longText('svg')->nullable();
            $t->timestamps();
        });
    }

    private function seedRows(): void
    {
        $now = now();
        DB::table('flovatars')->insert([
            'name' => 'luca', 'series' => '1', 'svg' => '<svg>BIG</svg>', 'flow_id' => 3, 'flow_address' => '0x2538515561c0c431',
            'flow_metadata' => json_encode(['id' => '3', 'hatId' => '581', 'backgroundId' => null, 'metadata' => ['svg' => '<svg>BIG</svg>', 'creatorAddress' => '0x67b5ddcc022c6dc5', 'components' => ['body' => '50']]]),
            'rare_count' => 0, 'epic_count' => 1, 'legendary_count' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('sale_offers')->insert([
            'item_id' => 1, 'item_type' => Flovatar::class, 'flow_id' => 3, 'flow_address' => '0x2538515561c0c431',
            'price' => 12.5, 'is_completed' => false, 'is_dapper' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('packs')->insert(['secret' => 'NEVER-SHOWN', 'flow_id' => 1, 'series' => 1, 'name' => 'Genesis Standard Pack', 'opened' => false, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function assertPublic($r): void
    {
        $r->assertOk();
        $cc = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('s-maxage=60', $cc);
        $this->assertStringContainsString('stale-while-revalidate=300', $cc);
        $this->assertSame('*', $r->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame([], $r->headers->getCookies());
    }

    public function test_item_snapshot_is_public_lean_and_canonical(): void
    {
        $r = $this->get('/api/v2/flovatars/3');
        $this->assertPublic($r);
        $r->assertJsonPath('id', 3)
            ->assertJsonPath('owner', '0x2538515561c0c431')
            ->assertJsonPath('url', 'https://next-testnet.flovatar.com/flovatars/3')
            ->assertJsonPath('listing.price', 12.5)
            ->assertJsonPath('listing.currency', 'FLOW')
            ->assertJsonPath('rarity.epic', 1)
            ->assertJsonPath('flobits.hat', '581');
        $this->assertStringNotContainsString('<svg', $r->getContent());
    }

    public function test_unknown_item_is_json_404(): void
    {
        $r = $this->get('/api/v2/flovatars/999');
        $r->assertNotFound();
        $this->assertStringContainsString('application/json', (string) $r->headers->get('Content-Type'));
    }

    public function test_lists_keep_legacy_paginator_shape(): void
    {
        $r = $this->get('/api/v2/showroom/flovatars?sortby=datenew');
        $this->assertPublic($r);
        $r->assertJsonStructure(['current_page', 'data' => [['flow_id', 'flow_address', 'thumb']], 'last_page', 'total']);
        $this->assertStringNotContainsString('BIG', $r->getContent());

        $m = $this->get('/api/v2/marketplace?dapper=false');
        $this->assertPublic($m);
        $m->assertJsonPath('total', 1)->assertJsonPath('data.0.flow_id', 3);
    }

    public function test_packs_never_expose_secrets(): void
    {
        $r = $this->get('/api/v2/packs');
        $this->assertPublic($r);
        $r->assertJsonPath('0.unopened', 1);
        $this->assertStringNotContainsString('NEVER-SHOWN', $r->getContent());
    }

    public function test_auth_endpoints_are_private(): void
    {
        $me = $this->getJson('/api/v2/auth/me');
        $me->assertOk()->assertJson(['authenticated' => false, 'address' => null]);
        $this->assertStringContainsString('no-store', (string) $me->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $me->headers->get('Cache-Control'));

        $nonce = $this->getJson('/api/v2/auth/nonce');
        $nonce->assertOk()->assertJsonStructure(['nonce', 'message', 'authenticated']);
        $this->assertStringContainsString('no-store', (string) $nonce->headers->get('Cache-Control'));

        // an unverifiable proof never logs in (CSRF is enforced outside tests like POST /login)
        $login = $this->postJson('/api/v2/auth/login', ['address' => '0x2538515561c0c431', 'proofType' => 'account-proof', 'nonce' => str_repeat('0', 64), 'signatures' => []]);
        $login->assertOk()->assertJson(['result' => false, 'address' => null]);
        $this->assertStringContainsString('no-store', (string) $login->headers->get('Cache-Control'));
    }

    public function test_static_build_writes_item_page_with_snapshot_canonical_and_og(): void
    {
        $out = sys_get_temp_dir().'/headless-test-'.uniqid();
        $this->artisan('headless:build', ['--items' => true, '--type' => 'flovatar', '--out' => $out, '--quiet-puts' => true])->assertExitCode(0);
        $file = $out.'/'.Headless::prefix().'flovatars/3/index.html';
        $this->assertFileExists($file);
        $this->assertStringStartsWith('testnet/', Headless::prefix());
        $html = file_get_contents($file);
        $this->assertStringContainsString('<link rel="canonical" href="https://next-testnet.flovatar.com/flovatars/3" />', $html);
        $this->assertStringContainsString('og:url" content="https://next-testnet.flovatar.com/flovatars/3"', $html);
        $this->assertStringContainsString('window.HEADLESS = ', $html);
        $this->assertStringContainsString('"owner":"0x2538515561c0c431"', $html);
        $this->assertStringContainsString("flovatarData('0x2538515561c0c431', 3", $html);
        // second run: unchanged, nothing rewritten
        $this->artisan('headless:build', ['--items' => true, '--type' => 'flovatar', '--out' => $out])->expectsOutputToContain('0 uploaded')->assertExitCode(0);
    }

    public function test_listing_change_schedules_one_debounced_regeneration(): void
    {
        config(['headless.enabled' => true, 'headless.debounce' => 30]);
        Queue::fake();
        $offer = SaleOffer::first();
        $offer->price = 20;
        $offer->save();
        $offer->price = 21;
        $offer->save();
        Flovatar::where('flow_id', 3)->first()->update(['flow_address' => '0x27407eda8e2bb47c']);
        Queue::assertPushed(RegenerateStaticItem::class, 1);
        Queue::assertPushed(RegenerateStaticItem::class, fn ($job) => $job->type === 'flovatar' && $job->flowId === 3);
    }

    public function test_regeneration_is_off_when_disabled(): void
    {
        Queue::fake();
        Flovatar::where('flow_id', 3)->first()->update(['flow_address' => '0x27407eda8e2bb47c']);
        Queue::assertNothingPushed();
    }

    public function test_indexer_events_map_to_item_pages(): void
    {
        config(['headless.enabled' => true]);
        Queue::fake();
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarMarketplace.FlovatarSaleWithdrawn', ['address' => '0x27407eda8e2bb47c', 'tokenId' => 7]);
        \App\Support\HeadlessRegen::fromEvent('A.2d55b98eb200daef.NFTStorefrontV2.ListingAvailable', ['nftType' => ['typeID' => 'A.2c6a4c4342aeca55.Flobot.NFT'], 'nftID' => 9]);
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarDustCollectible.Deposit', ['id' => 11, 'to' => '0x1']);
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarMarketplace.FlovatarComponentForSale', ['id' => 5]);
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarComponent.Deposit', ['id' => 5]);
        Queue::assertPushed(RegenerateStaticItem::class, 3);
        Queue::assertPushed(RegenerateStaticItem::class, fn ($j) => $j->type === 'flovatar' && $j->flowId === 7);
        Queue::assertPushed(RegenerateStaticItem::class, fn ($j) => $j->type === 'flobot' && $j->flowId === 9);
        Queue::assertPushed(RegenerateStaticItem::class, fn ($j) => $j->type === 'collectible' && $j->flowId === 11);
    }
}
