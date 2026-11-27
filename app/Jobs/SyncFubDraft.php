<?php

namespace App\Jobs;

use App\Models\FubDraft;
use App\Services\Fub\DraftSync;
use App\Services\Fub\FubIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Push a committed draft to the chain (operator txs). Not ShouldBeUnique (a dispatch while one runs would be dropped):
 * WithoutOverlapping per draft + a revision check at the end; if the draft changed meanwhile, it re-dispatches itself.
 */
class SyncFubDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct(public int $draftId)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('fub-draft-'.$this->draftId))->releaseAfter(20)->expireAfter(960)];
    }

    public function handle(DraftSync $sync, FubIndexer $indexer): void
    {
        $d = FubDraft::find($this->draftId);
        if (! $d || ! $d->chain_fub_id || $d->status === 'abandoned') {
            return;
        }
        $rev = $d->revision;
        $d->update(['sync_state' => 'running', 'sync_error' => null]);
        try {
            $r = $sync->sync($d->fresh());
            if ($r['more']) { // bounded chunks: continue in a fresh job (keeps the shared worker responsive)
                self::dispatch($d->id);

                return;
            }
            $indexer->refreshFub((int) $d->chain_fub_id);
        } catch (Throwable $e) {
            report($e);
            $d->update(['sync_state' => 'error', 'sync_error' => substr($e->getMessage(), 0, 1000)]);

            return;
        }
        $d->refresh();
        if ($d->revision !== $rev) {
            $d->update(['sync_state' => 'pending']);
            self::dispatch($d->id)->delay(now()->addSeconds(2));

            return;
        }
        $d->update(['sync_state' => 'idle', 'synced_at' => now()]);
    }
}
