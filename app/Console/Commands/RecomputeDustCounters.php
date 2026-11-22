<?php

namespace App\Console\Commands;

use App\Services\Flow\FlowEventTypes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds storage/app/claimed-dust.txt and burned-dust.txt (marketplace stats) from the
 * events table: sum of `amount` of FlovatarInbox claim/withdraw events and of
 * FlovatarDustToken.TokensBurned. Run after imports/backfills; live processing keeps
 * incrementing the files afterwards.
 */
class RecomputeDustCounters extends Command
{
    protected $signature = 'flovatar:recompute-dust-counters {--dry-run}';

    protected $description = 'Recompute claimed/burned DUST counters from the events table';

    public function handle(): int
    {
        $a = FlowEventTypes::contractAddress();
        $claimTypes = ["A.$a.FlovatarInbox.FlovatarWithdrawDust", "A.$a.FlovatarInbox.WalletWithdrawDust", "A.$a.FlovatarInbox.FlovatarClaimedCommunityDust"];
        $sum = fn (array $types) => (float) DB::table('events')->whereIn('type', $types)
            ->sum(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(flow_metadata, '$.amount')) AS DECIMAL(30,8))"));

        $claimed = $sum($claimTypes);
        $burned = $sum(["A.$a.FlovatarDustToken.TokensBurned"]);
        $this->info('claimed '.number_format($claimed, 8, '.', '').' (file: '.(Storage::exists('claimed-dust.txt') ? Storage::get('claimed-dust.txt') : '-').')');
        $this->info('burned  '.number_format($burned, 8, '.', '').' (file: '.(Storage::exists('burned-dust.txt') ? Storage::get('burned-dust.txt') : '-').')');

        if (! $this->option('dry-run')) {
            Cache::lock('dust-counter-claimed', 30)->block(30, fn () => Storage::put('claimed-dust.txt', $claimed));
            Cache::lock('dust-counter-burned', 30)->block(30, fn () => Storage::put('burned-dust.txt', $burned));
            Cache::forget('db-sales-stats');
            $this->info('written');
        }

        return self::SUCCESS;
    }
}
