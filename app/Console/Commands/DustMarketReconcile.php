<?php

namespace App\Console\Commands;

use App\Models\SaleOffer;
use App\Services\Market\DustStorefrontProcessor;
use App\Support\DustMarket;
use Illuminate\Console\Command;
use Throwable;

/**
 * ĐUST marketplace: re-check every open ĐUST offer on chain (one script per seller): the listing still exists, is not
 * purchased, the seller still holds the NFT (no ghost), it has not expired and it pays the Flovatar marketplace fee.
 * Dead rows are deleted, so ghost/expired listings disappear from the marketplace even when nobody cleans them up on chain.
 */
class DustMarketReconcile extends Command
{
    protected $signature = 'dust-market:reconcile {--dry-run}';

    protected $description = 'Delete ĐUST offers whose storefront listing is gone, sold, ghost, expired or fee-less';

    public function handle(): int
    {
        if (! DustMarket::active()) {
            return self::SUCCESS;
        }
        $p = new DustStorefrontProcessor();
        $offers = SaleOffer::where('currency', DustMarket::CURRENCY)->whereNotNull('listing_id')->get(['id', 'flow_address', 'listing_id', 'item_type', 'flow_id']);
        $dead = 0;
        foreach ($offers->groupBy('flow_address') as $seller => $rows) {
            foreach ($rows->chunk(100) as $chunk) {
                try {
                    $valid = $p->verifyMany((string) $seller, $chunk->pluck('listing_id')->all());
                } catch (Throwable $e) {
                    $this->warn("{$seller}: check failed ({$e->getMessage()})");

                    continue;
                }
                foreach ($chunk as $offer) {
                    if (! ($valid[(string) $offer->listing_id] ?? false)) {
                        $dead++;
                        $this->line("dead: {$offer->item_type} #{$offer->flow_id} listing {$offer->listing_id} ({$seller})");
                        if (! $this->option('dry-run')) {
                            SaleOffer::whereKey($offer->id)->delete();
                            $type = \App\Support\HeadlessRegen::MODEL_TYPES[$offer->item_type] ?? null;
                            $type ? \App\Support\HeadlessRegen::touch($type, (int) $offer->flow_id) : \App\Support\HeadlessRegen::touchComponentType((int) $offer->flow_id);
                        }
                    }
                }
            }
        }
        $this->info("ĐUST offers: {$offers->count()}, dead: {$dead}".($this->option('dry-run') ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
