<?php

namespace App\Console\Commands;

use App\Models\PackRewindPack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan pack-rewind:import <file.json> [--dry-run]
 *
 * Loads the Pack Rewind stock (KB scripts/pack-rewind/export_import.py): one JSON object per re-minted testnet pack
 * {collection, series, mainnetId, testnetId, name, price, currency, contents: [{m, t}], source}. Idempotent upsert keyed by
 * (collection, testnet_id); sale state (status/reservation/sold) of existing rows is never touched.
 */
class PackRewindImport extends Command
{
    protected $signature = 'pack-rewind:import {file} {--dry-run}';

    protected $description = 'Import the Pack Rewind testnet pack stock (testnet only)';

    public function handle(): int
    {
        if ((string) config('services.flow.network') !== 'testnet') {
            $this->error('Pack Rewind is testnet only.');

            return 1;
        }
        $rows = json_decode((string) file_get_contents($this->argument('file')), true);
        if (! is_array($rows)) {
            $this->error('Invalid JSON');

            return 1;
        }
        $series = array_keys((array) config('pack_rewind.series'));
        $new = 0;
        $seen = 0;
        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            $payload = [];
            foreach ($chunk as $r) {
                if (! in_array($r['series'] ?? '', $series, true) || ! in_array($r['collection'] ?? '', ['flovatar', 'sportvatar'], true)) {
                    $this->error('Bad row: '.json_encode($r));

                    return 1;
                }
                $payload[] = [
                    'collection' => $r['collection'], 'series' => $r['series'], 'mainnet_id' => (int) $r['mainnetId'],
                    'testnet_id' => (int) $r['testnetId'], 'name' => (string) $r['name'], 'price' => (string) $r['price'],
                    'currency' => (string) ($r['currency'] ?? 'FLOW'), 'contents' => json_encode($r['contents'] ?? []),
                    'source' => substr((string) ($r['source'] ?? 'chain'), 0, 48), 'status' => 'available',
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            $seen += count($payload);
            if ($this->option('dry-run')) {
                continue;
            }
            $before = PackRewindPack::count();
            DB::table('pack_rewind_packs')->upsert($payload, ['collection', 'testnet_id'],
                ['series', 'mainnet_id', 'name', 'price', 'currency', 'contents', 'source', 'updated_at']);
            $new += PackRewindPack::count() - $before;
        }
        $this->info("rows in file: $seen, new: $new, total stock rows: ".PackRewindPack::count());
        foreach (PackRewindPack::selectRaw('series, status, count(*) n')->groupBy('series', 'status')->get() as $g) {
            $this->line("  {$g->series} {$g->status} {$g->n}");
        }

        return 0;
    }
}
