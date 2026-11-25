<?php

namespace App\Services\Headless;

use App\Support\DustMarket;
use App\Models\DustCollectible;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\FlovatarComponent;
use App\Models\FlovatarTemplate;
use App\Models\Sale;
use App\Models\SaleOffer;
use App\Support\Blocklist;
use App\Support\Headless;
use App\Support\ItemImages;
use Illuminate\Database\Eloquent\Model;

/**
 * Item snapshots: the DB view of one NFT (owner, listing, sales, images) used by
 *  - GET /api/v2/{flovatars|flobots|collectibles|components}/{id} (cached 60 s at the edge), and
 *  - the per-item static pages (embedded as window.HEADLESS.item.snapshot at build time).
 * The browser treats the snapshot as a hint and confirms owner + listing on chain (FCL) - chain-first.
 * Never contains SVG blobs.
 */
class ItemSnapshot
{
    public const TYPES = [
        'flovatar' => Flovatar::class,
        'flobot' => Flobot::class,
        'collectible' => DustCollectible::class,
        'component' => FlovatarComponent::class,
    ];

    /** URL path segment per type (canonical item URL = /{segment}/{id}). */
    public const SEGMENTS = [
        'flovatar' => 'flovatars',
        'flobot' => 'flobots',
        'collectible' => 'collectibles',
        'component' => 'components',
    ];

    /** rendered_images type per item type (Flobit items show their template render). */
    private const IMAGE_TYPES = ['flovatar' => 'flovatar', 'flobot' => 'flobot', 'collectible' => 'collectible', 'component' => 'template'];

    public static function find(string $type, int $flowId): ?Model
    {
        $class = self::TYPES[$type] ?? null;
        if (! $class) {
            return null;
        }
        $columns = match ($type) {
            'flovatar' => ['id', 'name', 'series', 'flow_id', 'rare_count', 'epic_count', 'legendary_count', 'flow_address', 'created_at', 'updated_at'],
            'flobot' => ['id', 'name', 'series', 'flow_id', 'rarity', 'flow_address', 'created_at', 'updated_at'],
            'collectible' => ['id', 'mint', 'name', 'dust_series_id', 'rare_count', 'epic_count', 'legendary_count', 'rarity_score', 'flow_id', 'flow_address', 'created_at', 'updated_at'],
            'component' => ['id', 'flovatar_template_id', 'flow_id', 'flow_address', 'flow_metadata', 'mint', 'destroyed', 'packed', 'created_at', 'updated_at'],
        };
        $query = $class::query()->select($columns)->where('flow_id', $flowId);
        if ($type !== 'component') {
            $metaSvgPath = $type === 'flovatar' ? '$.metadata.svg' : '$.svg';
            $query->selectRaw('JSON_REMOVE(flow_metadata, ?) as flow_metadata', [$metaSvgPath]);
        }

        return $query->first();
    }

