<?php

namespace App\Console\Commands;

use App\Support\EventsArchiveState;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Archive old, already-processed rows of the raw indexer log (`events`) to gzipped JSONL,
 * then (separately, after the files are verified on R2) prune exactly those rows.
 *
 * Modes:
 *  - default: DRY RUN. Reports candidates, unsynced rows left alone, DUST sums, cursors, baselines.
 *  - --export: inside ONE consistent snapshot writes storage/app/events-archive/<key>/
 *    events-YYYY-MM.jsonl.gz + ids.txt.gz (every archived id) + manifest.json (row counts,
 *    id ranges, sha256, DUST sums), and records a row in events_archive_baselines (cutoff,
 *    watermark = cutoff_block - 1, archived DUST sums, manifest sha256). From then on the
 *    indexer ignores events at or below the watermark (EventsArchiveState). Never deletes.
 *  - Upload the directory to R2 (archive/events/<key>/) from a host with R2 access, download
 *    it again, check sha256 + row counts, and drop the marker r2-verified.json into the
 *    directory: {"manifest_sha256", "r2_prefix", "verified_at", "files": {name: {sha256, rows}}}.
 *  - --prune --archive=<key>: refuses unless the marker matches the manifest and every local
 *    file still matches its sha256. Deletes ONLY ids listed in ids.txt.gz that still match
 *    synced = 1 AND block_id < cutoff_block AND id in [min_id, max_id], in batches (max 5,000)
 *    with short sleeps. Each batch adds its count and DUST sums to the baseline in the same
 *    transaction as the delete, so flovatar:recompute-dust-counters is unchanged throughout.
 *    Resumable (re-run continues; already deleted ids are no-ops).
 *
 * Candidates: synced = 1 AND block_id < cutoff block (first block on/after now - keep-days).
 */
class EventsArchive extends Command
{
    protected $signature = 'flovatar:events-archive
        {--keep-days=90 : keep events newer than this many days (min 30)}
        {--cutoff-block= : explicit cutoff block instead of keep-days (archive synced rows below it)}
        {--export : write the gz files + manifest + baseline row (no deletes)}
        {--prune : delete exactly the rows of a verified export (needs --archive and the R2 marker)}
        {--archive= : archive key = directory name under storage/app/events-archive (for --prune)}
        {--batch=5000 : rows per delete batch (max 5000)}
        {--sleep-ms=200 : pause between delete batches}
        {--chunk=5000 : rows per read when exporting}';

    protected $description = 'Dry-run / export old processed events to gzipped JSONL, or prune a verified export';

    const MARKER = 'r2-verified.json';

