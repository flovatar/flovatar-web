<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPendingFlowEvents;
use App\Jobs\RefreshIsland;
use App\Jobs\RenderItemImages;
use App\Models\Island;
use App\Services\Flow\EventIngestor;
use App\Services\Flow\FlowEventTypes;
use App\Services\Flow\FlowRestClient;
use App\Services\Flow\IndexerCursor;
use App\Services\Islands\IslandChain;
use App\Support\Islands;
use Illuminate\Console\Command;
use Throwable;

/**
 * Asteroid Islands chain sync (ISLANDS_MODE stealth|on).
 *
 *   islands:sync                 templates (metadata + SVG) + chain config
 *   islands:sync --islands       + re-read every known island from its owner's collection (names, residents, validity)
 *   islands:sync --backfill      + scan island events (+ island NonFungibleToken.Deposited) from ISLANDS_START_HEIGHT
 *                                  up to the indexer's REST cursor (the indexer only follows new types from its cursor on)
 *   islands:sync --render        + queue static renders for every island
 */
class IslandsSync extends Command
{
    protected $signature = 'islands:sync {--islands} {--backfill} {--from= : backfill start height} {--render} {--force : refetch every template SVG}';

    protected $description = 'Sync Asteroid Islands templates/config (and optionally islands, events, renders) from chain';

    public function handle(IslandChain $chain, FlowRestClient $rest, EventIngestor $ingestor): int
    {
        if (! Islands::active()) {
            $this->warn('Asteroid Islands are off (ISLANDS_MODE / ISLANDS_CONTRACT_ADDRESS)');

            return self::SUCCESS;
        }

        $cfg = $chain->config(true);
        $this->info('config: totalSupply '.$cfg['totalSupply'].', slot model '.$cfg['slotModel'].', premium '.json_encode($cfg['premium']));
        $r = $chain->syncTemplates((bool) $this->option('force'));
        $this->info("templates: {$r['templates']} ({$r['fetched']} SVG fetched)");

        if ($this->option('backfill')) {
            $from = (int) ($this->option('from') ?: config('islands.start_height'));
            $to = IndexerCursor::get(IndexerCursor::REST) ?? $rest->sealedHeight();
            $types = array_merge(FlowEventTypes::islandTypes(), FlowEventTypes::standardTypes());
            $this->info("backfill $from → $to (".count($types).' types)');
            $total = 0;
            for ($start = $from; $start <= $to; $start += FlowRestClient::MAX_RANGE) {
                $end = min($start + FlowRestClient::MAX_RANGE - 1, $to);
                $rows = $rest->eventsForTypes($types, $start, $end);
                [$inserted] = $ingestor->store($rows, 'backfill');
                $total += $inserted;
                if ($inserted) {
                    $this->line("  $start-$end: +$inserted");
                }
            }
            $this->info("backfill: $total new events");
            if ($total) {
                ProcessPendingFlowEvents::processBatch(5000);
            }
        }

        if ($this->option('islands')) {
            foreach (Island::orderBy('flow_id')->get() as $island) {
                if (! $island->flow_address) {
                    continue;
                }
                try {
                    $data = $chain->island($island->flow_address, (int) $island->flow_id);
                    if ($data) {
                        RefreshIsland::apply($island, $data);
                        $this->line("island {$island->flow_id}: ok ({$island->residents_count} residents)");
                    } else {
                        $this->warn("island {$island->flow_id}: not in {$island->flow_address} (transfer pending?)");
                    }
                } catch (Throwable $e) {
                    $this->error("island {$island->flow_id}: ".substr($e->getMessage(), 0, 200));
                }
            }
        }

        if ($this->option('render')) {
            foreach (Island::pluck('flow_id') as $id) {
                RenderItemImages::dispatch('island', (int) $id, true);
            }
            $this->info('renders queued');
        }

        return self::SUCCESS;
    }
}