    public static function build(string $type, int $flowId): ?array
    {
        $item = self::find($type, $flowId);
        if (! $item) {
            return null;
        }
        $meta = self::meta($item->getAttribute('flow_metadata'));
        $owner = $item->flow_address ? strtolower($item->flow_address) : null;
        $imgType = self::IMAGE_TYPES[$type];
        $imgId = $type === 'component' ? (int) ($item->flovatar_template_id ? FlovatarTemplate::whereKey($item->flovatar_template_id)->value('flow_id') : 0) : $flowId;

        $out = [
            'type' => $type,
            'id' => $flowId,
            'name' => $item->name ?? ($meta['name'] ?? null) ?: null,
            'owner' => $owner,
            'creator' => $meta['metadata']['creatorAddress'] ?? ($meta['creatorAddress'] ?? null),
            'url' => Headless::frontendUrl(self::SEGMENTS[$type].'/'.$flowId),
            'flagged' => Blocklist::isFlagged(self::TYPES[$type], $flowId),
            'owner_blocked' => Blocklist::isBlocked($owner),
            'images' => self::images($imgType, $imgId),
            'listing' => self::listing($item),
            'sales' => self::sales($item),
            'updated_at' => optional($item->updated_at)->toIso8601String(),
        ];

        switch ($type) {
            case 'flovatar':
                $out['rarity'] = ['rare' => (int) $item->rare_count, 'epic' => (int) $item->epic_count, 'legendary' => (int) $item->legendary_count];
                $out['components'] = $meta['metadata']['components'] ?? null;
                $out['flobits'] = [
                    'accessory' => $meta['accessoryId'] ?? null, 'hat' => $meta['hatId'] ?? null,
                    'eyeglasses' => $meta['eyeglassesId'] ?? null, 'background' => $meta['backgroundId'] ?? null,
                ];
                break;
            case 'flobot':
                $out['rarity'] = $item->rarity;
                $out['components'] = $meta['metadata']['components'] ?? null;
                $out['flobits'] = ['background' => $meta['backgroundId'] ?? null];
                break;
            case 'collectible':
                $out['mint'] = (int) $item->mint;
                $out['series'] = (int) $item->dust_series_id;
                $out['rarity'] = ['rare' => (int) $item->rare_count, 'epic' => (int) $item->epic_count, 'legendary' => (int) $item->legendary_count, 'score' => (float) $item->rarity_score];
                break;
            case 'component':
                $tpl = $item->flovatar_template_id ? FlovatarTemplate::query()->select(\App\Http\Controllers\Controller::TEMPLATE_LEAN_COLUMNS)->find($item->flovatar_template_id) : null;
                $out['template'] = $tpl ? self::template($tpl) : null;
                $out['mint'] = (int) $item->mint;
                $out['destroyed'] = (bool) $item->destroyed;
                $out['packed'] = (bool) $item->packed;
                break;
        }

        return $out;
    }

    public static function template(FlovatarTemplate $tpl): array
    {
        return [
            'id' => (int) $tpl->flow_id,
            'db_id' => (int) $tpl->id,
            'name' => $tpl->name,
            'category' => $tpl->category,
            'color' => $tpl->color,
            'series' => (int) $tpl->series,
            'rarity' => $tpl->rarity,
            'description' => $tpl->description,
            'max_mintable' => (int) $tpl->max_mintable,
            'burned' => (int) $tpl->burned,
            'images' => self::images('template', (int) $tpl->flow_id),
        ];
    }

