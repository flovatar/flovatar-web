<?php

namespace App\Jobs;

use App\Models\Island;
use App\Models\IslandResident;
use App\Services\Islands\IslandChain;
use App\Support\Islands;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Re-reads one island from chain (owner's collection) and makes the DB match: name, description, scene, residents with
 * LIVE validity (contract checkResidents: not-owned / island-owner-changed). Then queues the static render.
 * Idempotent and not unique (a burst of events = a few cheap script calls).
 */
class RefreshIsland implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $timeout = 120;

    public $backoff = [20, 120];

    public function __construct(public int $flowId, public bool $render = true)
    {
        $this->onQueue('default');
    }

    public static function debounced(int $flowId, int $delay = 5, bool $render = true): void
    {
        if (! Islands::active() || $flowId <= 0) {
            return;
        }
        static::dispatch($flowId, $render)->delay(now()->addSeconds($delay));
    }

    public function handle(IslandChain $chain): void
    {
        if (! Islands::active()) {
            return;
        }
        $island = Island::where('flow_id', $this->flowId)->first();
        if (! $island || ! $island->flow_address) {
            return;
        }
        $data = $chain->island($island->flow_address, $this->flowId);
        if (! $data) {
            // not (or no longer) in that collection: a later Deposited event fixes the owner
            return;
        }
        static::apply($island, $data);
        if ($this->render) {
            RenderItemImages::debounced('island', $this->flowId, 5);
        }
    }

    /** Apply a chain read (IslandChain::island) to the DB rows. */
    public static function apply(Island $island, array $data): void
    {
        DB::transaction(function () use ($island, $data) {
            $island->fill([
                'rarity' => (string) $data['rarity'],
                'name' => mb_substr((string) ($data['name'] ?? ''), 0, 64),
                'description' => (string) ($data['description'] ?? ''),
                'layers' => array_map('intval', (array) ($data['layers'] ?? [])),
                'tint' => $data['tint'] ?? null,
                'deed_ids' => array_map('intval', (array) ($data['deedIds'] ?? [])),
                'deed_rarity' => (string) ($data['deedRarity'] ?? ''),
                'fused' => count((array) ($data['deedIds'] ?? [])) > 1,
                'chain_synced_at' => now(),
            ]);
            if (! $island->terraformed_at && ! empty($data['terraformedAt'])) {
                $island->terraformed_at = \Carbon\Carbon::createFromTimestamp((int) $data['terraformedAt']);
            }
            $seen = [];
            foreach ((array) ($data['residents'] ?? []) as $r) {
                $slot = (string) $r['slot'];
                $seen[] = $slot;
                IslandResident::updateOrCreate(['island_flow_id' => $island->flow_id, 'slot' => $slot], [
                    'nft_type' => (string) $r['nftType'], 'nft_id' => (int) $r['nftId'], 'set_by' => strtolower((string) $r['setBy']),
                    'set_at' => ! empty($r['setAt']) ? \Carbon\Carbon::createFromTimestamp((int) $r['setAt']) : null,
                    'valid' => (bool) $r['valid'], 'reason' => ($r['reason'] ?? '') !== '' ? (string) $r['reason'] : null,
                ]);
            }
            IslandResident::where('island_flow_id', $island->flow_id)->whereNotIn('slot', $seen ?: ['__none'])->delete();
            $island->residents_count = count(array_filter($seen, fn ($s) => $s !== 'caretaker'));
            $island->save();
        });
    }
}
