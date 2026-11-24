<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FilteredController;
use App\Models\DustTemplate;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Pack;
use App\Models\RenderedImage;
use App\Models\Sale;
use App\Models\SaleOffer;
use App\Services\Headless\ItemSnapshot;
use App\Support\Blocklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public, cacheable read API for the headless frontend (GET only; middleware PublicApiCache sets
 * Cache-Control: public, max-age=0, s-maxage=60, stale-while-revalidate=300 + CORS *).
 *
 * Only cross-wallet questions live here (search/filter/sort, history, stats, rankings); anything a Cadence
 * script can answer for one wallet/item is read in the browser via FCL (chain-first).
 * List endpoints reuse the existing lean queries (FilteredController) and keep the legacy response shape
 * (Laravel paginator JSON) so the existing Alpine components render them unchanged.
 * Every response is also memoised in Redis for a few seconds (Cache::flexible) to protect the origin when the
 * edge cache is cold or bypassed (workers.dev has no edge cache).
 */
class PublicController extends FilteredController
{
    private function remember(Request $request, string $name, int $fresh, callable $fn)
    {
        $params = $request->query();
        ksort($params);
        $key = 'apiv2:'.$name.':'.md5(json_encode($params));

        return Cache::flexible($key, [$fresh, $fresh * 10], $fn);
    }

    public function meta()
    {
        return [
            'api' => 2,
            'network' => config('services.flow.network'),
            'contracts' => ['flovatar' => config('services.flow.address')],
            'frontend_url' => config('headless.frontend_url'),
            'images_url' => config('render.public_url'),
            'templates' => collect(Controller::TEMPLATE_DATA_SETS)->mapWithKeys(fn ($set) => [
                $set => ['version' => Controller::templatesVersion($set), 'script' => route('templates.data', ['set' => $set], false).'?v='.Controller::templatesVersion($set)],
            ]),
        ];
    }

    /** Marketplace listings (same params as POST /search: type, rarity, sortby, minPrice, maxPrice, page, ...). */
    public function marketplace(Request $request)
    {
        return $this->remember($request, 'marketplace', 10, fn () => $this->withThumbs($this->getFilteredSales($request, true), true));
    }

    public function stats()
    {
        return $this->getStats();
    }

    public function transactions(Request $request)
    {
        return $this->remember($request, 'transactions', 10, fn () => $this->withThumbs($this->getFilteredTransactions($request, true), true));
    }

    public function showroomFlovatars(Request $request)
    {
        return $this->remember($request, 'showroom-flovatars', 30, fn () => $this->withThumbs($this->getFilteredFlovatars($request, true), false, 'flovatar'));
    }

    public function showroomFlobots(Request $request)
    {
        return $this->remember($request, 'showroom-flobots', 30, fn () => $this->withThumbs($this->getFilteredFlobots($request, true), false, 'flobot'));
    }

    public function showroomCollectibles(Request $request)
    {
        return $this->remember($request, 'showroom-collectibles', 30, fn () => $this->withThumbs($this->getFilteredCollectibles($request, true), false, 'collectible'));
    }

    /** Lean template lists (no svg): set=all|series1|flobot, or set=dust (DUST collectible templates). */
    public function templates(Request $request)
    {
        $set = (string) $request->query('set', 'all');
        if ($set === 'dust' || $set === 'dust2') {
            return $this->getDustTemplatesLean($set === 'dust2' ? 2 : 1);
        }
        abort_unless(in_array($set, ['all', 'series1', 'flobot'], true), 404);

        return $this->templatesLean($set);
    }

    /** Flobit type (template) page data: template, supply, open listings (floor), sales stats. */
    public function template(int $flowId)
    {
        return Cache::flexible('apiv2:template:'.$flowId, [60, 600], function () use ($flowId) {
            $tpl = FlovatarTemplate::query()->select(Controller::TEMPLATE_LEAN_COLUMNS)->where('flow_id', $flowId)->first();
            abort_unless($tpl, 404);
            $componentIds = FlovatarComponent::query()->where('flovatar_template_id', $tpl->id)->select('id');
            $minted = FlovatarComponent::query()->where('flovatar_template_id', $tpl->id)->count();
            $listings = SaleOffer::query()->notBlocked()->where('item_type', FlovatarComponent::class)
                ->where('is_completed', false)->whereIn('item_id', $componentIds)
                ->selectRaw('count(*) c, min(price) floor, is_dapper')->groupBy('is_dapper')->get();
            $sales = Sale::query()->notBlocked()->where('item_type', FlovatarComponent::class)->whereIn('item_id', $componentIds)
                ->selectRaw('count(*) c, avg(price) avg, max(price) max, max(created_at) last_at')->first();

            return [
                'template' => ItemSnapshot::template($tpl),
                'minted' => $minted,
                'listings' => $listings->map(fn ($l) => ['currency' => $l->is_dapper ? 'DUC' : 'FLOW', 'count' => (int) $l->c, 'floor' => (float) $l->floor])->values(),
                'sales' => ['count' => (int) $sales->c, 'avg' => $sales->avg !== null ? round((float) $sales->avg, 2) : null, 'max' => $sales->max !== null ? (float) $sales->max : null, 'last_at' => $sales->last_at],
            ];
        });
    }

    /** Pack supply (read-only; never secrets/signatures). A wallet's packs are read on chain. */
    public function packs()
    {
        return Cache::flexible('apiv2:packs', [300, 3600], function () {
            return Pack::query()->selectRaw('series, name, sum(opened = 1) opened, sum(opened = 0) unopened, count(*) total')
                ->whereNotNull('flow_id')->groupBy('series', 'name')->orderBy('series')->orderBy('name')->get()
                ->map(fn ($p) => ['series' => $p->series !== null ? (int) $p->series : null, 'name' => $p->name, 'opened' => (int) $p->opened, 'unopened' => (int) $p->unopened, 'total' => (int) $p->total])
                ->values();
        });
    }

