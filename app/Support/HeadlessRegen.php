<?php

namespace App\Support;

use App\Jobs\RegenerateStaticItem;
use App\Models\DustCollectible;
use App\Models\Flobot;
use App\Models\Flovatar;
use App\Models\Sale;
use App\Models\SaleOffer;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Indexer -> static page regeneration (Luca's per-item idea). Model observers call touch() whenever an
 * item row (mint/update/transfer) or one of its listings/sales changes, whoever wrote it (indexer jobs,
 * commands). touch() debounces: one delayed RegenerateStaticItem per item per `headless.debounce` seconds.
 * No-op unless HEADLESS_ENABLED=true.
 */
class HeadlessRegen
{
    public const MODEL_TYPES = [Flovatar::class => 'flovatar', Flobot::class => 'flobot', DustCollectible::class => 'collectible'];

    /** Flobit TYPE page of the template of a Flobit (component flow id); see StaticSiteBuilder::TYPE_PAGE. */
    public static function touchComponentType($componentFlowId): bool
    {
        if (! config('headless.enabled') || ! is_numeric($componentFlowId)) {
            return false;
        }
        try {
            $tpl = \App\Services\Headless\ItemSnapshot::templateFlowIdOfComponent((int) $componentFlowId);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return $tpl !== null && self::touch(\App\Services\Headless\StaticSiteBuilder::TYPE_PAGE, $tpl);
    }

    public static function key(string $type, int $flowId): string
    {
        return 'headless-regen:'.$type.':'.$flowId;
    }

    public static function touch(string $type, $flowId): bool
    {
        if (! config('headless.enabled') || ! is_numeric($flowId)) {
            return false;
        }
        $delay = max(1, (int) config('headless.debounce'));
        try {
            if (! Cache::add(self::key($type, (int) $flowId), 1, $delay + 300)) {
                return false; // a job is already pending for this item
            }
            RegenerateStaticItem::dispatch($type, (int) $flowId)->onQueue((string) config('headless.queue'))->delay(now()->addSeconds($delay));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Contract name in an event type -> static item type (A.{addr}.{Contract}.{Event}). */
    public const EVENT_CONTRACTS = ['Flovatar' => 'flovatar', 'Flobot' => 'flobot', 'FlovatarDustCollectible' => 'collectible'];

    /**
     * Indexer hook (ProcessGraffleEvents, after an event was applied): mint/update/deposit/withdraw of an item,
     * FLOW-marketplace listing events (FlovatarForSale/FlovatarSale*), NFTStorefrontV2 listing events (nftType + nftID).
     */
    public static function fromEvent(?string $type, $meta): void
    {
        if (! config('headless.enabled') || ! $type) {
            return;
        }
        $meta = $meta instanceof \Illuminate\Support\Collection ? $meta->toArray() : (array) $meta;
        $parts = explode('.', $type);
        $contract = $parts[2] ?? '';
        $event = $parts[3] ?? '';
        $itemType = null;
        $id = null;
        if (isset(self::EVENT_CONTRACTS[$contract])) {
            $itemType = self::EVENT_CONTRACTS[$contract];
            $id = $meta['id'] ?? $meta['tokenId'] ?? null;
        } elseif ($contract === 'FlovatarMarketplace' && str_starts_with($event, 'Flovatar') && ! str_contains($event, 'Component')) {
            $itemType = 'flovatar';
            $id = $meta['id'] ?? $meta['tokenId'] ?? null;
        } elseif ($contract === 'NFTStorefrontV2' && isset($meta['nftType'])) {
            $typeId = is_array($meta['nftType']) ? ($meta['nftType']['typeID'] ?? '') : (string) $meta['nftType'];
            foreach (self::EVENT_CONTRACTS as $name => $t) {
                if (str_ends_with($typeId, '.'.$name.'.NFT')) {
                    $itemType = $t;
                    $id = $meta['nftID'] ?? null;
                }
            }
        }
        if ($itemType && $id !== null) {
            self::touch($itemType, $id);
        }
        // Flobit TYPE pages: mint/burn (FlovatarComponent.*), FLOW-market component listings, Storefront component listings
        $componentId = null;
        if ($contract === 'FlovatarComponent') {
            $componentId = $meta['id'] ?? null;
        } elseif ($contract === 'FlovatarMarketplace' && str_contains($event, 'Component')) {
            $componentId = $meta['id'] ?? $meta['tokenId'] ?? null;
        } elseif ($contract === 'NFTStorefrontV2' && isset($meta['nftType'])) {
            $typeId = is_array($meta['nftType']) ? ($meta['nftType']['typeID'] ?? '') : (string) $meta['nftType'];
            if (str_ends_with($typeId, '.FlovatarComponent.NFT')) {
                $componentId = $meta['nftID'] ?? null;
            }
        }
        $typeRelevant = $contract !== 'FlovatarComponent' || in_array($event, ['Created', 'Burned', 'Destroyed'], true); // not plain transfers
        if ($componentId !== null && $typeRelevant) {
            isset($meta['templateId']) && is_numeric($meta['templateId']) // Created/Destroyed carry the template id
                ? self::touch(\App\Services\Headless\StaticSiteBuilder::TYPE_PAGE, (int) $meta['templateId'])
                : self::touchComponentType($componentId);
        }
    }

    public static function register(): void
    {
        foreach (self::MODEL_TYPES as $class => $type) {
            $class::saved(function ($item) use ($type) {
                if ($item->wasRecentlyCreated || $item->wasChanged(['flow_address', 'flow_metadata', 'name', 'rare_count', 'epic_count', 'legendary_count', 'rarity'])) {
                    self::touch($type, $item->flow_id);
                }
            });
            $class::deleted(fn ($item) => self::touch($type, $item->flow_id));
        }
        $byListing = function ($row) {
            $type = self::MODEL_TYPES[$row->item_type] ?? null;
            if ($type && $row->item_id) {
                $flowId = $row->item_type::query()->whereKey($row->item_id)->value('flow_id');
                self::touch($type, $flowId);
            } elseif ($row->item_type === \App\Models\FlovatarComponent::class && $row->item_id && config('headless.enabled')) {
                self::touchComponentType(\App\Models\FlovatarComponent::query()->whereKey($row->item_id)->value('flow_id'));
            }
        };
        // Flobit TYPE pages: mint (supply), burn/destroy; template rows (burned count, name, ...)
        \App\Models\FlovatarComponent::saved(function ($c) {
            if (config('headless.enabled') && ($c->wasRecentlyCreated || $c->wasChanged(['destroyed', 'flovatar_template_id']))) {
                self::touchComponentType($c->flow_id);
            }
        });
        \App\Models\FlovatarComponent::deleted(fn ($c) => self::touchComponentType($c->flow_id));
        \App\Models\FlovatarTemplate::saved(function ($t) {
            if ($t->flow_id !== null && $t->wasChanged(['burned', 'name', 'description', 'max_mintable', 'rarity'])) {
                self::touch(\App\Services\Headless\StaticSiteBuilder::TYPE_PAGE, $t->flow_id);
            }
        });
        SaleOffer::saved($byListing);
        SaleOffer::deleted($byListing);
        Sale::created($byListing);
    }
}
