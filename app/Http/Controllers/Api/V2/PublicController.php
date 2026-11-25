<?php

namespace App\Http\Controllers\Api\V2;

use App\Support\DustMarket;
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
        return $this->remember($request, 'marketplace', 10, fn () => $this->trimRows($this->withThumbs($this->getFilteredSales($request, true), true), 'sale'));
    }

    public function stats()
    {
        return $this->getStats();
    }

    public function transactions(Request $request)
    {
        return $this->remember($request, 'transactions', 10, fn () => $this->trimRows($this->withThumbs($this->getFilteredTransactions($request, true), true), 'transaction'));
    }

    public function showroomFlovatars(Request $request)
    {
        return $this->remember($request, 'showroom-flovatars', 30, fn () => $this->trimRows($this->withThumbs($this->getFilteredFlovatars($request, true), false, 'flovatar'), 'flovatar'));
    }

    public function showroomFlobots(Request $request)
    {
        return $this->remember($request, 'showroom-flobots', 30, fn () => $this->trimRows($this->withThumbs($this->getFilteredFlobots($request, true), false, 'flobot'), 'flobot'));
    }

    public function showroomCollectibles(Request $request)
    {
        return $this->remember($request, 'showroom-collectibles', 30, fn () => $this->trimRows($this->withThumbs($this->getFilteredCollectibles($request, true), false, 'collectible'), 'collectible'));
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

    /** Flobit type (template) page data: template, supply, open listings (floor + cheapest), sales stats. */
    public function template(int $flowId)
    {
        $data = Cache::flexible('apiv2:template:'.$flowId, [60, 600], fn () => ItemSnapshot::flobitType($flowId) ?? false);
        abort_unless($data, 404);

        return $data;
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
            $topSales = Sale::query()->notBlocked()->notDust()->with(['item' => $this->leanItemConstraints()])
                ->orderByDesc('price')->limit(25)->get(['id', 'item_id', 'item_type', 'price', 'is_dapper', 'flow_buyer_address', 'flow_seller_address', 'completed_at', 'created_at'])
                ->map(function ($s) {
                    $type = array_search($s->item_type, ItemSnapshot::TYPES, true) ?: null;

                    return ['type' => $type, 'id' => $s->item?->flow_id, 'name' => $s->item?->name ?? null, 'price' => (float) $s->price, 'currency' => DustMarket::currencyOf($s),
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

    /**
     * v2 list shape: only the fields the list templates read (same JSON paths as the legacy shape, so the shared
     * Alpine code renders both), no listing-event metadata, no svg, no timestamps nobody shows. The legacy endpoints
     * (POST /search, /showroom/search, ...) are untouched. Measured on prod data: docs/HEADLESS.md §6.
     */
    private function trimRows($paginator, string $kind)
    {
        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => match ($kind) {
            'sale' => $this->trimSale($row),
            'transaction' => $this->trimTransaction($row),
            'flovatar' => $this->only($row, ['id', 'name', 'flow_id', 'flow_address', 'rare_count', 'epic_count', 'legendary_count', 'thumb', 'list_thumb'])
                + ['flow_metadata' => $this->rarityMeta($row->flow_metadata), 'sale_offers' => $this->offers($row)],
            'flobot' => $this->only($row, ['id', 'name', 'flow_id', 'flow_address', 'rarity', 'thumb', 'list_thumb'])
                + ['flow_metadata' => $this->flobotMeta($row->flow_metadata), 'sale_offers' => $this->offers($row)],
            'collectible' => $this->only($row, ['id', 'name', 'mint', 'flow_id', 'flow_address', 'dust_series_id', 'rare_count', 'epic_count', 'legendary_count', 'rarity_score', 'price', 'thumb', 'list_thumb'])
                + ['sale_offers' => $this->offers($row)],
        }));

        return $paginator;
    }

    private function trimSale($row): array
    {
        return $this->only($row, ['id', 'item_type', 'flow_id', 'flow_address', 'price', 'is_dapper', 'listing_id', 'created_at', 'thumb', 'list_thumb'])
            + $this->currencyField($row) + ['item' => $this->trimItem($row->item)];
    }

    private function trimTransaction($row): array
    {
        return $this->only($row, ['id', 'item_type', 'price', 'is_dapper', 'transaction_id', 'flow_buyer_address', 'flow_seller_address', 'completed_at', 'created_at', 'thumb', 'list_thumb'])
            + $this->currencyField($row) + ['item' => $this->trimItem($row->item)];
    }

    /** ĐUST marketplace: `currency` (FLOW | DUC | DUST) in list rows, only while DUST_MARKET_ENABLED (prod JSON unchanged). */
    private function currencyField($row): array
    {
        return DustMarket::enabled() ? ['currency' => DustMarket::currencyOf($row)] : [];
    }

    /** The sold/listed item: what the cards read (name, ids, rarity counts / template id, mint). */
    private function trimItem($item): ?array
    {
        if (! $item) {
            return null;
        }
        $out = $this->only($item, ['id', 'flow_id', 'flow_address', 'name', 'flovatar_template_id', 'mint', 'rarity', 'rare_count', 'epic_count', 'legendary_count']);
        if ($item instanceof Flovatar) {
            $out['flow_metadata'] = $this->rarityMeta($item->flow_metadata);
        } elseif ($item instanceof \App\Models\Flobot) {
            $out['flow_metadata'] = $this->flobotMeta($item->flow_metadata);
        }

        return $out;
    }

    private function only($model, array $keys): array
    {
        $a = $model->toArray();

        return array_intersect_key($a, array_flip($keys));
    }

    /** flow_metadata as array (models cast it AsCollection; selectRaw'd JSON arrives as string). */
    private function metaArray($meta): ?array
    {
        if ($meta instanceof \Illuminate\Support\Collection) {
            return $meta->toArray();
        }
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        return is_array($meta) ? $meta : null;
    }

    /** Flovatar flow_metadata -> {metadata: {rareCount, epicCount, legendaryCount}} (rarity text/score/badges). */
    private function rarityMeta($meta): ?array
    {
        $meta = $this->metaArray($meta);
        $m = is_array($meta) ? ($meta['metadata'] ?? null) : null;
        if (! is_array($m)) {
            return null;
        }

        return ['metadata' => array_intersect_key($m, array_flip(['rareCount', 'epicCount', 'legendaryCount', 'series', 'mint']))];
    }

    private function flobotMeta($meta): ?array
    {
        $meta = $this->metaArray($meta);
        if (! is_array($meta)) {
            return null;
        }
        $m = $meta['metadata'] ?? [];

        return array_filter(['rarity' => $meta['rarity'] ?? null, 'metadata' => is_array($m) ? array_intersect_key($m, array_flip(['rarity', 'series', 'mint'])) : null], fn ($v) => $v !== null);
    }

    /** Open offers of a showroom row: only price + currency (the card shows the first price). */
    private function offers($row): array
    {
        if (! $row->relationLoaded('saleOffers')) {
            return [];
        }

        $offers = DustMarket::enabled() ? $row->saleOffers : $row->saleOffers->filter(fn ($o) => DustMarket::currencyOf($o) !== DustMarket::CURRENCY);

        return $offers->map(fn ($o) => ['price' => (float) $o->price, 'is_dapper' => (bool) $o->is_dapper] + $this->currencyField($o))->values()->all();
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