    /** Templates that get a Flobit TYPE page (/flobits/{flowId}): every template with at least one minted Flobit. */
    public static function flobitTypeIds(): array
    {
        return FlovatarTemplate::query()->whereNotNull('flow_id')
            ->whereIn('id', FlovatarComponent::query()->select('flovatar_template_id')->distinct())
            ->orderBy('flow_id')->pluck('flow_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Template flow id of a Flobit (component flow id), for regeneration of its type page. */
    public static function templateFlowIdOfComponent($componentFlowId): ?int
    {
        $tplId = FlovatarComponent::query()->where('flow_id', $componentFlowId)->value('flovatar_template_id');
        $flowId = $tplId ? FlovatarTemplate::query()->whereKey($tplId)->value('flow_id') : null;

        return $flowId !== null ? (int) $flowId : null;
    }

    /**
     * Flobit TYPE (template) data: template, supply, open listings per currency (count + floor), the cheapest open
     * listings (links to the item pages) and sales stats. Used by GET /api/v2/templates/{id} and the static type page.
     * Null when the template doesn't exist.
     */
    public static function flobitType(int $flowId, int $cheapest = 12): ?array
    {
        $tpl = FlovatarTemplate::query()->select(\App\Http\Controllers\Controller::TEMPLATE_LEAN_COLUMNS)->where('flow_id', $flowId)->first();
        if (! $tpl) {
            return null;
        }
        $componentIds = FlovatarComponent::query()->where('flovatar_template_id', $tpl->id)->select('id');
        $minted = FlovatarComponent::query()->where('flovatar_template_id', $tpl->id)->count();
        $open = fn () => SaleOffer::query()->notBlocked()->marketVisible()->where('item_type', FlovatarComponent::class)
            ->where('is_completed', false)->whereIn('item_id', $componentIds);
        $listings = $open()->selectRaw('count(*) c, min(price) floor, is_dapper, currency')->groupBy('is_dapper', 'currency')->get();
        $listed = $open()->with(['item' => fn ($q) => $q->select('id', 'flow_id', 'flow_address', 'mint')])
            ->orderBy('price')->limit($cheapest)->get(['id', 'item_id', 'item_type', 'flow_id', 'flow_address', 'price', 'is_dapper', 'currency']);
        $sales = Sale::query()->notBlocked()->notDust()->where('item_type', FlovatarComponent::class)->whereIn('item_id', $componentIds)
            ->selectRaw('count(*) c, avg(price) avg, max(price) max, max(created_at) last_at')->first();

        return [
            'template' => self::template($tpl),
            'url' => Headless::frontendUrl('flobits/'.$flowId),
            'minted' => $minted,
            'listings' => $listings->map(fn ($l) => ['currency' => DustMarket::currencyOf($l), 'count' => (int) $l->c, 'floor' => (float) $l->floor])->values()->all(),
            'listed' => $listed->map(fn ($o) => [
                'id' => (int) ($o->item?->flow_id ?? $o->flow_id), 'mint' => $o->item?->mint !== null ? (int) $o->item->mint : null,
                'owner' => $o->flow_address ?? $o->item?->flow_address, 'price' => (float) $o->price, 'currency' => DustMarket::currencyOf($o),
            ])->values()->all(),
            'sales' => ['count' => (int) $sales->c, 'avg' => $sales->avg !== null ? round((float) $sales->avg, 2) : null, 'max' => $sales->max !== null ? (float) $sales->max : null,
                'last_at' => $sales->last_at ? \Illuminate\Support\Carbon::parse($sales->last_at)->toIso8601String() : null],
        ];
    }

    public static function images(string $imgType, int $id): array
    {
        $og = ItemImages::og($imgType, $id);
        $base = '/api/image/r/'.$imgType.'/'.$id.'/';

        return [
            'og' => $og['url'],
            'full_png' => ItemImages::url($imgType, $id, 'full.png') ?? $base.'full.png',
            'thumb' => ItemImages::url($imgType, $id, 'thumb.jpg') ?? $base.'thumb.jpg',
            'downloads' => ItemImages::downloads($imgType, $id),
        ];
    }

    private static function listing(Model $item): ?array
    {
        $offer = SaleOffer::query()->marketVisible()
            ->where('item_type', get_class($item))->where('item_id', $item->id)
            ->where('is_completed', false)->orderByDesc('created_at')
            ->first(['id', 'flow_id', 'flow_address', 'price', 'is_dapper', 'currency', 'listing_id', 'created_at']);

        return $offer ? [
            'price' => (float) $offer->price,
            'currency' => DustMarket::currencyOf($offer),
            'is_dapper' => (bool) $offer->is_dapper,
            'seller' => $offer->flow_address,
            'listing_id' => $offer->listing_id,
            'listed_at' => optional($offer->created_at)->toIso8601String(),
        ] : null;
    }

    private static function sales(Model $item): array
    {
        return Sale::query()->notBlocked()->marketVisible()
            ->where('item_type', get_class($item))->where('item_id', $item->id)
            ->orderBy('created_at')->limit(100)
            ->get(['price', 'is_dapper', 'currency', 'flow_buyer_address', 'flow_seller_address', 'transaction_id', 'completed_at', 'created_at'])
            ->map(fn ($s) => [
                'price' => (float) $s->price,
                'currency' => DustMarket::currencyOf($s),
                'buyer' => $s->flow_buyer_address,
                'seller' => $s->flow_seller_address,
                'tx' => $s->transaction_id,
                'at' => optional($s->completed_at ?? $s->created_at)->toIso8601String(),
            ])->all();
    }

    private static function meta($value): array
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->toArray();
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }

        return [];
    }
}
