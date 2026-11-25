<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ĐUST Quests (testnet feature behind DUST_QUESTS_ENABLED, KB notes/dust-quests.md). Additive only: new tables.
 *  - quest_challenges: the catalogue (seeded from config/quests.php once per key, then edited in the admin page)
 *  - quest_claims: one row per (account, challenge) — UNIQUE — status pending|verified|paid|rejected, payout batch + tx
 *  - quest_payout_batches: batches handed to the payout worker (idempotent by batch id / claim ids)
 *  - quest_account_flags: admin freeze + per-account cap override
 *  - quest_settings: admin overrides of the season budget / daily cap / per-user cap / payouts paused
 *  - quest_discord_links: Discord user id per account (UNIQUE both ways)
 *  - quest_progress: honor-click start time, quiz attempts (per account + challenge)
 *  - quest_activity_days: one row per account per UTC day seen signed in (7-day streak)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_challenges')) {
            Schema::create('quest_challenges', function (Blueprint $table) {
                $table->id();
                $table->string('key', 48)->unique();
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->decimal('amount', 18, 8)->default(0);
                $table->string('verify_type', 16);
                $table->string('url', 255)->nullable();
                $table->string('action', 32)->nullable();
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->unsignedInteger('max_claims_total')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quest_claims')) {
            Schema::create('quest_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->index();
                $table->string('address', 18)->index();
                $table->foreignId('challenge_id')->index();
                $table->string('challenge_key', 48);
                $table->string('season', 32)->default('season-1');
                $table->decimal('amount', 18, 8);
                $table->string('status', 16)->index(); // pending | verified | paid | rejected
                $table->json('evidence')->nullable();
                $table->string('evidence_key', 191)->nullable()->unique(); // e.g. discord:<id>, share:<url>, wallet:<parent>
                $table->string('review_note', 255)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('batch_id', 36)->nullable()->index();
                $table->string('tx_id', 64)->nullable()->index();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->unique(['passkey_account_id', 'challenge_id']);
            });
        }
        if (! Schema::hasTable('quest_payout_batches')) {
            Schema::create('quest_payout_batches', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('status', 16)->index(); // reserved | paid | failed
                $table->unsignedInteger('count')->default(0);
                $table->decimal('total', 18, 8)->default(0);
                $table->string('tx_id', 64)->nullable()->unique();
                $table->text('error')->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quest_account_flags')) {
            Schema::create('quest_account_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->unique();
                $table->boolean('frozen')->default(false);
                $table->string('frozen_reason', 255)->nullable();
                $table->decimal('cap_override', 18, 8)->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quest_settings')) {
            Schema::create('quest_settings', function (Blueprint $table) {
                $table->string('key', 64)->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quest_discord_links')) {
            Schema::create('quest_discord_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->unique();
                $table->string('discord_user_id', 32)->unique();
                $table->string('username', 64)->nullable();
                $table->boolean('in_guild')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('quest_progress')) {
            Schema::create('quest_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id');
                $table->string('challenge_key', 48);
                $table->timestamp('started_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->date('attempts_day')->nullable();
                $table->timestamps();
                $table->unique(['passkey_account_id', 'challenge_key']);
            });
        }
        if (! Schema::hasTable('quest_activity_days')) {
            Schema::create('quest_activity_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id');
                $table->date('day');
                $table->timestamps();
                $table->unique(['passkey_account_id', 'day']);
            });
        }
    }

    public function down(): void
    {
        foreach (['quest_activity_days', 'quest_progress', 'quest_discord_links', 'quest_settings', 'quest_account_flags', 'quest_payout_batches', 'quest_claims', 'quest_challenges'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
