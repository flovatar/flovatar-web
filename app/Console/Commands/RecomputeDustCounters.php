<?php

namespace App\Console\Commands;

use App\Support\EventsArchiveState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds storage/app/claimed-dust.txt and burned-dust.txt (marketplace stats) from the
 * events table: sum of `amount` of FlovatarInbox claim/withdraw events and of
 * FlovatarDustToken.TokensBurned, PLUS the DUST of rows pruned by
 * `flovatar:events-archive --prune` (events_archive_baselines.claimed_pruned/burned_pruned,
 * updated in the same transaction as each delete batch), so the totals are the same before,
 * during and after pruning. Run after imports/backfills; live processing keeps incrementing
 * the files afterwards.
 */
class RecomputeDustCounters extends Command
{
    protected $signature = 'flovatar:recompute-dust-counters {--dry-run}';

    protected $description = 'Recompute claimed/burned DUST counters from the events table + the pruned-events baseline';

    public function handle(): int
    {
        $s = EventsArchiveState::dustSums();
        $claimed = (float) $s['claimed'];
        $burned = (float) $s['burned'];
        $this->info('claimed '.number_format($claimed, 8, '.', '').' (file: '.(Storage::exists('claimed-dust.txt') ? Storage::get('claimed-dust.txt') : '-').')');
        $this->info('burned  '.number_format($burned, 8, '.', '').' (file: '.(Storage::exists('burned-dust.txt') ? Storage::get('burned-dust.txt') : '-').')');
        $this->line("  exact: claimed {$s['claimed']} = events {$s['claimed_events']} + pruned {$s['claimed_pruned']}; burned {$s['burned']} = events {$s['burned_events']} + pruned {$s['burned_pruned']}");

        if (! $this->option('dry-run')) {
            Cache::lock('dust-counter-claimed', 30)->block(30, fn () => Storage::put('claimed-dust.txt', $claimed));
            Cache::lock('dust-counter-burned', 30)->block(30, fn () => Storage::put('burned-dust.txt', $burned));
            Cache::forget('db-sales-stats');
            $this->info('written');
        }

        return self::SUCCESS;
    }
}
