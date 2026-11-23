<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per `flovatar:events-archive --export` run (archive of old, processed `events`
 * rows to gzipped JSONL on R2). Written by the archive command:
 *  - export: cutoff, watermark, id range, row count, manifest sha256, DUST sums of the archive
 *  - R2 verification marker: r2_prefix / r2_verified_at
 *  - --prune: pruned_rows / claimed_pruned / burned_pruned, updated in the SAME transaction as
 *    each delete batch, so `flovatar:recompute-dust-counters` (sum over events + these
 *    columns) gives the same totals before, during and after pruning.
 * watermark_block: the indexer refuses to (re)insert events at or below it (see EventsArchiveState).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events_archive_baselines', function (Blueprint $table) {
            $table->id();
            $table->string('archive_key')->unique()->comment('storage/app/events-archive/<key>');
            $table->date('cutoff_date');
            $table->unsignedBigInteger('cutoff_block')->comment('archived: synced = 1 AND block_id < cutoff_block');
            $table->unsignedBigInteger('watermark_block')->comment('cutoff_block - 1; indexer ignores events <= this');
            $table->unsignedBigInteger('min_id');
            $table->unsignedBigInteger('max_id');
            $table->unsignedBigInteger('rows');
            $table->char('manifest_sha256', 64);
            $table->decimal('claimed_archived', 30, 8)->default(0);
            $table->decimal('burned_archived', 30, 8)->default(0);
            $table->string('r2_prefix')->nullable();
            $table->timestamp('r2_verified_at')->nullable();
            $table->unsignedBigInteger('pruned_rows')->default(0);
            $table->decimal('claimed_pruned', 30, 8)->default(0);
            $table->decimal('burned_pruned', 30, 8)->default(0);
            $table->timestamp('pruned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events_archive_baselines');
    }
};