    /** Rankings: top Flovatar holders, biggest sales, DUST/volume stats. */
    public function rankings()
    {
        return Cache::flexible('apiv2:rankings', [300, 3600], function () {
            $blocked = Blocklist::addresses();
            $holders = Flovatar::query()->selectRaw('flow_address, count(*) c, sum(legendary_count) legendary, sum(epic_count) epic, sum(rare_count) rare')
                ->whereNotNull('flow_address')->when($blocked, fn ($q) => $q->whereNotIn('flow_address', $blocked))
                ->groupBy('flow_address')->orderByDesc('c')->limit(25)->get()
                ->map(fn ($r) => ['address' => $r->flow_address, 'flovatars' => (int) $r->c, 'legendary' => (int) $r->legendary, 'epic' => (int) $r->epic, 'rare' => (int) $r->rare]);
            $topSales = Sale::query()->notBlocked()->with(['item' => $this->leanItemConstraints()])
                ->orderByDesc('price')->limit(25)->get(['id', 'item_id', 'item_type', 'price', 'is_dapper', 'flow_buyer_address', 'flow_seller_address', 'completed_at', 'created_at'])
                ->map(function ($s) {
                    $type = array_search($s->item_type, ItemSnapshot::TYPES, true) ?: null;

                    return ['type' => $type, 'id' => $s->item?->flow_id, 'name' => $s->item?->name ?? null, 'price' => (float) $s->price, 'currency' => $s->is_dapper ? 'DUC' : 'FLOW',
                        'buyer' => $s->flow_buyer_address, 'seller' => $s->flow_seller_address, 'at' => optional($s->completed_at ?? $s->created_at)->toIso8601String()];
                });

            return ['holders' => $holders, 'top_sales' => $topSales, 'stats' => $this->getStats()];
        });
    }

    /**
     * Adds `thumb` (best R2 render, immutable URL, or null) and `list_thumb` to each row. `list_thumb` is the URL list
     * templates show instead of the origin's /api/image SVG, set only when the JPEG looks the same on the dark list
     * cards: thumb.jpg is flattened on white, so transparent items (Flovatars/Flobots without a background, every DUST
     * collectible, Flobit templates other than backgrounds) and animated items (JPEG = frozen frame) keep the SVG.
     * Two small queries per page; the result is memoised with the page. $sales: rows are sale offers/sales with ->item.
     */
    private function withThumbs($paginator, bool $sales, ?string $type = null)
    {
        $rows = $paginator->getCollection();
        $wanted = [];
        foreach ($rows as $row) {
            [$t, $id] = $sales ? $this->rowItem($row) : [$type, $row->flow_id];
            if ($t && $id) {
                $wanted[$t][] = (int) $id;
            }
        }
        $thumbs = [];
        $listThumbs = [];
        foreach ($wanted as $t => $ids) {
            $renders = RenderedImage::query()->where('item_type', $t)->whereIn('flow_id', array_unique($ids))->whereNotNull('rendered_at')->get();
            $opaque = $this->opaqueItems($t, $renders->pluck('flow_id')->all());
            foreach ($renders as $r) {
                if ($url = $r->url('thumb.jpg')) {
                    $thumbs[$t.':'.$r->flow_id] = $url;
                    if (! $r->animated && isset($opaque[(int) $r->flow_id])) {
                        $listThumbs[$t.':'.$r->flow_id] = $url;
                    }
                }
            }
        }
        foreach ($rows as $row) {
            [$t, $id] = $sales ? $this->rowItem($row) : [$type, $row->flow_id];
            $row->setAttribute('thumb', $thumbs[$t.':'.$id] ?? null);
            $row->setAttribute('list_thumb', $listThumbs[$t.':'.$id] ?? null);
        }

        return $paginator;
    }

    /** flow_id => true for items whose image has an opaque background (see withThumbs). */
    private function opaqueItems(string $type, array $flowIds): array
    {
        if (! $flowIds) {
            return [];
        }
        $model = ['flovatar' => Flovatar::class, 'flobot' => \App\Models\Flobot::class][$type] ?? null;
        if ($model) {
            return $model::query()->whereIn('flow_id', $flowIds)
                ->selectRaw("flow_id, json_extract(flow_metadata, '$.backgroundId') bg")->get()
                ->filter(fn ($r) => ! in_array(trim((string) $r->bg, "\" "), ["", "null"], true)) // MySQL: JSON text ("570" | null), SQLite: 570 | NULL
                ->mapWithKeys(fn ($r) => [(int) $r->flow_id => true])->all();
        }
        if ($type === 'template') {
            return FlovatarTemplate::query()->whereIn('flow_id', $flowIds)->where('category', 'background')
                ->pluck('flow_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        }

        return []; // DUST collectibles have no background layer
    }

    private function rowItem($row): array
    {
        $map = [\App\Models\Flovatar::class => 'flovatar', \App\Models\Flobot::class => 'flobot', \App\Models\DustCollectible::class => 'collectible'];
        $type = $map[$row->item_type] ?? null;
        if ($type) {
            return [$type, $row->item?->flow_id];
        }
        if ($row->item_type === FlovatarComponent::class && $row->item) {
            static $tplFlowIds = null;
            $tplFlowIds ??= Cache::remember('apiv2:tpl-flow-ids', 300, fn () => FlovatarTemplate::query()->pluck('flow_id', 'id')->all());
            $tplFlowId = $tplFlowIds[$row->item->flovatar_template_id] ?? null;

            return ['template', $tplFlowId];
        }

        return [null, null];
    }
}
