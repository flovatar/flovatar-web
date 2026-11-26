<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshIsland;
use App\Jobs\RenderItemImages;
use App\Models\Island;
use App\Models\IslandTemplate;
use App\Services\Islands\IslandBelt;
use App\Services\Islands\IslandChain;
use App\Services\Islands\IslandScene;
use App\Support\ItemImages;
use App\Support\Islands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Asteroid Islands pages + JSON (KB notes/islands-build.md). Pages are user-agnostic shells (edge-cacheable); wallet
 * data (deeds, packs, own NFTs) is read in the browser with FCL (resources/js/app/islands.js).
 */
class IslandsController extends Controller
{
    public function __construct(protected IslandChain $chain, protected IslandScene $scene)
    {
    }

    protected function guard(): void
    {
        abort_unless(Islands::enabled(), 404);
    }

    // ------------------------------------------------------------------------------------------------ pages

    /**
     * /islands: the WebGL belt (data from /api/islands/belt) with the island list as fallback. The list is also
     * rendered here, paginated, so every island page is linked from plain HTML (no-JS visitors, crawlers).
     */
    public function index(Request $request)
    {
        $this->guard();
        $counts = ['common' => 0, 'epic' => 0, 'legendary' => 0];
        foreach (Island::query()->selectRaw('rarity, count(*) as n')->groupBy('rarity')->pluck('n', 'rarity') as $r => $n) {
            $counts[$r] = (int) $n;
        }
        $islands = Island::query()->orderBy('flow_id')
            ->paginate(120, ['flow_id', 'rarity', 'name', 'flow_address', 'residents_count', 'fused', 'tint'])->withQueryString();
        $latest = Island::query()->max('flow_id');

        return view('islands.index', [
            'islands' => $islands, 'counts' => $counts, 'total' => array_sum($counts),
            'ogImage' => ($latest ? ItemImages::url('island', $latest, 'og.jpg', false) : null) ?? 'https://flovatar.com/images/share.png',
        ]);
    }

    public function build()
    {
        $this->guard();

        return response()->view('islands.build')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function show(int $id)
    {
        $this->guard();
        $island = Island::where('flow_id', $id)->first();
        if (! $island) {
            // just terraformed and not indexed yet: the page still loads and reads the chain (?owner=)
            return response()->view('islands.show', ['island' => null, 'id' => $id, 'og' => null], 200)
                ->header('Cache-Control', 'no-store');
        }

        return view('islands.show', ['island' => $island, 'id' => $id, 'og' => ItemImages::og('island', $id)]);
    }

    public function fullSvg(int $id)
    {
        $this->guard();
        $island = Island::where('flow_id', $id)->firstOrFail();
        abort_unless($island->layers, 404);
        $hash = $this->scene->fullHash($island);
        $svg = Cache::remember('islands:full:'.$id.':'.$hash, 3600, fn () => $this->scene->full($island, false));

        return response(IslandScene::displayFix($svg), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=60, s-maxage=60',
            'ETag' => '"'.$hash.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
        ]);
    }

    public function sceneSvg(int $id)
    {
        $this->guard();
        $island = Island::where('flow_id', $id)->firstOrFail();
        abort_unless($island->layers, 404);

        return response(IslandScene::displayFix($this->scene->scene((array) $island->layers, $island->tint, (int) $island->flow_id)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'",
        ]);
    }

    // ------------------------------------------------------------------------------------------------ JSON

