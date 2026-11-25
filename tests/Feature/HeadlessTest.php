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
            $t->string('currency', 16)->nullable();
            $t->timestamp('expires_at')->nullable();
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
            $t->string('currency', 16)->nullable();
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

    public function test_list_thumb_only_where_the_jpeg_looks_like_the_svg(): void
    {
        $now = now();
        // #3 has no background (transparent) -> SVG; #4 has one -> R2 thumb; #5 has one but is animated -> SVG
        foreach ([4 => '570', 5 => '571'] as $id => $bg) {
            DB::table('flovatars')->insert(['name' => 'f'.$id, 'flow_id' => $id, 'flow_address' => '0x2538515561c0c431',
                'flow_metadata' => json_encode(['id' => (string) $id, 'backgroundId' => $bg]), 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([3 => false, 4 => false, 5 => true] as $id => $animated) {
            DB::table('rendered_images')->insert(['item_type' => 'flovatar', 'flow_id' => $id, 'hash' => 'h'.$id, 'path' => "flovatar/r/$id/h$id",
                'animated' => $animated, 'variants' => json_encode(['full.png', 'thumb.jpg']), 'rendered_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }
        $rows = collect($this->get('/api/v2/showroom/flovatars')->assertOk()->json('data'))->keyBy('flow_id');
        $this->assertStringEndsWith('flovatar/r/3/h3/thumb.jpg', $rows[3]['thumb']);
        $this->assertNull($rows[3]['list_thumb']);
        $this->assertStringEndsWith('flovatar/r/4/h4/thumb.jpg', $rows[4]['list_thumb']);
        $this->assertNotNull($rows[5]['thumb']);
        $this->assertNull($rows[5]['list_thumb']);
    }

    public function test_v2_lists_are_trimmed_to_the_fields_the_cards_read(): void
    {
        $row = $this->get('/api/v2/marketplace')->assertOk()->json('data.0');
        $this->assertSame(['created_at', 'flow_address', 'flow_id', 'id', 'is_dapper', 'item', 'item_type', 'list_thumb', 'listing_id', 'price', 'thumb'],
            collect(array_keys($row))->sort()->values()->all());
        $this->assertArrayNotHasKey('svg', $row['item']);
        $this->assertSame(['metadata' => []], $row['item']['flow_metadata'] ?? ['metadata' => []]);

        $f = $this->get('/api/v2/showroom/flovatars')->assertOk()->json('data.0');
        $this->assertSame([['price' => 12.5, 'is_dapper' => false]], $f['sale_offers']);
        $this->assertStringNotContainsString('<svg', json_encode($f));
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

    private function seedFlobitType(): void
    {
        $now = now();
        DB::table('flovatar_templates')->insert(['id' => 41, 'name' => 'Aviator Silver', 'category' => 'eyeglasses', 'series' => '1', 'rarity' => 'rare',
            'max_mintable' => 250, 'burned' => 2, 'flow_id' => 214, 'svg' => '<svg>TPL</svg>', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('flovatar_components')->insert([
            ['flovatar_template_id' => 41, 'flow_id' => 501, 'flow_address' => '0x2538515561c0c431', 'mint' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['flovatar_template_id' => 41, 'flow_id' => 502, 'flow_address' => '0x27407eda8e2bb47c', 'mint' => 8, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('sale_offers')->insert(['item_id' => 1, 'item_type' => \App\Models\FlovatarComponent::class, 'flow_id' => 501, 'flow_address' => '0x2538515561c0c431',
            'price' => 3.5, 'is_completed' => false, 'is_dapper' => false, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function test_flobit_type_page_api_and_static_build(): void
    {
        $this->seedFlobitType();
        $api = $this->get('/api/v2/templates/214');
        $this->assertPublic($api);
        $api->assertJsonPath('minted', 2)->assertJsonPath('listings.0.floor', 3.5)->assertJsonPath('listed.0.id', 501)
            ->assertJsonPath('listed.0.owner', '0x2538515561c0c431')->assertJsonPath('url', 'https://next-testnet.flovatar.com/flobits/214');
        $this->assertStringNotContainsString('<svg', $api->getContent());

        $out = sys_get_temp_dir().'/headless-test-'.uniqid();
        $this->artisan('headless:build', ['--items' => true, '--type' => 'flobit-type', '--out' => $out, '--quiet-puts' => true])->assertExitCode(0);
        $html = file_get_contents($out.'/'.Headless::prefix().'flobits/214/index.html');
        $this->assertStringContainsString('<link rel="canonical" href="https://next-testnet.flovatar.com/flobits/214" />', $html);
        $this->assertStringContainsString('Aviator Silver', $html);
        $this->assertStringContainsString('flobitTypeData(window.flobitType)', $html);
        $this->assertStringContainsString('/components/', $html);
        $this->artisan('headless:build', ['--sitemap' => true, '--out' => $out, '--quiet-puts' => true])->assertExitCode(0);
        $this->assertStringContainsString('/flobits/214</loc>', file_get_contents($out.'/'.Headless::prefix().'sitemap.xml'));
        $this->get('/flobits/999')->assertNotFound();
        // classic site: the type page is edge-cacheable like the other public pages (no session)
        $page = $this->get('/flobits/214');
        $page->assertOk();
        $this->assertStringContainsString('s-maxage=60', (string) $page->headers->get('Cache-Control'));
        $this->assertSame([], $page->headers->getCookies());
    }

    public function test_flobit_events_regenerate_the_type_page(): void
    {
        $this->seedFlobitType();
        config(['headless.enabled' => true]);
        Queue::fake();
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarComponent.Deposit', ['id' => 501, 'to' => '0x1']); // transfer: no
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarComponent.Created', ['id' => 503, 'templateId' => 214, 'mint' => 9]);
        \App\Support\HeadlessRegen::fromEvent('A.2c6a4c4342aeca55.FlovatarMarketplace.FlovatarComponentForSale', ['id' => 502, 'price' => 4]);
        Queue::assertPushed(RegenerateStaticItem::class, 1); // debounced per template
        Queue::assertPushed(RegenerateStaticItem::class, fn ($j) => $j->type === 'flobit-type' && $j->flowId === 214);
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

    public function test_worker_passes_the_visitor_ip_only_from_the_workers_range(): void
    {
        \Illuminate\Support\Facades\Route::get('/__ip_probe', fn (\Illuminate\Http\Request $r) => $r->ip());
        $worker = ['REMOTE_ADDR' => '2a06:98c0:3600::103'];
        $via = ['X-Headless-Proxy' => '1', 'X-Headless-Client-IP' => '203.0.113.7'];
        // Worker subrequest -> visitor IP (per-IP throttles stay per visitor)
        $this->withServerVariables($worker)->withHeaders($via)->get('/__ip_probe')->assertSeeText('203.0.113.7');
        // header from anyone else is ignored
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->withHeaders($via)->get('/__ip_probe')->assertSeeText('198.51.100.9');
        // Workers range without the proxy marker / with garbage: unchanged
        $this->withServerVariables($worker)->withHeaders(['X-Headless-Proxy' => '0', 'X-Headless-Client-IP' => '203.0.113.7'])->get('/__ip_probe')->assertSeeText('2a06:98c0:3600::103');
        $this->withServerVariables($worker)->withHeaders(['X-Headless-Proxy' => '1', 'X-Headless-Client-IP' => 'evil'])->get('/__ip_probe')->assertSeeText('2a06:98c0:3600::103');
    }

    public function test_origin_host_is_noindex_except_through_the_worker(): void
    {
        config(['headless.origin_hosts' => ['api.flovatar.com']]);
        $direct = $this->get('http://api.flovatar.com/api/v2/packs');
        $direct->assertOk();
        $this->assertSame('noindex, nofollow', $direct->headers->get('X-Robots-Tag'));
        $proxied = $this->withServerVariables(['REMOTE_ADDR' => '2a06:98c0:3600::103'])
            ->withHeaders(['X-Headless-Proxy' => '1', 'X-Headless-Client-IP' => '203.0.113.7'])->get('http://api.flovatar.com/api/v2/packs');
        $proxied->assertOk();
        $this->assertNull($proxied->headers->get('X-Robots-Tag'));
        // the classic host is untouched
        $this->assertNull($this->get('http://flovatar.com/api/v2/packs')->headers->get('X-Robots-Tag'));
    }
}