    public function handle(): int
    {
        if ($this->option('prune')) {
            return $this->prune();
        }

        $keepDays = max(30, (int) $this->option('keep-days'));
        if ($this->option('cutoff-block')) {
            $cutoffBlock = (int) $this->option('cutoff-block');
            $cutoffDate = Carbon::parse(DB::table('events')->where('block_id', '>=', $cutoffBlock)->min('event_date') ?? now())->startOfDay();
        } else {
            $cutoffDate = Carbon::now()->subDays($keepDays)->startOfDay();
            $cutoffBlock = (int) DB::table('events')->where('event_date', '>=', $cutoffDate)->min('block_id');
        }
        if ($cutoffBlock <= 0 || ! DB::table('events')->where('block_id', '>=', $cutoffBlock)->exists()) {
            $this->error('No events at/after the cutoff: nothing would be kept, refusing.');

            return self::FAILURE;
        }
        $key = $cutoffDate->format('Ymd');
        $dir = 'events-archive/'.$key;

        if ($this->option('export')) {
            if (DB::table('events_archive_baselines')->where('archive_key', $key)->exists() || Storage::exists("$dir/manifest.json")) {
                $this->error("Archive $key already exists (baseline row or $dir/manifest.json). Refusing to overwrite.");

                return self::FAILURE;
            }
            if ($open = DB::table('events_archive_baselines')->whereNull('pruned_at')->value('archive_key')) {
                $this->error("Archive $open is exported but not pruned yet. Prune it first, so archives never overlap.");

                return self::FAILURE;
            }
            // Everything below (counts, sums, rows written) comes from ONE consistent snapshot.
            DB::unprepared('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        try {
            $base = DB::table('events')->where('block_id', '<', $cutoffBlock);
            $candidates = (clone $base)->where('synced', true);

            $total = (int) DB::table('events')->count();
            $count = (int) (clone $candidates)->count();
            $unsyncedOld = (int) (clone $base)->where('synced', false)->count();
            $range = (clone $candidates)->selectRaw('min(id) a, max(id) b, min(block_id) c, max(block_id) d, sum(length(flow_metadata)) bytes')->first();

            $amount = EventsArchiveState::amountSql();
            $sum = fn ($q, array $types) => (string) (clone $q)->whereIn('type', $types)->selectRaw("COALESCE(SUM($amount), 0) s")->value('s');
            $dust = [
                'claimed_archived' => $sum($candidates, EventsArchiveState::claimTypes()),
                'burned_archived' => $sum($candidates, EventsArchiveState::burnTypes()),
                'claimed_all' => $sum(DB::table('events'), EventsArchiveState::claimTypes()),
                'burned_all' => $sum(DB::table('events'), EventsArchiveState::burnTypes()),
            ];
            $cursors = DB::table('syncs')->get()->map(fn ($r) => (array) $r)->all();
            $byYear = (clone $candidates)->selectRaw("coalesce(year(event_date), 'none') y, coalesce(source, if(graffle, 'graffle', 'legacy')) s, count(*) n")
                ->groupBy('y', 's')->orderBy('y')->get();

            $this->info(($this->option('export') ? 'EXPORT' : 'DRY RUN').": cutoff ".$cutoffDate->toDateString()." = block {$cutoffBlock} (archive key {$key})");
            $this->line("events total {$total}; archive candidates (synced, block < cutoff) {$count}; would remain ".($total - $count));
            $this->line("unsynced rows below cutoff (NEVER archived, needed by flow:process / gave-up review): {$unsyncedOld}");
            $this->line("candidate id range {$range->a}..{$range->b}, blocks {$range->c}..{$range->d}, flow_metadata ~".round(($range->bytes ?? 0) / 1048576).' MB');
            foreach ($byYear as $r) {
                $this->line("  {$r->y} {$r->s}: {$r->n}");
            }
            $this->line('DUST sums: '.json_encode($dust));
            $this->line('indexer cursors (syncs): '.json_encode($cursors));
            $this->line('archive watermark (indexer ignores blocks <= it): '.EventsArchiveState::watermark());
            foreach (DB::table('events_archive_baselines')->orderBy('id')->get() as $b) {
                $this->line("  baseline {$b->archive_key}: rows {$b->rows}, pruned {$b->pruned_rows}, claimed {$b->claimed_archived}/{$b->claimed_pruned} pruned, burned {$b->burned_archived}/{$b->burned_pruned} pruned, watermark {$b->watermark_block}, r2 ".($b->r2_verified_at ?? '-').', pruned_at '.($b->pruned_at ?? '-'));
            }

            if (! $this->option('export')) {
                $this->comment('Dry run only. Nothing written, nothing deleted.');

                return self::SUCCESS;
            }
            if ($count === 0) {
                $this->error('No candidates, nothing to export.');

                return self::FAILURE;
            }

            Storage::makeDirectory($dir);
            $files = [];
            $handles = [];
            $idsPath = Storage::path("$dir/ids.txt.gz");
            $ids = gzopen($idsPath, 'wb6');
            $chunk = max(500, (int) $this->option('chunk'));
            $lastId = 0;
            $written = 0;
            do {
                $rows = (clone $candidates)->where('id', '>', $lastId)->orderBy('id')->limit($chunk)->get();
                foreach ($rows as $row) {
                    $month = $row->event_date ? substr($row->event_date, 0, 7) : 'undated';
                    if (! isset($handles[$month])) {
                        $handles[$month] = gzopen(Storage::path("$dir/events-$month.jsonl.gz"), 'wb6');
                        $files[$month] = ['file' => "events-$month.jsonl.gz", 'rows' => 0, 'min_id' => $row->id, 'max_id' => $row->id];
                    }
                    gzwrite($handles[$month], json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                    gzwrite($ids, $row->id."\n");
                    $files[$month]['rows']++;
                    $files[$month]['max_id'] = $row->id;
                    $lastId = $row->id;
                    $written++;
                }
                $this->output->write("\r{$written}/{$count}");
            } while ($rows->count() === $chunk);
            $this->newLine();
            DB::unprepared('COMMIT');

            foreach ($handles as $month => $h) {
                gzclose($h);
                $files[$month]['sha256'] = hash_file('sha256', Storage::path("$dir/".$files[$month]['file']));
            }
            gzclose($ids);
            ksort($files);
            if ($written !== $count) {
                $this->error("wrote $written rows but the snapshot counted $count. Not recording a baseline.");

                return self::FAILURE;
            }

            $manifest = [
                'created_at' => now()->toIso8601String(),
                'archive_key' => $key,
                'keep_days' => $this->option('cutoff-block') ? null : $keepDays,
                'cutoff_date' => $cutoffDate->toDateString(),
                'cutoff_block' => $cutoffBlock,
                'watermark_block' => $cutoffBlock - 1,
                'predicate' => 'synced = 1 AND block_id < cutoff_block',
                'rows' => $written,
                'min_id' => (int) $range->a,
                'max_id' => (int) $range->b,
                'events_total_at_export' => $total,
                'dust' => $dust,
                'ids_file' => ['file' => 'ids.txt.gz', 'rows' => $written, 'sha256' => hash_file('sha256', $idsPath)],
                'files' => array_values($files),
            ];
            Storage::put("$dir/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $manifestSha = hash_file('sha256', Storage::path("$dir/manifest.json"));

            DB::table('events_archive_baselines')->insert([
                'archive_key' => $key,
                'cutoff_date' => $cutoffDate->toDateString(),
                'cutoff_block' => $cutoffBlock,
                'watermark_block' => $cutoffBlock - 1,
                'min_id' => (int) $range->a,
                'max_id' => (int) $range->b,
                'rows' => $written,
                'manifest_sha256' => $manifestSha,
                'claimed_archived' => $dust['claimed_archived'],
                'burned_archived' => $dust['burned_archived'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            EventsArchiveState::forgetWatermark();

            $this->info("wrote {$written} rows to storage/app/{$dir} (".count($files)." files + ids.txt.gz), manifest sha256 {$manifestSha}. Nothing deleted.");
            $this->line('Baseline recorded; indexer watermark is now block '.($cutoffBlock - 1).'.');
            $this->line("Next: upload to R2, verify, put {$dir}/".self::MARKER.", then --prune --archive={$key}.");

            return self::SUCCESS;
        } finally {
            if ($this->option('export')) {
                // no-op if already committed
                DB::unprepared('ROLLBACK');
            }
        }
    }

    protected function prune(): int
    {
        $key = (string) $this->option('archive');
        $dir = 'events-archive/'.$key;
        $b = $key !== '' ? DB::table('events_archive_baselines')->where('archive_key', $key)->first() : null;
        if (! $b) {
            $this->error("--archive=<key> with a recorded baseline is required (got '{$key}').");

            return self::FAILURE;
        }
        if (! Storage::exists("$dir/manifest.json")) {
            $this->error("$dir/manifest.json missing.");

            return self::FAILURE;
        }
        $manifestSha = hash_file('sha256', Storage::path("$dir/manifest.json"));
        if (! hash_equals($b->manifest_sha256, $manifestSha)) {
            $this->error('manifest.json sha256 differs from the baseline row. Refusing.');

            return self::FAILURE;
        }
        if (! Storage::exists("$dir/".self::MARKER)) {
            $this->error("R2 verification marker $dir/".self::MARKER.' is missing. Upload + verify the archive on R2 first. Refusing.');

            return self::FAILURE;
        }
        $manifest = json_decode(Storage::get("$dir/manifest.json"), true);
        $marker = json_decode(Storage::get("$dir/".self::MARKER), true);
        if (! is_array($marker) || ($marker['manifest_sha256'] ?? null) !== $manifestSha || empty($marker['r2_prefix'])) {
            $this->error('R2 marker does not match this manifest (manifest_sha256 / r2_prefix). Refusing.');

            return self::FAILURE;
        }
        $expected = collect($manifest['files'])->push($manifest['ids_file'])->push(['file' => 'manifest.json', 'sha256' => $manifestSha, 'rows' => null]);
        foreach ($expected as $f) {
            $m = $marker['files'][$f['file']] ?? null;
            if (! $m || ($m['sha256'] ?? null) !== $f['sha256'] || ($f['rows'] !== null && (int) ($m['rows'] ?? -1) !== (int) $f['rows'])) {
                $this->error("R2 marker has no matching sha256/rows for {$f['file']}. Refusing.");

                return self::FAILURE;
            }
            if ($f['file'] !== 'manifest.json' && hash_file('sha256', Storage::path("$dir/{$f['file']}")) !== $f['sha256']) {
                $this->error("Local file {$f['file']} no longer matches its sha256. Refusing.");

                return self::FAILURE;
            }
        }
        if (! $b->r2_verified_at) {
            DB::table('events_archive_baselines')->where('id', $b->id)->update([
                'r2_prefix' => $marker['r2_prefix'], 'r2_verified_at' => Carbon::parse($marker['verified_at'] ?? now()), 'updated_at' => now(),
            ]);
        }
        $this->info("PRUNE {$key}: {$b->rows} archived rows (ids {$b->min_id}..{$b->max_id}, synced = 1, block_id < {$b->cutoff_block}); R2 {$marker['r2_prefix']} verified ".($marker['verified_at'] ?? '?'));
        $this->line("already pruned: {$b->pruned_rows}");

        $batchSize = min(5000, max(100, (int) $this->option('batch')));
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;
        $amount = EventsArchiveState::amountSql();
        $in = fn (array $types) => implode(',', array_map(fn ($t) => DB::getPdo()->quote($t), $types));
        $claimIn = $in(EventsArchiveState::claimTypes());
        $burnIn = $in(EventsArchiveState::burnTypes());

        $fh = gzopen(Storage::path("$dir/ids.txt.gz"), 'rb');
        $read = $deleted = 0;
        $batch = [];
        $prev = 0;
        $flush = function () use (&$batch, &$deleted, $b, $amount, $claimIn, $burnIn, $sleepUs) {
            if (! $batch) {
                return;
            }
            $ph = implode(',', array_fill(0, count($batch), '?'));
            $where = "id IN ($ph) AND id BETWEEN ? AND ? AND synced = 1 AND block_id < ?";
            $bind = array_merge($batch, [$b->min_id, $b->max_id, $b->cutoff_block]);
            DB::transaction(function () use ($where, $bind, $b, $amount, $claimIn, $burnIn, &$deleted) {
                $s = DB::selectOne("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN type IN ($claimIn) THEN $amount END), 0) c, COALESCE(SUM(CASE WHEN type IN ($burnIn) THEN $amount END), 0) bu FROM events WHERE $where FOR UPDATE", $bind);
                if ((int) $s->n === 0) {
                    return;
                }
                $n = DB::delete("DELETE FROM events WHERE $where", $bind);
                if ($n !== (int) $s->n) {
                    throw new \RuntimeException("batch mismatch: selected {$s->n}, deleted {$n}");
                }
                DB::update('UPDATE events_archive_baselines SET pruned_rows = pruned_rows + ?, claimed_pruned = claimed_pruned + CAST(? AS DECIMAL(30,8)), burned_pruned = burned_pruned + CAST(? AS DECIMAL(30,8)), updated_at = ? WHERE id = ?',
                    [$n, (string) $s->c, (string) $s->bu, now(), $b->id]);
                $deleted += $n;
            });
            $batch = [];
            if ($sleepUs) {
                usleep($sleepUs);
            }
        };

        while (($line = gzgets($fh)) !== false) {
            $id = (int) trim($line);
            if ($id <= $prev || $id < $b->min_id || $id > $b->max_id) {
                gzclose($fh);
                $this->error("ids.txt.gz not strictly increasing / out of range at id $id. Stopped (pruned so far: $deleted).");

                return self::FAILURE;
            }
            $prev = $id;
            $read++;
            $batch[] = $id;
            if (count($batch) >= $batchSize) {
                $flush();
                $this->output->write("\rread {$read}/{$b->rows}, deleted {$deleted}");
            }
        }
        $flush();
        gzclose($fh);
        $this->newLine();

        $b2 = DB::table('events_archive_baselines')->where('id', $b->id)->first();
        DB::table('events_archive_baselines')->where('id', $b->id)->update(['pruned_at' => now(), 'updated_at' => now()]);
        $this->info("deleted {$deleted} rows in this run; baseline pruned_rows {$b2->pruned_rows}/{$b2->rows}");
        $this->line("DUST pruned claimed {$b2->claimed_pruned} (archived {$b2->claimed_archived}), burned {$b2->burned_pruned} (archived {$b2->burned_archived})");
        if ($read !== (int) $b->rows) {
            $this->warn("ids file has $read ids, manifest says {$b->rows}");
        }
        if ((int) $b2->pruned_rows !== (int) $b2->rows || bccomp($b2->claimed_pruned, $b2->claimed_archived, 8) !== 0 || bccomp($b2->burned_pruned, $b2->burned_archived, 8) !== 0) {
            $this->warn('Pruned rows/sums differ from the archive (rows that stopped matching were left in place). Totals stay correct: recompute uses the pruned sums.');
        }
        $this->comment('Next: flovatar:recompute-dust-counters --dry-run (must be unchanged), then OPTIMIZE TABLE events in a quiet window.');

        return self::SUCCESS;
    }
}
