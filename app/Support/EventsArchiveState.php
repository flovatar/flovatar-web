<?php

namespace App\Support;

use App\Services\Flow\FlowEventTypes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * State of the `events` archive (table events_archive_baselines, written by
 * `flovatar:events-archive`).
 *
 * - watermark(): highest archived block. Events at or below it are never (re)inserted
 *   (EventIngestor, Event model, flow:catchup, flow:import-backfill) unless forced, so
 *   pruned rows can't come back and be applied twice.
 * - dustSums(): claimed/burned DUST = sum over the remaining events + the amounts of the
 *   pruned rows (kept per archive in the baseline table). Exact DECIMAL arithmetic in SQL.
 */
class EventsArchiveState
{
    const CACHE_KEY = 'events-archive-watermark-v1';

    public static function watermark(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, 300, function () {
            if (! Schema::hasTable('events_archive_baselines')) {
                return 0;
            }

            return (int) DB::table('events_archive_baselines')->max('watermark_block');
        });
    }

    public static function forgetWatermark(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function claimTypes(): array
    {
        $a = FlowEventTypes::contractAddress();

        return ["A.$a.FlovatarInbox.FlovatarWithdrawDust", "A.$a.FlovatarInbox.WalletWithdrawDust", "A.$a.FlovatarInbox.FlovatarClaimedCommunityDust"];
    }

    public static function burnTypes(): array
    {
        $a = FlowEventTypes::contractAddress();

        return ["A.$a.FlovatarDustToken.TokensBurned"];
    }

    /** SQL expression: DUST amount of an event row (DECIMAL). */
    public static function amountSql(string $table = 'events'): string
    {
        return "CAST(JSON_UNQUOTE(JSON_EXTRACT($table.flow_metadata, '$.amount')) AS DECIMAL(30,8))";
    }

    /**
     * DUST totals as exact decimal strings:
     * ['claimed' => events + pruned, 'burned' => ..., 'claimed_events', 'burned_events', 'claimed_pruned', 'burned_pruned'].
     */
    public static function dustSums(): array
    {
        $in = fn (array $types) => implode(',', array_map(fn ($t) => DB::getPdo()->quote($t), $types));
        $amount = static::amountSql();
        $pruned = fn (string $col) => Schema::hasTable('events_archive_baselines')
            ? "(SELECT COALESCE(SUM($col), 0) FROM events_archive_baselines)"
            : 'CAST(0 AS DECIMAL(30,8))';
        $sql = 'SELECT ce, be, cp, bp, ce + cp AS claimed, be + bp AS burned FROM (SELECT '
            .'(SELECT COALESCE(SUM('.$amount.'), 0) FROM events WHERE type IN ('.$in(static::claimTypes()).')) ce, '
            .'(SELECT COALESCE(SUM('.$amount.'), 0) FROM events WHERE type IN ('.$in(static::burnTypes()).')) be, '
            .$pruned('claimed_pruned').' cp, '
            .$pruned('burned_pruned').' bp) t';
        $r = DB::selectOne($sql);

        return [
            'claimed' => (string) $r->claimed,
            'burned' => (string) $r->burned,
            'claimed_events' => (string) $r->ce,
            'burned_events' => (string) $r->be,
            'claimed_pruned' => (string) $r->cp,
            'burned_pruned' => (string) $r->bp,
        ];
    }
}
