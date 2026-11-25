<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Flow\FlowEventTypes;
use App\Services\Market\DustStorefrontProcessor;
use App\Support\DustMarket;
use Illuminate\Console\Command;

/**
 * ĐUST marketplace: replay the stored NFTStorefrontV2 events of Flovatar items paid in ĐUST (+ Listing.ResourceDestroyed)
 * through DustStorefrontProcessor in chain order. Use after switching DUST_MARKET_ENABLED on (the indexer only acknowledges
 * ĐUST events while it is off) or after `flow:catchup --from=…` re-fetched a range. Idempotent (upserts / firstOrCreate by tx).
 */
class DustMarketBackfill extends Command
{
    protected $signature = 'dust-market:backfill {--from= : first block height} {--dry-run : only count}';

    protected $description = 'Replay stored ĐUST storefront events into sale_offers / sales';

    public function handle(): int
    {
        if (! DustMarket::active() && ! $this->option('dry-run')) {
            $this->error('DUST_MARKET_ENABLED is off: nothing would be written (use --dry-run to count).');

            return self::FAILURE;
        }
        $types = FlowEventTypes::storefrontTypes();
        $q = Event::whereIn('type', $types)->orderBy('block_id')->orderBy('index');
        if ($this->option('from')) {
            $q->where('block_id', '>=', (int) $this->option('from'));
        }
        $p = new DustStorefrontProcessor();
        $seen = $applied = 0;
        $q->chunk(500, function ($events) use ($p, &$seen, &$applied) {
            foreach ($events as $event) {
                $vault = $event->flow_metadata['salePaymentVaultType'] ?? null;
                if (! DustMarket::isDustVault($vault)) {
                    continue;
                }
                $seen++;
                if ($this->option('dry-run')) {
                    continue;
                }
                if ($p->process($event)) {
                    $applied++;
                    if (! $event->synced) {
                        $event->update(['synced' => true]);
                    }
                }
            }
        });
        $this->info("ĐUST storefront events: {$seen}, applied {$applied}");

        return self::SUCCESS;
    }
}