    /** Chain config + templates (no SVG): everything the builder and the resident manager need. */
    public function apiConfig()
    {
        $this->guard();
        try {
            $cfg = $this->chain->config();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'chain-unavailable'], 503)->header('Cache-Control', 'no-store');
        }
        $templates = Cache::remember('islands:templates:list', 300, fn () => IslandTemplate::orderBy('flow_id')->get()
            ->map(fn ($t) => [
                'id' => (int) $t->flow_id, 'layer' => $t->layer, 'rarity' => $t->rarity, 'name' => $t->name,
                'description' => $t->description, 'enabled' => $t->enabled, 'svgLength' => (int) $t->svg_length,
                'svgFrontLength' => (int) $t->svg_front_length, 'hash' => substr((string) $t->svg_hash, 0, 12),
                'anchors' => (object) IslandScene::parseAnchors((array) $t->metadata), 'metadata' => (object) (array) $t->metadata,
            ])->values()->all());
        $cfg['templates'] = $templates;
        $cfg['tierScale'] = config('islands.tier_scale');
        $cfg['families'] = config('islands.families');
        $cfg['defaultAnchors'] = array_map(fn ($r) => IslandScene::defaultAnchors($r, (int) ($cfg['premium'][$r] ?? 0)),
            array_combine(Islands::RARITIES, Islands::RARITIES));

        return response()->json($cfg)->header('Cache-Control', 'public, max-age=30, s-maxage=60');
    }

    public function apiTemplate(int $id)
    {
        $this->guard();
        $t = IslandTemplate::where('flow_id', $id)->firstOrFail();

        return response()->json([
            'id' => (int) $t->flow_id, 'layer' => $t->layer, 'rarity' => $t->rarity, 'enabled' => $t->enabled,
            'svg' => (string) $t->svg, 'svgFront' => (string) $t->svg_front,
            'svgLength' => (int) $t->svg_length, 'svgFrontLength' => (int) $t->svg_front_length,
            'hash' => substr((string) $t->svg_hash, 0, 12),
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    public function apiList(Request $request)
    {
        $this->guard();
        $q = Island::query()->orderByDesc('flow_id');
        if ($owner = $request->query('owner')) {
            $owner = strtolower((string) $owner);
            abort_unless(preg_match('/^0x[0-9a-f]{16}$/', $owner), 422);
            $q->where('flow_address', $owner);
        }
        if (in_array($r = $request->query('rarity'), Islands::RARITIES, true)) {
            $q->where('rarity', $r);
        }
        $rows = $q->limit(500)->get();

        return response()->json(['islands' => $rows->map(fn ($i) => $this->card($i))->values()])
            ->header('Cache-Control', 'public, max-age=0, s-maxage=30');
    }

    public function apiShow(int $id)
    {
        $this->guard();
        $island = Island::where('flow_id', $id)->firstOrFail();

        return response()->json($this->detail($island))->header('Cache-Control', 'public, max-age=0, s-maxage=15');
    }

    // ------------------------------------------------------------------------------------------------ WebGL belt

    /** Everything the belt scene needs in one cached response: compact rows for every island + atlas page URLs. */
    public function apiBelt(IslandBelt $belt)
    {
        $this->guard();

        return response()->json($belt->manifest())->header('Cache-Control', 'public, max-age=0, s-maxage=30');
    }

    /** Far-LOD texture page (content-hashed name -> immutable). */
    public function beltAtlas(IslandBelt $belt, string $page, string $hash)
    {
        $this->guard();
        $file = $belt->atlasFile((int) $page, $hash);
        abort_unless($file, 404);

        return $this->beltImage($file);
    }

    /** Near-LOD sprite of one island for its current render (key = render hash -> immutable). */
    public function beltSprite(IslandBelt $belt, string $id, string $key)
    {
        $this->guard();
        $file = $belt->spriteFile((int) $id, $key);
        abort_unless($file, 404);

        return $this->beltImage($file);
    }

    protected function beltImage(string $file)
    {
        return response()->file($file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Re-read one island from chain now (after a signed tx), so the page doesn't wait for the indexer. */
    public function apiRefresh(Request $request, int $id)
    {
        $this->guard();
        $island = Island::where('flow_id', $id)->first();
        $owner = strtolower((string) $request->input('owner', ''));
        $owner = preg_match('/^0x[0-9a-f]{16}$/', $owner) ? $owner : ($island->flow_address ?? null);
        abort_unless($owner, 404);
        try {
            $data = $this->chain->island($owner, $id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'chain-unavailable'], 503);
        }
        if (! $data) {
            return response()->json(['error' => 'not-found'], 404);
        }
        // the chain answer is the truth: the island IS in $owner's collection
        $island ??= new Island(['flow_id' => $id]);
        $island->flow_address = $owner;
        RefreshIsland::apply($island, $data);
        RenderItemImages::debounced('island', $id, 5);

        return response()->json($this->detail($island->fresh()))->header('Cache-Control', 'no-store');
    }

    // ------------------------------------------------------------------------------------------------ shapes

    protected function card(Island $i): array
    {
        return [
            'id' => (int) $i->flow_id, 'designation' => $i->designation, 'name' => $i->display_name, 'rarity' => $i->rarity,
            'owner' => $i->flow_address, 'residents' => (int) $i->residents_count, 'capacity' => Islands::capacity($i->rarity),
            'fused' => (bool) $i->fused, 'url' => '/islands/'.$i->flow_id,
            // transparent PNG (the JPG variants are flattened on white, wrong on the night-sky cards)
            'image' => ItemImages::url('island', $i->flow_id, 'full.png', false) ?? '/islands/'.$i->flow_id.'/full.svg',
        ];
    }

    protected function detail(Island $island): array
    {
        $cfg = $this->chain->config();
        $n = (int) ($cfg['premium'][$island->rarity] ?? 0);
        $model = (string) ($cfg['slotModel'] ?? 'ps');
        $residents = [];
        foreach ($island->residents()->get() as $r) {
            $logical = Islands::logicalSlot($island->rarity, $r->slot, $n, $model);
            $family = Islands::familyOf($r->nft_type);
            $residents[] = [
                'slot' => $logical, 'chainSlot' => $r->slot, 'premium' => Islands::isPremiumSlot($logical),
                'type' => $r->nft_type, 'id' => (int) $r->nft_id, 'family' => $family,
                'setBy' => $r->set_by, 'valid' => (bool) $r->valid && $r->set_by === $island->flow_address,
                'reason' => $r->set_by !== $island->flow_address ? 'island-owner-changed' : $r->reason,
            ];
        }
        $templates = $this->scene->templates(array_values((array) $island->layers));

        return $this->card($island) + [
            'description' => (string) $island->description, 'rawName' => (string) $island->name,
            'layers' => (array) $island->layers, 'tint' => $island->tint, 'deedIds' => (array) $island->deed_ids,
            'deedRarity' => $island->deed_rarity, 'terraformedAt' => optional($island->terraformed_at)->toIso8601String(),
            'tx' => $island->terraform_tx, 'premiumSlots' => $n, 'slotModel' => $model,
            'slots' => Islands::slots($island->rarity, $n, $model), 'residentsList' => $residents,
            'anchors' => $this->scene->anchors($island->rarity, (array) $island->layers, $templates),
            // layer => template name, in scene order (templates() is keyed by template id)
            'layerNames' => (object) collect((array) $island->layers)
                ->sortBy(fn ($tid, $layer) => array_search($layer, ['sky', 'base', 'structure', 'fub', 'foreground', 'fx'], true))
                ->map(fn ($tid) => isset($templates[$tid]) ? $templates[$tid]->name : '#'.$tid)->all(),
            'full' => '/islands/'.$island->flow_id.'/full.svg?h='.$this->scene->fullHash($island),
            'png' => ItemImages::url('island', $island->flow_id, 'full.png', false),
        ];
    }
}
