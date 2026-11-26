<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Island;
use App\Models\IslandResident;
use App\Models\IslandTemplate;
use App\Services\Flow\FlowEventTypes;
use App\Services\Islands\IslandChain;
use App\Services\Islands\IslandEventProcessor;
use App\Services\Islands\IslandScene;
use App\Services\Render\ItemRenderer;
use App\Support\Islands;
use App\Support\Stealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Asteroid Islands (KB notes/islands-build.md): ISLANDS_MODE off by default (nothing registered, indexed or rendered),
 * fail-closed without a contract address, stealth hides from anonymous visitors, pages/JSON/SVG when on, contract-identical
 * scene composition (testnet fixture), event processing into the islands tables.
 */
class IslandsTest extends TestCase
{
    private const ADDR = '0x2c6a4c4342aeca55';

    private const OWNER = '0x27407eda8e2bb47c';

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'headless.enabled' => false,
            'render.enabled' => false,
            'services.flow.address' => self::ADDR,
            'services.flow.network' => 'testnet',
            'services.flow.nftaddress' => '0x631e88ae7f1d7c20',
            'islands.contract' => self::ADDR,
            'islands.premium_contracts' => ['Flovatar' => self::ADDR, 'Flobot' => self::ADDR, 'FlovatarDustCollectible' => self::ADDR, 'Sportvatar' => '0xc80df59e5354ca8a'],
        ]);
        DB::purge();
        (require base_path('database/migrations/2026_11_26_100001_create_islands_tables.php'))->up();
        \Illuminate\Support\Facades\Schema::create('rendered_images', function ($t) {
            $t->increments('id');
            $t->string('item_type');
            $t->unsignedBigInteger('flow_id');
            $t->string('hash')->nullable();
            $t->string('path')->nullable();
            $t->string('source')->nullable();
            $t->json('variants')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('rendered_at')->nullable();
            $t->timestamps();
        });
        $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/islands-scene-1.json')), true);
        $fx = $this->fixture;
        $chain = new class extends IslandChain {
            public array $cfg = [];

            public function config(bool $fresh = false): array
            {
                return $this->cfg;
            }
        };
        $chain->cfg = [
            'contract' => self::ADDR, 'svgOpen' => $fx['cfg']['svgOpen'], 'sceneOrder' => $fx['cfg']['sceneOrder'],
            'premium' => ['common' => 3, 'epic' => 6, 'legendary' => 10], 'slotModel' => 'pad', 'tintRarities' => ['epic', 'legendary'],
            'layers' => [], 'budget' => ['common' => 120000], 'premiumTypes' => Islands::premiumTypes(), 'maxName' => 32, 'maxDescription' => 500,
        ];
        $this->app->instance(IslandChain::class, $chain);
        IslandTemplate::create(['flow_id' => 1, 'layer' => 'base', 'rarity' => 'common', 'name' => 'asteroid desert rock', 'enabled' => true,
            'svg' => $fx['templates']['1']['svg'], 'svg_front' => '', 'svg_length' => $fx['templates']['1']['svgLength'], 'svg_front_length' => 0,
            'svg_hash' => sha1($fx['templates']['1']['svg']), 'metadata' => ['anchor.P1' => '1512,2610,0.2,front,0']]);
    }

    private function mode(?string $m): void
    {
        config(['stealth.features.islands' => $m]);
    }

    private function routes(): void
    {
        Route::middleware(['web', 'stealth:islands'])->group(base_path('routes/islands.php'));
    }

    public function test_off_by_default(): void
    {
        $this->assertNull((require base_path('config/stealth.php'))['features']['islands']);
        $this->mode(null);
        $this->assertSame('off', Stealth::mode('islands'));
        $this->assertFalse(Islands::active());
        $this->assertFalse(Islands::enabled());
        $this->assertFalse(Route::has('islands.index'));
        $this->get('/islands')->assertNotFound();
        $this->assertSame([], FlowEventTypes::islandTypes());
        $this->assertNotContains('A.2c6a4c4342aeca55.FlovatarAsteroidIsland.NFT', FlowEventTypes::standardDepositNftTypes());
        $this->assertNotContains('island', ItemRenderer::types());
        $this->assertNotContains('i', Stealth::bundles());
        $html = view('partials.navigation')->render();
        $this->assertStringNotContainsString('data-islands', $html);
    }

    public function test_fails_closed_without_contract_and_on_invalid_mode(): void
    {
        $this->mode('on');
        config(['islands.contract' => '']);
        $this->assertFalse(Islands::active());
        config(['islands.contract' => 'nonsense']);
        $this->assertFalse(Islands::active());
        config(['islands.contract' => self::ADDR]);
        $this->assertTrue(Islands::active());
        $this->mode('yes please');
        $this->assertFalse(Islands::active());
    }

    public function test_stealth_hides_everything_from_anonymous_visitors(): void
    {
        $this->mode('stealth');
        $this->routes();
        $this->assertTrue(Islands::active());
        $this->get('/islands')->assertNotFound();
        $this->get('/api/islands')->assertNotFound();
        $this->get('/islands/1')->assertNotFound();
        $this->assertNotContains('i', Stealth::bundles(request()));
        // indexing keeps running in stealth
        $this->assertContains(Islands::typeId('Terraformed'), FlowEventTypes::all());
    }

    public function test_pages_json_and_svg_when_on(): void
    {
        $this->mode('on');
        $this->routes();
        $this->seedIsland();
        $r = $this->get('/islands');
        $r->assertOk();
        $this->assertStringContainsString('K-334/0001', $r->getContent());
        $this->assertStringContainsString('window.ISLANDS', $r->getContent());
        $this->assertStringContainsString('data-islands="nav"', $r->getContent());

        $r = $this->get('/islands/1');
        $r->assertOk();
        $this->assertStringContainsString('property="og:title" content="Rock Garden · K-334/0001', $r->getContent());
        $this->assertStringContainsString('x-data="islandPage(1)"', $r->getContent());

        $this->get('/islands/build')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // scene = contract getSvg() byte for byte (testnet fixture)
        $this->assertSame($this->fixture['expected'], $this->get('/islands/1/scene.svg')->assertOk()->getContent());

        $j = $this->getJson('/api/islands/1')->assertOk()->json();
        $this->assertSame('K-334/0001', $j['designation']);
        $this->assertSame(3, $j['premiumSlots']);
        $this->assertSame('pad', $j['slotModel']);
        $bySlot = collect($j['residentsList'])->keyBy('slot');
        $this->assertTrue($bySlot['P1']['premium']);
        $this->assertSame('A1', $bySlot['P1']['chainSlot']);
        $this->assertFalse($bySlot['S1']['premium']);
        $this->assertSame('A4', $bySlot['S1']['chainSlot']);
        $this->assertEquals([1512, 2610, 0.2, 'front', false], $j['anchors']['P1']);
        $this->assertCount(1, $this->getJson('/api/islands?owner='.self::OWNER)->json('islands'));
        $this->getJson('/api/islands?owner=bad')->assertStatus(422);
    }

    public function test_full_svg_places_only_valid_premium_residents_at_the_anchor(): void
    {
        $this->mode('on');
        $island = $this->seedIsland();
        $art = new class extends \App\Services\Islands\ResidentArt {
            public function svg(string $typeId, int $nftId): ?string
            {
                return str_ends_with($typeId, '.Flovatar.NFT') ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 3000"><circle r="9"/></svg>' : null;
            }
        };
        $this->app->instance(\App\Services\Islands\ResidentArt::class, $art);
        $svg = app(IslandScene::class)->full($island);
        $this->assertStringContainsString('<g id="residents-front"><g class="island-resident" data-slot="P1">', $svg);
        $this->assertStringContainsString('translate(1212 2065) scale(0.2)', $svg); // 1512-1500*.2, 2610-2725*.2
        $this->assertStringNotContainsString('data-slot="S1"', $svg); // standard residents never in the scene
        IslandResident::where('slot', 'A1')->update(['valid' => false, 'reason' => 'not-owned']);
        $this->assertStringNotContainsString('island-resident', app(IslandScene::class)->full($island->fresh()));
    }

    public function test_events_are_applied_in_chain_order(): void
    {
        $this->mode('on');
        Queue::fake();
        $p = new IslandEventProcessor();
        $a = 'A.2c6a4c4342aeca55.FlovatarAsteroidIsland.';
        $ev = fn (string $type, array $m, int $i = 0) => new Event(['type' => $type, 'flow_metadata' => $m, 'transaction_id' => 'tx'.$i, 'block_id' => 100 + $i, 'event_date' => now()]);

        $t = $ev($a.'Terraformed', ['id' => 5, 'designation' => 'K-334/0005', 'rarity' => 'epic', 'deedIds' => [1, 2, 3, 4, 5], 'deedTemplateIds' => [69, 69, 69, 69, 69],
            'deedRarity' => 'common', 'fused' => true, 'layers' => ['base' => 2, 'structure' => 4], 'tint' => '#5fb04a', 'owner' => self::OWNER]);
        $this->assertTrue($p->handles($t));
        $this->assertTrue($p->process($t));
        $island = Island::where('flow_id', 5)->first();
        $this->assertSame('epic', $island->rarity);
        $this->assertTrue($island->fused);
        $this->assertSame(['base' => 2, 'structure' => 4], $island->layers);
        $this->assertSame(self::OWNER, $island->flow_address);

        $p->process($ev($a.'ResidentSet', ['islandId' => 5, 'slot' => 'A1', 'nftType' => 'A.2c6a4c4342aeca55.Flovatar.NFT', 'nftId' => 1, 'owner' => self::OWNER], 1));
        $p->process($ev($a.'ResidentSet', ['islandId' => 5, 'slot' => 'B2', 'nftType' => 'A.2c6a4c4342aeca55.FlovatarComponent.NFT', 'nftId' => 9, 'owner' => self::OWNER], 2));
        $this->assertSame(2, Island::where('flow_id', 5)->value('residents_count'));
        $p->process($ev($a.'ResidentMoved', ['islandId' => 5, 'fromSlot' => 'A1', 'toSlot' => 'B2', 'owner' => self::OWNER], 3));
        $this->assertSame('A.2c6a4c4342aeca55.Flovatar.NFT', IslandResident::where('island_flow_id', 5)->where('slot', 'B2')->value('nft_type'));
        $this->assertSame(9, (int) IslandResident::where('island_flow_id', 5)->where('slot', 'A1')->value('nft_id'));
        $p->process($ev($a.'ResidentCleared', ['islandId' => 5, 'slot' => 'A1', 'nftType' => 'A.2c6a4c4342aeca55.FlovatarComponent.NFT', 'nftId' => 9, 'owner' => self::OWNER], 4));
        $this->assertSame(1, Island::where('flow_id', 5)->value('residents_count'));
        $p->process($ev($a.'IslandNameChanged', ['id' => 5, 'name' => 'Kaax Rock', 'owner' => self::OWNER], 5));
        $this->assertSame('Kaax Rock', Island::where('flow_id', 5)->value('name'));

        // sold: owner changes, residents set by the old owner turn invalid
        $dep = $ev('A.631e88ae7f1d7c20.NonFungibleToken.Deposited', ['type' => $a.'NFT', 'id' => 5, 'uuid' => 1, 'to' => '0xc327c7079fd1b4a3', 'collectionUUID' => 2], 6);
        $this->assertTrue($p->handles($dep));
        $p->process($dep);
        $this->assertSame('0xc327c7079fd1b4a3', Island::where('flow_id', 5)->value('flow_address'));
        $this->assertFalse((bool) IslandResident::where('island_flow_id', 5)->value('valid'));
        Queue::assertPushed(\App\Jobs\RefreshIsland::class);

        // Flobot deposits stay with the legacy branch
        $flobot = $ev('A.631e88ae7f1d7c20.NonFungibleToken.Deposited', ['type' => 'A.2c6a4c4342aeca55.Flobot.NFT', 'id' => 1, 'to' => self::OWNER], 7);
        $this->assertFalse($p->handles($flobot));
        $this->mode(null);
        $this->assertFalse($p->handles($t));
    }

    public function test_slot_mapping_helpers(): void
    {
        $this->assertSame(['P1', 'P2', 'P3', 'S1', 'S2'], array_column(Islands::slots('common', 3, 'ps'), 'slot'));
        $this->assertSame('S2', Islands::logicalSlot('epic', 'B3', 6, 'pad'));
        $this->assertSame('B3', Islands::logicalSlot('epic', 'B3', 6, 'ps'));
        $this->assertSame('caretaker', Islands::logicalSlot('epic', 'caretaker', 6, 'pad'));
        $this->assertSame(['P1' => [1.0, 2.0, 0.5, 'back', true]], IslandScene::parseAnchors(['anchors' => '[{"slot":"P1","x":1,"y":2,"scale":0.5,"z":"back","flip":true}]']));
        $this->assertSame(1000, ItemRenderer::heightFor('<svg x="1"><g/></svg>', 1000));
        $this->assertSame(1333, ItemRenderer::heightFor('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3025.27 4033.12">', 1000));
    }

    private function seedIsland(): Island
    {
        $island = Island::create(['flow_id' => 1, 'rarity' => 'common', 'name' => 'Rock Garden', 'layers' => ['base' => 1], 'tint' => null,
            'deed_ids' => [122], 'deed_rarity' => 'common', 'fused' => false, 'flow_address' => self::OWNER, 'residents_count' => 2, 'terraformed_at' => now()]);
        IslandResident::create(['island_flow_id' => 1, 'slot' => 'A1', 'nft_type' => 'A.2c6a4c4342aeca55.Flovatar.NFT', 'nft_id' => 1, 'set_by' => self::OWNER, 'valid' => true]);
        IslandResident::create(['island_flow_id' => 1, 'slot' => 'A4', 'nft_type' => 'A.2c6a4c4342aeca55.FlovatarComponent.NFT', 'nft_id' => 3, 'set_by' => self::OWNER, 'valid' => true]);

        return $island;
    }

    public function test_display_fix_declares_xlink_namespace(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><use xlink:href="#a"/></svg>';
        $fixed = \App\Services\Islands\IslandScene::displayFix($svg);
        $this->assertStringStartsWith('<svg xmlns:xlink="http://www.w3.org/1999/xlink" xmlns=', $fixed);
        $this->assertSame($fixed, \App\Services\Islands\IslandScene::displayFix($fixed));
        $plain = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';
        $this->assertSame($plain, \App\Services\Islands\IslandScene::displayFix($plain));
    }
}
