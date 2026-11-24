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
            }
        };
        SaleOffer::saved($byListing);
        SaleOffer::deleted($byListing);
        Sale::created($byListing);
    }
}
