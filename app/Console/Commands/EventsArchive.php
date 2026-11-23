<?php

namespace App\Console\Commands;

use App\Services\Flow\FlowEventTypes;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Archive old, already-processed rows of the raw indexer log (`events`) to gzipped JSONL.
 *
 * Default is a DRY RUN: it only reports what would be archived and the safety facts
 * (unsynced rows left alone, DUST sums the counters depend on, cutoff vs indexer cursors).
 * --export writes storage/app/events-archive/<cutoff>/events-YYYY-MM.jsonl.gz + manifest.json
 * (row counts, id ranges, sha256, DUST sums). Upload them to R2 (archive/events/) from a host
 * with R2 access. This command NEVER deletes rows; pruning is a separate, reviewed step
 * (see notes/server-flovatar.md "Performance 2026-11-23").
 *
 * Candidates: synced = 1 AND block_id < cutoff block (first block on/after now - keep-days).
 */
class EventsArchive extends Command
{
    protected $signature = 'flovatar:events-archive
        {--keep-days=90 : keep events newer than this many days}
        {--export : write the gz files + manifest (no deletes ever)}
        {--chunk=5000 : rows per read}';

    protected $description = 'Dry-run / export old processed events to gzipped JSONL for the R2 archive (never deletes)';

    public function handle(): int
    {
        $keepDays = max(30, (int) $this->option('keep-days'));
        $cutoffDate = Carbon::now()->subDays($keepDays)->startOfDay();
        $cutoffBlock = (int) DB::table('events')->where('event_date', '>=', $cutoffDate)->min('block_id');
        if ($cutoffBlock <= 0) {
            $this->error('No events newer than the cutoff: nothing would be kept, refusing.');

            return self::FAILURE;
        }

        $base = DB::table('events')->where('block_id', '<', $cutoffBlock);
        $candidates = (clone $base)->where('synced', true);

        $total = (int) DB::table('events')->count();
        $count = (int) (clone $candidates)->count();
        $unsyncedOld = (int) (clone $base)->where('synced', false)->count();
        $range = (clone $candidates)->selectRaw('min(id) a, max(id) b, min(block_id) c, max(block_id) d, sum(length(flow_metadata)) bytes')->first();

        $a = FlowEventTypes::contractAddress();
        $claimTypes = ["A.$a.FlovatarInbox.FlovatarWithdrawDust", "A.$a.FlovatarInbox.WalletWithdrawDust", "A.$a.FlovatarInbox.FlovatarClaimedCommunityDust"];
        $sum = fn ($q, array $types) => (float) (clone $q)->whereIn('type', $types)
            ->sum(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(flow_metadata, '$.amount')) AS DECIMAL(30,8))"));
        $dust = [
            'claimed_archived' => $sum($candidates, $claimTypes),
            'burned_archived' => $sum($candidates, ["A.$a.FlovatarDustToken.TokensBurned"]),
            'claimed_all' => $sum(DB::table('events'), $claimTypes),
            'burned_all' => $sum(DB::table('events'), ["A.$a.FlovatarDustToken.TokensBurned"]),
        ];
        $cursors = DB::table('syncs')->get()->map(fn ($r) => (array) $r)->all();
        $byYear = (clone $candidates)->selectRaw("coalesce(year(event_date), 'none') y, coalesce(source, if(graffle, 'graffle', 'legacy')) s, count(*) n")
            ->groupBy('y', 's')->orderBy('y')->get();

        $this->info(($this->option('export') ? 'EXPORT' : 'DRY RUN').": keep {$keepDays} days, cutoff ".$cutoffDate->toDateString()." = block {$cutoffBlock}");
        $this->line("events total {$total}; archive candidates (synced, block < cutoff) {$count}; would remain ".($total - $count));
        $this->line("unsynced rows below cutoff (NEVER archived, needed by flow:process / gave-up review): {$unsyncedOld}");
        $this->line("candidate id range {$range->a}..{$range->b}, blocks {$range->c}..{$range->d}, flow_metadata ~".round(($range->bytes ?? 0) / 1048576)." MB");
        foreach ($byYear as $r) {
            $this->line("  {$r->y} {$r->s}: {$r->n}");
        }
        $this->line('DUST sums (RecomputeDustCounters reads the whole table): '.json_encode($dust));
        $this->line('indexer cursors (syncs): '.json_encode($cursors));

        if (! $this->option('export')) {
            $this->comment('Dry run only. Nothing written, nothing deleted.');

            return self::SUCCESS;
        }

        $dir = 'events-archive/'.$cutoffDate->format('Ymd');
        Storage::makeDirectory($dir);
        $files = [];
        $handles = [];
        $chunk = max(500, (int) $this->option('chunk'));
        $lastId = 0;
        $written = 0;
        do {
            $rows = (clone $candidates)->where('id', '>', $lastId)->orderBy('id')->limit($chunk)->get();
            foreach ($rows as $row) {
                $month = $row->event_date ? substr($row->event_date, 0, 7) : 'undated';
                if (! isset($handles[$month])) {
                    $path = Storage::path("$dir/events-$month.jsonl.gz");
                    $handles[$month] = gzopen($path, 'wb6');
                    $files[$month] = ['file' => "events-$month.jsonl.gz", 'rows' => 0, 'min_id' => $row->id, 'max_id' => $row->id];
                }
                gzwrite($handles[$month], json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                $files[$month]['rows']++;
                $files[$month]['max_id'] = $row->id;
                $lastId = $row->id;
                $written++;
            }
            $this->output->write("\r{$written}/{$count}");
        } while ($rows->count() === $chunk);
        $this->newLine();
        foreach ($handles as $month => $h) {
            gzclose($h);
            $files[$month]['sha256'] = hash_file('sha256', Storage::path("$dir/".$files[$month]['file']));
        }
        Storage::put("$dir/manifest.json", json_encode([
            'created_at' => now()->toIso8601String(),
            'keep_days' => $keepDays,
            'cutoff_date' => $cutoffDate->toDateString(),
            'cutoff_block' => $cutoffBlock,
            'predicate' => 'synced = 1 AND block_id < cutoff_block',
            'rows' => $written,
            'dust' => $dust,
            'files' => array_values($files),
        ], JSON_PRETTY_PRINT));
        $this->info("wrote {$written} rows to storage/app/{$dir} (".count($files).' files). Nothing deleted.');

        return self::SUCCESS;
    }
}
