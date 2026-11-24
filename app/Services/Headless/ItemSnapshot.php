<?php

namespace App\Services\Headless;

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
        $offer = SaleOffer::query()
            ->where('item_type', get_class($item))->where('item_id', $item->id)
            ->where('is_completed', false)->orderByDesc('created_at')
            ->first(['id', 'flow_id', 'flow_address', 'price', 'is_dapper', 'listing_id', 'created_at']);

        return $offer ? [
            'price' => (float) $offer->price,
            'currency' => $offer->is_dapper ? 'DUC' : 'FLOW',
            'is_dapper' => (bool) $offer->is_dapper,
            'seller' => $offer->flow_address,
            'listing_id' => $offer->listing_id,
            'listed_at' => optional($offer->created_at)->toIso8601String(),
        ] : null;
    }

    private static function sales(Model $item): array
    {
        return Sale::query()->notBlocked()
            ->where('item_type', get_class($item))->where('item_id', $item->id)
            ->orderBy('created_at')->limit(100)
            ->get(['price', 'is_dapper', 'flow_buyer_address', 'flow_seller_address', 'transaction_id', 'completed_at', 'created_at'])
            ->map(fn ($s) => [
                'price' => (float) $s->price,
                'currency' => $s->is_dapper ? 'DUC' : 'FLOW',
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
