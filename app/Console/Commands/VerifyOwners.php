<?php

namespace App\Console\Commands;

use App\Services\Flow\FlowEventTypes;
use Flow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Spot-checks NFT owners stored in the database against mainnet: for each sampled NFT,
 * runs the get_* Cadence script against the stored owner, which returns data only when
 * that account really holds the NFT. Samples NFTs touched by indexer events (most likely
 * to be wrong) plus random ones.
 */
class VerifyOwners extends Command
{
    protected $signature = 'flovatar:verify-owners {--sample=30 : NFTs per model} {--recent-only : only NFTs touched by indexer events}';

    protected $description = 'Compare stored NFT owners with mainnet for a sample of Flovatars, Flobits and collectibles';

    public function handle(): int
    {
        $a = FlowEventTypes::contractAddress();
        $models = [
            'flovatars' => ['./scripts/get_flovatar.cdc', "A.$a.Flovatar.Deposit"],
            'flovatar_components' => ['./scripts/get_component.cdc', "A.$a.FlovatarComponent.Deposit"],
            'dust_collectibles' => ['./scripts/get_collectible.cdc', "A.$a.FlovatarDustCollectible.Deposit"],
        ];
        $n = (int) $this->option('sample');
        $totals = ['ok' => 0, 'mismatch' => 0, 'no_owner' => 0];

        foreach ($models as $table => [$script, $depositType]) {
            $recentIds = DB::table('events')->where('type', $depositType)->whereIn('source', ['backfill', 'rest', 'stream'])
                ->selectRaw("DISTINCT CAST(JSON_EXTRACT(flow_metadata, '$.id') AS UNSIGNED) id")->pluck('id')->shuffle()->take($n);
            $ids = $recentIds;
            if (! $this->option('recent-only')) {
                $ids = $ids->merge(DB::table($table)->whereNotNull('flow_address')->inRandomOrder()->limit($n)->pluck('flow_id'));
            }
            $rows = DB::table($table)->whereIn('flow_id', $ids->unique()->values())->get(['flow_id', 'flow_address']);
            $ok = $bad = $none = 0;
            foreach ($rows as $row) {
                if (! $row->flow_address) {
                    // Components inside a Flovatar (equipped) or burned have no wallet owner.
                    $none++;

                    continue;
                }
                try {
                    $out = json_decode(Flow::script($script)->argAddress($row->flow_address)->argInt($row->flow_id)->run(), true);
                    $found = is_array($out) && ($out['value'] ?? null) !== null;
                } catch (\Throwable $e) {
                    $found = false;
                }
                if ($found) {
                    $ok++;
                } else {
                    $bad++;
                    $this->warn("  $table #{$row->flow_id}: not held by {$row->flow_address} on chain");
                }
            }
            $this->info("$table: checked ".($ok + $bad)." owners, $ok match, $bad mismatch, $none without wallet owner (equipped/burned)");
            $totals['ok'] += $ok;
            $totals['mismatch'] += $bad;
            $totals['no_owner'] += $none;
        }
        $this->line(json_encode($totals));

        return $totals['mismatch'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
