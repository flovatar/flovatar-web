<?php

namespace App\Console\Commands;

use App\Models\FubDraft;
use App\Models\FubToolCall;
use App\Services\Fub\FubIndexer;
use App\Support\Fub;
use Illuminate\Console\Command;

/**
 * fub:maintain — FUB v1 housekeeping (scheduler, hourly):
 *  - retention (Q22): drafts untouched for 90 days are deleted with their private originals; published drafts keep only
 *    what the chain has anyway (originals are dropped right after publish + 7 days);
 *  - safety net: re-read every FUB from the chain (--refresh), in case an event was missed.
 */
class FubMaintenance extends Command
{
    protected $signature = 'fub:maintain {--refresh : re-read every FUB from the chain} {--days=90}';

    protected $description = 'FUB drafts retention + chain refresh safety net';

    public function handle(FubIndexer $indexer): int
    {
        if (! Fub::active()) {
            $this->info('FUB inactive');

            return self::SUCCESS;
        }
        $cut = now()->subDays((int) $this->option('days'));
        $n = 0;
        FubDraft::whereIn('status', ['sketch', 'committed', 'abandoned'])->where('updated_at', '<', $cut)->each(function (FubDraft $d) use (&$n) {
            $d->items()->delete();
            $d->layers()->delete();
            FubToolCall::where('fub_draft_id', $d->id)->delete();
            $d->delete();
            $n++;
        });
        $o = 0;
        FubDraft::where('status', 'published')->where('published_at', '<', now()->subDays(7))->each(function (FubDraft $d) use (&$o) {
            $o += $d->items()->whereNotNull('svg_original')->update(['svg_original' => null]);
        });
        $this->info("deleted $n stale drafts, dropped $o published originals");
        if ($this->option('refresh')) {
            $this->info('refreshed '.$indexer->refreshAll().' FUBs');
        }

        return self::SUCCESS;
    }
}
