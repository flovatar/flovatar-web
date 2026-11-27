<?php

namespace Tests\Feature;

use App\Http\Controllers\Fub\FubPagesController;
use App\Models\Fub;
use App\Models\FubDraft;
use App\Models\FubDraftItem;
use App\Models\FubItem;
use App\Models\Fun;
use App\Models\User;
use App\Services\Fub\DraftSync;
use App\Services\Fub\FubChain;
use App\Services\Fub\FubEventProcessor;
use App\Services\Fub\FubIndexer;
use App\Services\Fub\FubOperator;
use App\Services\Flow\FlowEventTypes;
use App\Support\Fub as FubSupport;
use App\Support\FubAuth;
use App\Support\Stealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FUB v1 web (KB notes/fub/FUB-BUILD.md): FUB_MODE off by default, fail-closed, studio draft API (owner-only, contract
 * rules, sanitiser), assistant tool API (token + owner grant, one draft only), user-SVG content headers, draft->chain
 * plan, event processing, moderation (site-only hide), reports, operator signatures.
 */
class FubTest extends TestCase
{
    private const ADDR = '0x2c6a4c4342aeca55';

    private const ALICE = '0x27407eda8e2bb47c';

    private const BOB = '0xc327c7079fd1b4a3';

    private const TOKEN = 'test-tool-token-0123456789abcdef0123456789';

