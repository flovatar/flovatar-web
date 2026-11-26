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

    private string $beltDir = '';

    protected function tearDown(): void
    {
        if ($this->beltDir !== '' && is_dir($this->beltDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($this->beltDir);
        }
        Islands::forgetDesignationPrefix();
        parent::tearDown();
    }

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
        Islands::forgetDesignationPrefix();
        $this->beltDir = sys_get_temp_dir().'/islands-belt-test-'.getmypid().'-'.uniqid();
        config(['islands.belt.dir' => $this->beltDir, 'islands.designation_prefix' => 'K313/']);
        DB::purge();
        (require base_path('database/migrations/2026_11_26_100001_create_islands_tables.php'))->up();
        (require base_path('database/migrations/2026_11_26_200001_add_display_to_island_residents.php'))->up();
        IslandResident::forgetDisplayColumns();
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
        $this->assertStringContainsString('K313/0001', $r->getContent());
        $this->assertStringContainsString('window.ISLANDS', $r->getContent());
        $this->assertStringContainsString('data-islands="nav"', $r->getContent());

        $r = $this->get('/islands/1');
        $r->assertOk();
        $this->assertStringContainsString('property="og:title" content="Rock Garden · K313/0001', $r->getContent());
        $this->assertStringContainsString('x-data="islandPage(1)"', $r->getContent());
        // round 4 island page: display zoom carousel (replaces the old under-scene carousel), accessible resident lists,
        // owner collection box with pick-and-place, pending-changes bar, layer tools
        foreach (['data-islands="zoom"', 'data-islands="display"', 'data-islands="display-list"', 'data-islands="collection"', 'data-islands="tab-family"',
            'data-islands="tab-all"', 'data-islands="pending-bar"', 'x-for="l in changeLayers"', 'data-islands="choose-display"', 'Save (one approval)'] as $needle) {
            $this->assertStringContainsString($needle, $r->getContent());
        }
        $this->assertStringNotContainsString('data-islands="carousel"', $r->getContent());

        $b = $this->get('/islands/build')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('x-data="islandsBuilder"', $b->getContent());
        // category tabs use the icon files (idle + selected), never inline placeholder symbols
        $this->assertStringContainsString(':src="tabIcon(t.key, true)"', $b->getContent());
        $this->assertStringNotContainsString('isb-i-sky', $b->getContent());
        foreach (['deeds', 'sky', 'base', 'structure', 'fub', 'display', 'foreground', 'fx', 'tint'] as $tabKey) {
            $this->assertFileExists(public_path('images/islands/tab-' . $tabKey . '.svg'));
            $this->assertFileExists(public_path('images/islands/tab-' . $tabKey . '-on.svg'));
        }
        // copy: the redeemed items are "TerraKits" everywhere users read it (never "land deed(s)" / "Deeds")
        $this->assertStringContainsString('<span class="n">1</span> TerraKits</button>', $b->getContent());
        $this->assertStringContainsString('each holds an Asteroid TerraKit, a FUB TerraKit and a Flobot Kit', $b->getContent());
        foreach ([$r->getContent(), $b->getContent(), $this->get('/islands')->getContent()] as $html) {
            $this->assertStringNotContainsStringIgnoringCase('land deed', $html);
            $this->assertDoesNotMatchRegularExpression('/\bdeeds?\b/i', strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html)));
        }
        // the "unminted" stamp belongs to the builder's Save PNG only: island pages never mention it
        $this->assertStringNotContainsStringIgnoringCase('unminted', $r->getContent());
        // round 4 builder: layer notes (mutable layers changeable, display explained) + review part notes
        $this->assertStringContainsString('data-islands="layer-note"', $b->getContent());
        $this->assertStringContainsString('isb-part-note', $b->getContent());
        // the size budget is internal: no size meter, no KB / byte figure anywhere in the builder by default
        $this->assertDoesNotMatchRegularExpression('/\bKB\b|\bkB\b|bytes|isb-meter|data-islands="budget"/i', $b->getContent());
        $this->assertFalse(\App\Support\Islands::bootstrap()['sizeMeter']);
        config(['islands.show_size_meter' => true]);
        $this->assertStringContainsString('data-islands="budget"', $this->get('/islands/build')->getContent());
        $this->assertTrue(\App\Support\Islands::bootstrap()['sizeMeter']);
        config(['islands.show_size_meter' => false]);

        // scene = contract getSvg() byte for byte (testnet fixture)
        $this->assertSame($this->fixture['expected'], $this->get('/islands/1/scene.svg')->assertOk()->getContent());

        $j = $this->getJson('/api/islands/1')->assertOk()->json();
        $this->assertSame('K313/0001', $j['designation']);
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

        $t = $ev($a.'Terraformed', ['id' => 5, 'designation' => 'K313/0005', 'rarity' => 'epic', 'deedIds' => [1, 2, 3, 4, 5], 'deedTemplateIds' => [69, 69, 69, 69, 69],
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

    public function test_designation_prefix_comes_from_the_contract_setting(): void
    {
        $this->mode('on');
        $this->assertSame('K313/', Islands::designationPrefix());
        $this->assertSame('K313/0007', Islands::designation(7));
        $this->assertSame('K313', Islands::sector());
        $this->assertSame('K313/', Islands::bootstrap()['designationPrefix']);
        // unsafe / empty settings never reach the pages
        foreach ([null, '', '<b>', str_repeat('K', 30), "K\n1/"] as $bad) {
            Islands::rememberDesignationPrefix($bad);
            $this->assertSame('K313/', Islands::designationPrefix());
        }
        // what IslandChain::readConfig() does with the contract setting designationPrefix
        Islands::rememberDesignationPrefix('NC-9/');
        $this->assertSame('NC-9/0042', Islands::designation(42));
        Islands::forgetDesignationPrefix(); // next request: read from the cache, not from the memo
        $this->assertSame('NC-9/0042', Islands::designation(42));
        $this->assertSame('NC-9', Islands::sector());
        $this->assertSame('Asteroid NC-9/0003', (new Island(['flow_id' => 3]))->display_name);
    }

    public function test_belt_page_manifest_atlas_and_sprite(): void
    {
        $this->mode('on');
        $this->routes();
        \Illuminate\Support\Facades\Storage::fake('belt-renders');
        config(['render.disk' => 'belt-renders']);
        $this->seedIsland();
        Island::create(['flow_id' => 2, 'rarity' => 'legendary', 'name' => null, 'layers' => ['base' => 1], 'flow_address' => '0x73f3e0573458fd3c', 'residents_count' => 0]);
        Island::create(['flow_id' => 400, 'rarity' => 'epic', 'name' => 'Far Out', 'layers' => ['base' => 1], 'flow_address' => self::OWNER, 'residents_count' => 7]);
        // renders of islands 1 and 400 (transparent PNG with a red block), none for island 2
        $png = function (): string {
            $im = imagecreatetruecolor(300, 400);
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagefilledrectangle($im, 60, 120, 240, 360, imagecolorallocatealpha($im, 255, 0, 0, 0));
            ob_start();
            imagepng($im);

            return (string) ob_get_clean();
        };
        foreach ([1 => 'aaaaaaaaaaaa', 400 => 'bbbbbbbbbbbb'] as $id => $hash) {
            \Illuminate\Support\Facades\Storage::disk('belt-renders')->put('testnet/island/r/'.$id.'/'.$hash.'/full.png', $png());
            DB::table('rendered_images')->insert(['item_type' => 'island', 'flow_id' => $id, 'hash' => $hash, 'path' => 'testnet/island/r/'.$id.'/'.$hash,
                'source' => 'server', 'variants' => json_encode(['full.png', 'og.jpg']), 'rendered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        // page: 3D stage + server-rendered list (links for no-JS / crawlers), new copy, OG tags
        $html = $this->get('/islands')->assertOk()->getContent();
        foreach (['class="belt-canvas"', 'x-data="islandsBelt"', 'Kaax Asteroid Belt · Sector K313', 'href="/islands/1"', 'href="/islands/400"', 'K313/0400',
            'Terraform an island', 'href="/islands/build"', 'property="og:title" content="Asteroid Islands · Kaax Asteroid Belt, Sector K313', 'role="dialog"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringNotContainsString('K-334', $html);
        $this->assertStringNotContainsString('Nova City', strip_tags(preg_replace('~<script.*?</script>~s', '', $html)), 'the city is never named in plain page copy');
        $this->assertSame('Nova City', Islands::bootstrap()['belt']['center']);
        $this->assertContains('b', array_keys(Stealth::LAZY_CHUNKS));
        $this->assertSame('islands', Stealth::featureForBundle('b'));

        // manifest: compact rows, atlas pages by id (336 per page), built inline for a small backlog
        $r = $this->getJson('/api/islands/belt')->assertOk();
        $this->assertStringContainsString('public', (string) $r->headers->get('Cache-Control'));
        $m = $r->json();
        $this->assertSame(3, $m['count']);
        $this->assertSame('K313/', $m['prefix']);
        $this->assertSame(['common' => 1, 'epic' => 1, 'legendary' => 1], $m['counts']);
        $this->assertSame(336, $m['cell']['per']);
        $this->assertSame(['common' => 5, 'epic' => 25, 'legendary' => 125], $m['capacity']);
        $this->assertSame([self::OWNER, '0x73f3e0573458fd3c'], $m['owners']);
        $this->assertSame([1, 0, 'Rock Garden', 2, 0], array_slice($m['islands'][0], 0, 5));
        $this->assertSame([1, 'aaaaaaaaaaaa'], array_slice($m['islands'][0], 6));
        $this->assertSame([2, 2, '', 0, 1], array_slice($m['islands'][1], 0, 5));
        $this->assertSame([0, ''], array_slice($m['islands'][1], 6), 'no render yet: no cell, no sprite');
        $this->assertSame([400, 1, 'Far Out', 7, 0], array_slice($m['islands'][2], 0, 5));
        $this->assertSame(['0', '1'], array_map('strval', array_keys($m['atlases'])));
        $this->assertMatchesRegularExpression('~^/api/islands/belt/atlas/0-[a-f0-9]{12}\.webp$~', $m['atlases']['0']);

        // atlas page 0: island 1 in cell 0 (red body in the middle of the cell, transparent corner)
        $a = $this->get($m['atlases']['0'])->assertOk();
        $this->assertSame('image/webp', $a->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $a->headers->get('Cache-Control'));
        $im = imagecreatefromwebp($a->baseResponse->getFile()->getPathname());
        $this->assertSame([2048, 2048], [imagesx($im), imagesy($im)]);
        $mid = imagecolorsforindex($im, imagecolorat($im, 48, 80));
        $this->assertTrue($mid['red'] > 200 && $mid['green'] < 60 && $mid['alpha'] < 10);
        $this->assertSame(127, imagecolorsforindex($im, imagecolorat($im, 1, 1))['alpha']);
        $this->assertSame(127, imagecolorsforindex($im, imagecolorat($im, 96 + 48, 80))['alpha'], 'cell 1 (island 2) is empty');
        // island 400 = page 1, slot 63 = column 0, row 3
        $im = imagecreatefromwebp($this->get($m['atlases']['1'])->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertTrue(imagecolorsforindex($im, imagecolorat($im, 48, 3 * 128 + 80))['red'] > 200);
        $this->get('/api/islands/belt/atlas/0-000000000000.webp')->assertNotFound();

        // near-LOD sprite: only for the current render key
        $sp = $this->get('/api/islands/belt/sprite/1-aaaaaaaaaaaa.webp')->assertOk();
        $im = imagecreatefromwebp($sp->baseResponse->getFile()->getPathname());
        $this->assertSame([512, 683], [imagesx($im), imagesy($im)]);
        $this->get('/api/islands/belt/sprite/1-cccccccccccc.webp')->assertNotFound();
        $this->get('/api/islands/belt/sprite/2-aaaaaaaaaaaa.webp')->assertNotFound();

        // a new render of island 1 -> page 0 gets a new name, page 1 keeps its file; nothing to do -> no-op
        $belt = app(\App\Services\Islands\IslandBelt::class);
        $this->assertSame(['cells' => 0, 'atlases' => 0, 'pending' => 0], $belt->warm());
        \Illuminate\Support\Facades\Storage::disk('belt-renders')->put('testnet/island/r/1/dddddddddddd/full.png', $png());
        DB::table('rendered_images')->where('flow_id', 1)->update(['hash' => 'dddddddddddd', 'path' => 'testnet/island/r/1/dddddddddddd']);
        $m2 = $this->app->make(\App\Services\Islands\IslandBelt::class)->manifest(true);
        $this->assertNotSame($m['atlases']['0'], ((array) $m2['atlases'])['0']);
        $this->assertSame($m['atlases']['1'], ((array) $m2['atlases'])['1']);
        $this->assertSame('dddddddddddd', $m2['islands'][0][7]);
        $this->get('/api/islands/belt/sprite/1-dddddddddddd.webp')->assertOk();

        // stealth: nothing for anonymous visitors
        $this->mode('stealth');
        $this->app->make(\App\Services\Islands\IslandBelt::class)->forget();
        $this->get('/api/islands/belt')->assertNotFound();
        $this->get(((array) $m2['atlases'])['0'])->assertNotFound();
    }

    public function test_round4_display_screen_set_layer_events_and_api(): void
    {
        $this->mode('on');
        $this->routes();
        Queue::fake();
        $chain = app(IslandChain::class);
        $chain->cfg += ['contractVersion' => 3, 'sharedLayers' => ['sky', 'fx', 'display'], 'mutableLayers' => ['sky'], 'displayLayer' => 'display',
            'displayMigration' => true, 'setLayerPaused' => false, 'slotModel' => 'ps'];
        $chain->cfg['slotModel'] = 'ps';
        $chain->cfg['layers'] = ['base' => ['required' => ['common'], 'allowed' => ['common']], 'sky' => ['required' => [], 'allowed' => ['common']],
            'display' => ['required' => ['common'], 'allowed' => ['common']], 'fx' => ['required' => [], 'allowed' => ['common']]];
        $chain->cfg['sceneOrder'] = array_merge($this->fixture['cfg']['sceneOrder'], ['display']);

        // contract grammar for the screen
        $this->assertSame(['x' => 100.0, 'y' => 200.0, 'w' => 300.0, 'h' => 150.0, 'quad' => []], IslandScene::parseScreen('100,200,300,150'));
        $this->assertSame([10.0, 20.0, 110.0, 20.0, 110.0, 70.0, 10.0, 70.0], IslandScene::parseScreen('0,0,100,50,10,20,110,20,110,70,10,70')['quad']);
        $this->assertSame(1.0, IslandScene::parseScreen('{"x":1,"y":2,"w":3,"h":4}')['x']);
        $this->assertSame([10.0, 20.0, 110.0, 25.0, 108.0, 72.0, 12.0, 70.0], IslandScene::parseScreen('10,20,100,50', '10,20 110,25 108,72 12,70')['quad']);
        $this->assertNull(IslandScene::parseScreen('10,20,100,50', '10,20 110,25'));
        foreach (['', '1,2,3', '1,2,0,4', 'a,b,c,d', '1,2,3,-4', '1,2,3,4,5'] as $bad) {
            $this->assertNull(IslandScene::parseScreen($bad), $bad);
        }
        $this->assertSame('<defs><clipPath id="screen-clip-7"><rect x="100" y="200" width="300" height="150"/></clipPath></defs>'
            .'<g id="screen-S1" class="resident standard screen" data-slot="S1" clip-path="url(#screen-clip-7)">'
            .'<image href="data:a&amp;b" x="100" y="200" width="300" height="150" preserveAspectRatio="xMidYMid slice"/></g>',
            IslandScene::screenSvg(7, IslandScene::parseScreen('100,200,300,150'), 'data:a&b', 'S1'));
        $this->assertSame('<svg><g id="layer-display"><d/></g><S/><g id="layer-fx"/></svg>', IslandScene::insertScreen('<svg><g id="layer-display"><d/></g><g id="layer-fx"/></svg>', '<S/>', 4));
        $this->assertSame('<svg><g id="display-screen"><S/></g></svg>', IslandScene::insertScreen('<svg><g id="display-screen"/></svg>', '<S/>', 4));

        // an island with a display: the first valid standard resident is drawn in the screen (data: URI, never external)
        IslandTemplate::create(['flow_id' => 50, 'layer' => 'display', 'rarity' => 'any', 'name' => 'Billboard', 'enabled' => true, 'svg' => '<rect id="bb"/>', 'svg_front' => '',
            'svg_length' => 15, 'svg_front_length' => 0, 'svg_hash' => sha1('bb'), 'metadata' => ['screen' => '100,200,300,150']]);
        IslandTemplate::create(['flow_id' => 51, 'layer' => 'sky', 'rarity' => 'any', 'name' => 'Nebula', 'enabled' => true, 'svg' => '<rect id="sk"/>', 'svg_front' => '',
            'svg_length' => 15, 'svg_front_length' => 0, 'svg_hash' => sha1('sk'), 'metadata' => []]);
        $island = Island::create(['flow_id' => 9, 'rarity' => 'common', 'layers' => ['base' => 1, 'display' => 50], 'flow_address' => self::OWNER, 'residents_count' => 2]);
        IslandResident::create(['island_flow_id' => 9, 'slot' => 'S2', 'nft_type' => 'A.2c6a4c4342aeca55.FlovatarComponent.NFT', 'nft_id' => 4, 'set_by' => self::OWNER, 'valid' => true,
            'name' => 'Flobit #4', 'thumbnail' => 'data:image/png;base64,QUJD']);
        IslandResident::create(['island_flow_id' => 9, 'slot' => 'S3', 'nft_type' => 'A.2c6a4c4342aeca55.FlovatarComponent.NFT', 'nft_id' => 5, 'set_by' => self::OWNER, 'valid' => true,
            'name' => 'Flobit #5', 'thumbnail' => 'data:image/png;base64,REVG']);
        $svg = app(IslandScene::class)->full($island);
        $this->assertStringContainsString('<g id="layer-display"><rect id="bb"/></g><defs><clipPath id="screen-clip-9">', $svg);
        $this->assertStringContainsString('data-slot="S2"', $svg);
        $this->assertStringContainsString('href="data:image/png;base64,QUJD"', $svg);
        $this->assertStringNotContainsString('REVG', $svg);
        IslandResident::where('slot', 'S2')->update(['valid' => false, 'reason' => 'not-owned']);
        $this->assertStringContainsString('href="data:image/png;base64,REVG"', app(IslandScene::class)->full($island->fresh()), 'the next valid one takes the screen');
        // remote thumbnails: only public hosts are fetched (SSRF guard)
        $this->assertFalse(\App\Services\Islands\ResidentArt::publicHost('127.0.0.1'));
        $this->assertFalse(\App\Services\Islands\ResidentArt::publicHost('localhost'));
        $this->assertFalse(\App\Services\Islands\ResidentArt::publicHost('10.0.0.8'));
        $this->assertNull(app(\App\Services\Islands\ResidentArt::class)->dataUri('http://169.254.169.254/latest/meta-data'));
        $this->assertSame('https://ipfs.io/ipfs/QmX', \App\Services\Islands\ResidentArt::httpUrl('ipfs://QmX'));

        // API: display + screen, carousel data (name / thumbnail), settable layers (sky any time; display already set)
        $j = $this->getJson('/api/islands/9')->assertOk()->json();
        $this->assertSame(50, $j['display']['templateId']);
        $this->assertEquals(['x' => 100, 'y' => 200, 'w' => 300, 'h' => 150, 'quad' => []], $j['display']['screen']);
        $this->assertSame(['sky'], $j['settableLayers']);
        $this->assertFalse($j['canChooseDisplay']);
        $this->assertSame('S3', $j['screenSlot']);
        $this->assertSame('Flobit #5', collect($j['residentsList'])->firstWhere('slot', 'S3')['name']);
        $this->assertSame('Billboard', $j['layerNames']['display']);
        // an island without a display may choose one once
        Island::create(['flow_id' => 10, 'rarity' => 'common', 'layers' => ['base' => 1], 'flow_address' => self::OWNER, 'residents_count' => 0]);
        $j = $this->getJson('/api/islands/10')->assertOk()->json();
        $this->assertTrue($j['canChooseDisplay']);
        $this->assertSame(['sky', 'display'], $j['settableLayers']);
        $this->assertNull($j['display']);
        // settable layers follow the contract setting (mutableLayers), nothing hardcoded: sky + fx (Luca 2026-11-26)
        $chain->cfg['mutableLayers'] = ['sky', 'fx'];
        $this->assertSame(['sky', 'fx'], $this->getJson('/api/islands/9')->assertOk()->json('settableLayers'));
        $this->assertSame(['sky', 'fx', 'display'], $this->getJson('/api/islands/10')->assertOk()->json('settableLayers'));
        $chain->cfg['mutableLayers'] = ['sky'];
        // displayMigration off (setting, Luca 2026-11-26): old islands keep no display, no one-time action
        $chain->cfg['displayMigration'] = false;
        $j = $this->getJson('/api/islands/10')->assertOk()->json();
        $this->assertFalse($j['canChooseDisplay']);
        $this->assertSame(['sky'], $j['settableLayers']);
        $this->assertNull($j['display']);
        $this->assertNull($j['screenSlot']);
        $this->get('/islands/10')->assertOk()->assertSee('data-islands="no-display"', false);
        $chain->cfg['displayMigration'] = true;
        // the refresh POST is CSRF-exempt and sessionless (the front end sends no token to /api/*; else 419 for wallet
        // users without a site session)
        $req = \Illuminate\Http\Request::create('/api/islands/10/refresh', 'POST');
        $this->assertTrue(\App\Support\PublicCache::isStateless($req));
        $csrf = new \ReflectionProperty(\App\Http\Middleware\VerifyCsrfToken::class, 'except');
        $this->assertContains('api/islands/*/refresh', $csrf->getDefaultValue());
        // contract v2 (no set_layer): no layer tools
        $chain->cfg['contractVersion'] = 2;
        $j = $this->getJson('/api/islands/10')->assertOk()->json();
        $this->assertSame([], $j['settableLayers']);
        $this->assertFalse($j['canChooseDisplay']);
        $chain->cfg['contractVersion'] = 3;
        // config lists the parsed screen of display templates
        $tpl = collect($this->getJson('/api/islands/config')->assertOk()->json('templates'))->keyBy('id');
        $this->assertEquals(['x' => 100, 'y' => 200, 'w' => 300, 'h' => 150, 'quad' => []], $tpl[50]['screen']);
        $this->assertNull($tpl[51]['screen']);

        // events: IslandLayerChanged (set_layer) updates one layer; null removes it; it re-renders
        $p = new IslandEventProcessor();
        $a = 'A.2c6a4c4342aeca55.FlovatarAsteroidIsland.';
        $ev = fn (string $type, array $m) => new Event(['type' => $type, 'flow_metadata' => $m, 'transaction_id' => 'tx', 'block_id' => 1, 'event_date' => now()]);
        $e = $ev($a.'IslandLayerChanged', ['id' => 10, 'layer' => 'display', 'templateId' => 50, 'previousTemplateId' => null, 'migration' => true, 'owner' => self::OWNER]);
        $this->assertContains($a.'IslandLayerChanged', FlowEventTypes::islandTypes());
        $this->assertTrue($p->handles($e));
        $p->process($e);
        $this->assertSame(['base' => 1, 'display' => 50], Island::where('flow_id', 10)->first()->layers);
        $p->process($ev($a.'IslandLayerChanged', ['id' => 10, 'layer' => 'sky', 'templateId' => 51, 'previousTemplateId' => null, 'migration' => false, 'owner' => self::OWNER]));
        $p->process($ev($a.'IslandLayerChanged', ['id' => 10, 'layer' => 'sky', 'templateId' => null, 'previousTemplateId' => 51, 'migration' => false, 'owner' => self::OWNER]));
        $this->assertSame(['base' => 1, 'display' => 50], Island::where('flow_id', 10)->first()->layers);
        Queue::assertPushed(\App\Jobs\RefreshIsland::class, fn ($job) => $job->flowId === 10 && $job->render);

        // RefreshIsland::apply stores the residents' Display views
        \App\Jobs\RefreshIsland::apply(Island::where('flow_id', 10)->first(), ['rarity' => 'common', 'layers' => ['base' => 1, 'display' => 50], 'deedIds' => [1],
            'residents' => [['slot' => 'S1', 'nftType' => 'A.x.Y.NFT', 'nftId' => 3, 'setBy' => self::OWNER, 'valid' => true, 'reason' => '']],
            'displays' => ['S1' => ['name' => 'Thing #3', 'thumbnail' => 'https://example.com/3.png']]]);
        $row = IslandResident::where('island_flow_id', 10)->where('slot', 'S1')->first();
        $this->assertSame(['Thing #3', 'https://example.com/3.png'], [$row->name, $row->thumbnail]);
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