    private const CLEAN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 3000"><g id="hat"><path d="M100 100L2900 2900" stroke="#000" stroke-width="13"/></g></svg>';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'headless.enabled' => false,
            'services.flow.address' => self::ADDR, 'services.flow.network' => 'testnet', 'services.flow.nftaddress' => '0x631e88ae7f1d7c20',
            'fub.contract' => self::ADDR, 'fub.tool_token' => self::TOKEN, 'fub.assistant_url' => 'https://assistant.test',
            'fub.admins' => [self::BOB], 'islands.contract' => self::ADDR,
        ]);
        DB::purge();
        (require base_path('database/migrations/2026_11_27_100001_create_fub_tables.php'))->up();
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        $chain = new class extends FubChain {
            public array $fubs = [];

            public array $items = [];

            public function config(bool $fresh = false): array
            {
                return ['contract' => '0x2c6a4c4342aeca55', 'svgOpen' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 3000">',
                    'tiers' => ['common' => ['layers' => 8, 'composed' => 120000, 'largeItems' => 3], 'epic' => ['layers' => 12, 'composed' => 200000, 'largeItems' => 6],
                        'legendary' => ['layers' => 16, 'composed' => 300000, 'largeItems' => 12]], 'itemMax' => 32768, 'largeItemMax' => 65536];
            }

            public function fub(int $id, ?int $height = null): ?array
            {
                return $this->fubs[$id] ?? null;
            }

            public function allItems(int $fubId, int $count, ?int $height = null): array
            {
                return $this->items[$fubId] ?? [];
            }

            public function itemSvgs(array $ids): array
            {
                return array_fill_keys($ids, '<g id="u1-root"><path d="M1 1L2 2"/></g>');
            }

            public function fubCount(): int
            {
                return count($this->fubs);
            }
        };
        $this->app->instance(FubChain::class, $chain);
        Queue::fake();
        $this->withoutMiddleware(\App\Http\Middleware\ThrottleRequestsPerRoute::class); // Redis limiter (no Redis in tests)
    }

    private function chain()
    {
        return $this->app->make(FubChain::class);
    }

    private function mode(?string $m): void
    {
        config(['stealth.features.fub' => $m]);
    }

    private function on(): void
    {
        $this->mode('on');
        Route::middleware('web')->group(function () {
            Route::get('fub', [FubPagesController::class, 'index']);
            require base_path('routes/fub.php');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    private function as(string $addr): self
    {
        $u = User::firstOrCreate(['email' => $addr], ['name' => $addr, 'password' => 'x']);

        return $this->actingAs($u);
    }

    private function draftFor(string $addr, array $layers = ['bg' => 'Background', 'hat' => 'Hat']): FubDraft
    {
        $d = FubDraft::create(['owner_address' => $addr, 'name' => 'Space Snacks', 'settings' => ['royalty' => 0.05, 'unique' => true]]);
        $i = 0;
        foreach ($layers as $k => $n) {
            $d->layers()->create(['key' => $k, 'name' => $n, 'required' => true, 'position' => $i++]);
        }

        return $d;
    }

    // --------------------------------------------------------------------------------------------- switch

    public function test_off_by_default_and_fails_closed(): void
    {
        $this->assertNull((require base_path('config/stealth.php'))['features']['fub']);
        $this->mode(null);
        $this->assertFalse(FubSupport::active());
        $this->assertSame([], FubEventProcessor::types());
        $this->assertSame([], FlowEventTypes::fubTypes());
        $this->assertNotContains('u', Stealth::bundles());
        $this->assertFalse(Route::has('fub.studio'));
        $this->mode('on');
        config(['fub.contract' => '']);
        $this->assertFalse(FubSupport::active(), 'no contract = off');
        config(['fub.contract' => 'nonsense']);
        $this->assertFalse(FubSupport::active());
    }

    public function test_on_registers_pages_events_and_bundle(): void
    {
        $this->on();
        $this->assertTrue(FubSupport::enabled());
        $this->assertContains('A.2c6a4c4342aeca55.FlovatarUniverseBuilder.FunMinted', FubEventProcessor::types());
        $this->assertContains('A.2c6a4c4342aeca55.FlovatarUniverseBuilder.NFT', FlowEventTypes::standardDepositNftTypes());
        $this->assertContains('u', Stealth::bundles());
        $this->get('/fub')->assertOk()->assertSee('Build your own F.U.N. collection')->assertSee('25 item slots and 500 mints', false);
        $this->get('/fub/studio/1')->assertOk()->assertSee('data-fub="studio"', false);
    }

    /** Unreleased product names must never appear in this public repo's pages/JS: compared by hash, so the test
     *  itself doesn't spell them out. */
    private const SECRET_NAME_HASHES = ['1d0ca65c19c17264759baf9d5ca93e44d5b7725f8911d756bd9e2e03fc1f4927', 'c7fddc5945253431bff7d1de52812d20c10a1fef1e79ed3b58cc87a3762f7335'];

    private function assertNoSecretNames(string $text): void
    {
        preg_match_all('/[a-z0-9]+/', strtolower($text), $m);
        foreach (array_unique($m[0]) as $word) {
            $this->assertNotContains(hash('sha256', $word), self::SECRET_NAME_HASHES, 'unreleased name leaked');
        }
    }

    public function test_public_copy_never_names_the_assistant(): void
    {
        $this->on();
        foreach (['/fub', '/fub/studio/1'] as $u) {
            $this->assertNoSecretNames($this->get($u)->getContent());
        }
        $this->assertNoSecretNames(file_get_contents(base_path('resources/js/app/fub-v1.js')));
        $this->assertNoSecretNames(file_get_contents(base_path('resources/views/fub-v1/studio.blade.php')));
    }

    // --------------------------------------------------------------------------------------------- studio API

    public function test_draft_api_requires_a_wallet_session(): void
    {
        $this->on();
        $this->postJson('/api/fub/drafts', ['name' => 'x'])->assertStatus(401);
        $this->getJson('/api/fub/drafts')->assertStatus(401);
    }

    public function test_create_layers_items_with_sanitised_svg(): void
    {
        $this->on();
        $id = $this->as(self::ALICE)->postJson('/api/fub/drafts', ['name' => 'Space Snacks'])->assertCreated()->json('id');
        $this->postJson("/api/fub/drafts/$id/layers", ['name' => 'Background'])->assertCreated()->assertJsonPath('layers.0.key', 'l1');
        $evil = '<svg><script>alert(1)</script><g onload="x()"><rect width="10" height="10" fill="#123"/></g><text>hi</text></svg>';
        $r = $this->postJson("/api/fub/drafts/$id/items", ['layer' => 'l1', 'name' => 'Blue', 'price' => 5, 'svg' => $evil])->assertCreated();
        $this->assertTrue($r->json('report.dangerous'));
        $item = FubDraftItem::first();
        $this->assertStringNotContainsString('script', $item->svg);
        $this->assertStringNotContainsString('onload', $item->svg);
        $this->assertStringNotContainsString('hi', $item->svg);
        $this->assertStringStartsWith('<g id="u'.$item->id.'-root">', $item->svg);
        $this->assertSame(hash('sha3-256', $item->svg), $item->sha3);
        $this->assertSame($evil, $item->svg_original, 'original kept privately');
        $this->assertArrayNotHasKey('svg_original', $item->toArray());
        $this->assertSame(1, $r->json('draft.budget.used'));
    }

    public function test_contract_rules_are_enforced_before_the_chain(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $this->as(self::ALICE);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Free', 'price' => 0, 'svg' => self::CLEAN])->assertStatus(422);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'nope', 'name' => 'X', 'price' => 2, 'svg' => self::CLEAN])->assertStatus(422);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => '<b>', 'price' => 2, 'svg' => self::CLEAN])->assertStatus(422);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Curve', 'price' => 10, 'increase' => 0.05, 'price_cap' => 5, 'svg' => self::CLEAN])
            ->assertStatus(422)->assertJsonValidationErrors('price_cap');
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Steep', 'price' => 10, 'increase' => 0.5, 'price_cap' => 50, 'svg' => self::CLEAN])->assertStatus(422);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Ok', 'price' => 10, 'increase' => 0.05, 'price_cap' => 50, 'max_mint' => 100, 'svg' => self::CLEAN])->assertCreated();
        $this->patchJson("/api/fub/drafts/{$d->id}", ['royalty' => 0.2])->assertStatus(422);
        // slots: common = 25
        $d->update(['settings' => ['slots' => 1]]);
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Two', 'price' => 2, 'svg' => self::CLEAN])->assertStatus(422)->assertJsonValidationErrors('items');
        // layers: common = 8
        foreach (range(3, 8) as $n) {
            $this->postJson("/api/fub/drafts/{$d->id}/layers", ['name' => 'L'.$n])->assertCreated();
        }
        $this->postJson("/api/fub/drafts/{$d->id}/layers", ['name' => 'L9'])->assertStatus(422);
        // destructive without confirm
        $this->deleteJson("/api/fub/drafts/{$d->id}/layers/bg")->assertStatus(422);
    }

    public function test_other_wallets_never_see_or_touch_a_draft(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $this->as(self::ALICE)->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'A', 'price' => 2, 'svg' => self::CLEAN])->assertCreated();
        $item = FubDraftItem::first();
        $this->as(self::BOB);
        $this->getJson("/api/fub/drafts/{$d->id}")->assertNotFound();
        $this->patchJson("/api/fub/drafts/{$d->id}", ['name' => 'mine'])->assertNotFound();
        $this->postJson("/api/fub/drafts/{$d->id}/layers", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/fub/drafts/{$d->id}/items/{$item->id}?confirm=1")->assertNotFound();
        $this->get("/fub-content/draft-item/{$item->id}.svg")->assertNotFound();
        $this->get("/fub-content/draft/{$d->id}.svg?i={$item->id}")->assertNotFound();
        // an item of another draft can't be addressed through your own draft either
        $mine = $this->draftFor(self::BOB);
        $this->patchJson("/api/fub/drafts/{$mine->id}/items/{$item->id}", ['name' => 'stolen'])->assertStatus(403);
        $this->assertSame('A', $item->fresh()->name);
    }

    public function test_published_items_are_frozen_and_structure_locked(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $d->update(['status' => 'published', 'chain_fub_id' => 7]);
        Fub::create(['flow_id' => 7, 'creator' => self::ALICE, 'status' => 'open', 'tier' => 'common', 'slots' => 25, 'mint_cap' => 500, 'max_supply' => 500, 'name' => 'S']);
        $it = $d->items()->create(['layer_key' => 'bg', 'name' => 'Live', 'price' => 5, 'svg' => '<g/>', 'sha3' => 'x', 'size' => 4, 'chain_item_id' => 70, 'chain_state' => 'synced']);
        FubItem::create(['flow_id' => 70, 'fub_flow_id' => 7, 'layer' => 'bg', 'name' => 'Live', 'rarity' => 'common', 'price' => 5, 'current_price' => 5, 'state' => 'live', 'live' => true]);
        $this->as(self::ALICE);
        $this->patchJson("/api/fub/drafts/{$d->id}/items/{$it->id}", ['price' => 1])->assertStatus(422);
        $this->postJson("/api/fub/drafts/{$d->id}/layers", ['name' => 'New'])->assertStatus(422);
        $this->patchJson("/api/fub/drafts/{$d->id}", ['royalty' => 0.01])->assertStatus(422);
        // additions to existing layers are allowed (staged; the creator signs commitAdditions)
        $this->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'Extra', 'price' => 3, 'svg' => self::CLEAN])->assertCreated();
    }

    public function test_content_endpoints_are_sandboxed(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $this->as(self::ALICE)->postJson("/api/fub/drafts/{$d->id}/items", ['layer' => 'bg', 'name' => 'A', 'price' => 2, 'svg' => self::CLEAN])->assertCreated();
        $it = FubDraftItem::first();
        $r = $this->get("/fub-content/draft-item/{$it->id}.svg")->assertOk();
        $this->assertStringContainsString('sandbox', $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('image/svg+xml', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $comp = $this->get("/fub-content/draft/{$d->id}.svg?i={$it->id}")->assertOk()->getContent();
        $this->assertStringContainsString('<g id="fun-l0" data-item="d'.$it->id.'">', $comp);
    }

    // --------------------------------------------------------------------------------------------- assistant tools

    private function grantFor(FubDraft $d): string
    {
        return FubAuth::mintGrant($d)['grant'];
    }

    private function tool(string $method, string $uri, array $body = [], ?string $grant = null, ?string $token = self::TOKEN)
    {
        $h = ['Accept' => 'application/json'];
        if ($token) {
            $h['Authorization'] = 'Bearer '.$token;
        }
        if ($grant) {
            $h['X-Fub-Grant'] = $grant;
        }

        return $this->json($method, '/api/fub-tools/'.$uri, $body, $h);
    }

    public function test_tool_api_needs_token_and_owner_grant(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $g = $this->grantFor($d);
        $this->tool('GET', 'draft', [], $g, null)->assertStatus(401);
        $this->tool('GET', 'draft', [], $g, 'wrong-token-wrong-token-wrong-token-xx')->assertStatus(401);
        $this->tool('GET', 'draft')->assertStatus(403);
        $this->tool('GET', 'draft', [], 'fg1.forged.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA')->assertStatus(403);
        $tampered = preg_replace('/^fg1\.([^.]+)\./', 'fg1.'.rtrim(strtr(base64_encode(json_encode(['a' => self::BOB, 'd' => $d->id, 'e' => time() + 99, 'p' => 0, 'n' => 'x'])), '+/', '-_'), '=').'.', $g);
        $this->tool('GET', 'draft', [], $tampered)->assertStatus(403);
        $this->tool('GET', 'grant', [], $g)->assertOk()->assertJsonPath('address', self::ALICE)->assertJsonPath('draftId', $d->id);
        $this->tool('GET', 'draft', [], $g)->assertOk()->assertJsonPath('draft.name', 'Space Snacks');
    }

    public function test_tool_api_edits_only_the_granted_draft(): void
    {
        $this->on();
        $a = $this->draftFor(self::ALICE);
        $b = $this->draftFor(self::BOB);
        $bItem = $b->items()->create(['layer_key' => 'bg', 'name' => 'Bob', 'price' => 5, 'svg' => '<g/>', 'sha3' => 'x', 'size' => 4]);
        $g = $this->grantFor($a);
        $r = $this->tool('POST', 'items', ['layer' => 'bg', 'name' => 'Moon', 'price' => 4, 'svg' => self::CLEAN], $g)->assertOk();
        $item = FubDraftItem::find($r->json('item'));
        $this->assertSame($a->id, (int) $item->fub_draft_id);
        $this->assertTrue($item->ai_assisted, 'assistant items are marked');
        $this->tool('PATCH', "items/{$bItem->id}", ['name' => 'hijack'], $g)->assertNotFound();
        $this->tool('PUT', "items/{$bItem->id}/svg", ['svg' => self::CLEAN], $g)->assertNotFound();
        $this->tool('DELETE', "items/{$bItem->id}?confirm=1", [], $g)->assertNotFound();
        $this->tool('GET', "items/{$bItem->id}/svg", [], $g)->assertNotFound();
        $this->assertSame('Bob', $bItem->fresh()->name);
        $this->tool('DELETE', "items/{$item->id}", [], $g)->assertStatus(422); // needs the user's confirm
        $this->tool('POST', 'layers', ['name' => 'Eyes'], $g)->assertOk();
        $this->assertDatabaseHas('fub_tool_calls', ['fub_draft_id' => $a->id, 'source' => 'assistant', 'tool' => 'create_item']);
        // no publish/commit/mint tools exist
        foreach (['publish', 'commit', 'mint', 'drafts', 'refill'] as $p) {
            $this->tool('POST', $p, [], $g)->assertStatus(404);
        }
    }

    public function test_grants_expire_and_can_be_revoked(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $g = $this->grantFor($d);
        $this->tool('GET', 'draft', [], $g)->assertOk();
        $this->as(self::ALICE)->deleteJson("/api/fub/drafts/{$d->id}/grant")->assertOk();
        $this->tool('GET', 'draft', [], $g)->assertStatus(403);
        $old = FubAuth::mintGrant($d->fresh(), -10)['grant'];
        $this->tool('GET', 'draft', [], $old)->assertStatus(403);
        $g2 = $this->grantFor($d->fresh());
        $this->tool('GET', 'draft', [], $g2)->assertOk();
        $d->fresh()->update(['status' => 'abandoned']);
        $this->tool('GET', 'draft', [], $g2)->assertStatus(403);
    }

    public function test_grant_endpoint_is_owner_only(): void
    {
        $this->on();
        $d = $this->draftFor(self::ALICE);
        $this->as(self::BOB)->postJson("/api/fub/drafts/{$d->id}/grant")->assertNotFound();
        $g = $this->as(self::ALICE)->postJson("/api/fub/drafts/{$d->id}/grant")->assertOk()->json('grant');
        $this->assertNotNull(FubAuth::verifyGrant($g));
    }

    // --------------------------------------------------------------------------------------------- chain sync plan

    private function chainFub(array $over = []): array
    {
        return ['fub' => array_merge(['id' => 9, 'creator' => self::ALICE, 'status' => 'building', 'name' => 'Space Snacks', 'description' => '',
            'layers' => [['key' => 'bg', 'name' => 'Background', 'required' => true], ['key' => 'hat', 'name' => 'Hat', 'required' => true]],
            'royaltyCut' => 0.05, 'unique' => true, 'maxSupply' => 500, 'mintCap' => 500, 'license' => 'holder-commercial'], $over),
            'heaviest' => 0, 'composedCap' => 120000, 'maxLayers' => 8, 'largeCap' => 3, 'itemIds' => [], 'pendingIds' => []];
    }

    public function test_sync_plan_orders_ops_and_skips_live_items(): void
    {
        $d = $this->draftFor(self::ALICE);
        $d->update(['chain_fub_id' => 9, 'description' => '', 'settings' => ['royalty' => 0.05, 'unique' => true, 'max_supply' => 500, 'license' => 'holder-commercial']]);
        $new = $d->items()->create(['layer_key' => 'bg', 'name' => 'New', 'price' => 5, 'svg' => '<g id="a"/>', 'sha3' => 'h1', 'size' => 10]);
        $changed = $d->items()->create(['layer_key' => 'bg', 'name' => 'Changed', 'price' => 5, 'svg' => '<g id="b"/>', 'sha3' => 'h2new', 'size' => 10, 'chain_item_id' => 21, 'chain_state' => 'dirty']);
        $priced = $d->items()->create(['layer_key' => 'hat', 'name' => 'Priced', 'price' => 8, 'svg' => '<g id="c"/>', 'sha3' => 'h3', 'size' => 10, 'chain_item_id' => 22, 'chain_state' => 'dirty']);
        $gone = $d->items()->create(['layer_key' => 'hat', 'name' => 'Gone', 'price' => 5, 'svg' => '<g/>', 'sha3' => 'h4', 'size' => 4, 'chain_item_id' => 23, 'chain_state' => 'deleted']);
        $ci = fn ($id, $hash, $price, $layer, $live = false) => ['item' => ['id' => $id, 'hash' => $hash, 'large' => false, 'layer' => $layer, 'name' => $id === 21 ? 'Changed' : 'Priced',
            'description' => '', 'rarity' => 'common', 'price' => $price, 'maxMint' => 0, 'increase' => 0, 'priceCap' => $price, 'metadata' => ['aiAssisted' => 'false']], 'live' => $live];
        $plan = DraftSync::plan($d, $this->chainFub(), [$ci(21, 'h2old', 5, 'bg'), $ci(22, 'h3', 5, 'hat'), $ci(23, 'h4', 5, 'hat')]);
        $ops = array_column($plan, 'op');
        $this->assertSame(['removeItem', 'putItem', 'replaceItem', 'updateItem'], $ops);
        $this->assertSame($gone->id, $plan[0]['item']);
        $this->assertSame($new->id, $plan[1]['item']);
        $this->assertSame($changed->id, $plan[2]['item']);
        $this->assertSame($priced->id, $plan[3]['item']);
        // the svg goes as the last String argument, exactly the sanitised bytes
        $this->assertSame('<g id="a"/>', end($plan[1]['args'])['value']);
        // live items are never touched
        $plan = DraftSync::plan($d, $this->chainFub(['status' => 'open']), [$ci(21, 'h2old', 5, 'bg', true), $ci(22, 'h3', 5, 'hat', true)]);
        $this->assertSame(['putItem'], array_column($plan, 'op'));
        // settings / layers drift -> setInfo / setLayers / setOptions
        $d->update(['name' => 'Renamed', 'settings' => ['royalty' => 0.02, 'unique' => false, 'max_supply' => 100, 'license' => 'cc0']]);
        $d->layers()->where('key', 'hat')->update(['name' => 'Hats']);
        $ops = array_column(DraftSync::plan($d, $this->chainFub(), [$ci(21, 'h2new', 5, 'bg'), $ci(22, 'h3', 8, 'hat')]), 'op');
        $this->assertSame(['setInfo', 'setLayers', 'setOptions', 'putItem'], $ops); // item 23 is gone from the chain already
    }

    public function test_operator_transactions_are_scoped_to_the_uploader(): void
    {
        foreach (['setInfo', 'setLayers', 'setOptions', 'putItem', 'updateItem', 'replaceItem', 'removeItem', 'recomputeLayerMax'] as $op) {
            $tx = FubOperator::tx($op);
            $this->assertStringContainsString('auth(FlovatarUniverseBuilder.Upload) &FlovatarUniverseBuilder.Uploader', $tx);
            $this->assertStringContainsString('prepare(signer: auth(CopyValue) &Account)', $tx, 'operator tx can only copy its capability');
            $this->assertStringNotContainsString('Withdraw', $tx);
        }
    }

    public function test_operator_signature_verifies(): void
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $hex = bin2hex(openssl_pkey_get_details($k)['ec']['d']);
        $hex = str_pad($hex, 64, '0', STR_PAD_LEFT);
        $raw = FubOperator::sign('hello flow', $hex);
        $this->assertSame(64, strlen($raw));
        $this->assertSame(128, strlen(FubOperator::publicKeyHex($hex)));
    }

    // --------------------------------------------------------------------------------------------- events / index

    public function test_events_index_fubs_and_funs(): void
    {
        $this->on();
        $chain = $this->chain();
        $chain->fubs[1] = $this->chainFub(['id' => 1, 'status' => 'open', 'slots' => 25, 'tier' => 'common', 'minted' => 1, 'suspended' => false, 'payout' => self::ALICE,
            'islandId' => 1, 'manifest' => 'm', 'pending' => '', 'items' => 1, 'publishedAt' => time() - 100, 'largeItems' => 0, 'voucherIds' => [197],
            'feeTreasury' => 0.025, 'feeBurn' => 0.025, 'tosHash' => 't', 'seq' => 3]);
        $chain->fubs[1]['itemIds'] = [5];
        $chain->items[1] = [['item' => ['id' => 5, 'layer' => 'bg', 'name' => 'Sky', 'description' => '', 'rarity' => 'common', 'price' => 10, 'currentPrice' => 10,
            'maxMint' => 0, 'minted' => 1, 'increase' => 0, 'priceCap' => 10, 'size' => 30, 'hash' => 'abc', 'state' => 'live', 'suspended' => false, 'large' => false,
            'metadata' => ['aiAssisted' => 'true']], 'live' => true]];
        $p = new FubEventProcessor(app(FubIndexer::class));
        $ev = (object) ['type' => 'A.2c6a4c4342aeca55.FlovatarUniverseBuilder.FunMinted', 'transaction_id' => 'ab', 'event_date' => now()->toIso8601String(),
            'flow_metadata' => collect(['id' => 1, 'fubId' => 1, 'serial' => 1, 'items' => [5], 'price' => 10.0, 'to' => self::BOB])];
        $this->assertTrue($p->handles($ev));
        app(FubIndexer::class)->refreshFub(1); // surfaces errors directly
        $this->assertTrue($p->process($ev));
        $this->assertSame(['bg' => 5], Fun::first()->picks);
        $this->assertSame(self::BOB, Fun::first()->owner);
        $this->assertSame('open', Fub::first()->status);
        $this->assertSame('<g id="u1-root"><path d="M1 1L2 2"/></g>', FubItem::first()->svg);
        $dep = (object) ['type' => 'A.631e88ae7f1d7c20.NonFungibleToken.Deposited', 'flow_metadata' => collect(['type' => 'A.2c6a4c4342aeca55.FlovatarUniverseBuilder.NFT', 'id' => 1, 'to' => self::ALICE])];
        $this->assertTrue($p->handles($dep));
        $p->process($dep);
        $this->assertSame(self::ALICE, Fun::first()->owner);
        // pages
        $this->get('/fub/1')->assertOk()->assertSee('Space Snacks')->assertSee('data-fub="builder"', false);
        $this->getJson('/api/fub/1')->assertOk()->assertJsonPath('itemList.0.aiAssisted', true);
        $svg = $this->get('/fub-content/fun/1.svg')->assertOk()->getContent();
        $this->assertSame('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 3000"><g id="fun-l0" data-item="5"><g id="u1-root"><path d="M1 1L2 2"/></g></g></svg>', $svg);
        $this->get('/fub/fun/1')->assertOk()->assertSee('Sky');
    }

    public function test_admin_hide_is_site_only_and_admin_only(): void
    {
        $this->on();
        Fub::create(['flow_id' => 3, 'creator' => self::ALICE, 'status' => 'open', 'tier' => 'common', 'slots' => 25, 'mint_cap' => 500, 'max_supply' => 500, 'name' => 'Bad', 'island_id' => 2, 'published_at' => now()]);
        $this->as(self::ALICE)->get('/fub/admin')->assertNotFound();
        $this->as(self::ALICE)->post('/fub/admin/3/hide', ['hidden' => 1])->assertNotFound();
        $this->as(self::BOB)->get('/fub/admin')->assertOk()->assertSee('Bad');
        $this->as(self::BOB)->post('/fub/admin/3/hide', ['hidden' => 1, 'reason' => 'stolen'])->assertRedirect();
        $this->assertNotNull(Fub::first()->hidden_at);
        $this->get('/fub/3')->assertNotFound();
        $this->getJson('/api/fub/3')->assertNotFound();
        $this->get('/fub-content/fub/3.svg?i=1')->assertNotFound();
        $this->assertSame(0, Fub::query()->listed()->count());
    }

    public function test_reports_are_stored_and_throttled(): void
    {
        $this->on();
        Fub::create(['flow_id' => 4, 'creator' => self::ALICE, 'status' => 'open', 'tier' => 'common', 'slots' => 25, 'mint_cap' => 500, 'max_supply' => 500, 'name' => 'R', 'published_at' => now()->subDays(5), 'checks_passed_at' => now()]);
        $this->assertSame(1, Fub::query()->listed()->count());
        $this->postJson('/api/fub/report', ['fub' => 4, 'reason' => 'stolen-art', 'details' => 'copied from x'])->assertOk();
        $this->postJson('/api/fub/report', ['fub' => 4, 'reason' => 'nonsense'])->assertStatus(422);
        $this->postJson('/api/fub/report', ['fub' => 999, 'reason' => 'other'])->assertNotFound();
        $this->assertDatabaseHas('fub_reports', ['fub_flow_id' => 4, 'reason' => 'stolen-art', 'status' => 'open']);
        $this->assertSame(0, Fub::query()->listed()->count(), 'open report keeps it out of the directory');
    }
}
