<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passkey production hardening (testnet feature behind PASSKEY_POC_ENABLED / PASSKEY_LOGIN_ENABLED). Additive only:
 *  - passkey_accounts: email_verified_at, recovered_at (row re-created from the chain after a DB loss)
 *  - passkey_email_codes: one-time email codes (hashed), attempts, TTL
 *  - passkey_wallet_links: HybridCustody link of a passkey account (child) to a wallet (parent), for ĐUST quests
 *  - passkey_transactions: fee + sponsor_amount (FLOW the sponsor paid / sent), for the daily spend summary
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('passkey_accounts')) {
            Schema::table('passkey_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('passkey_accounts', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('email');
                }
                if (! Schema::hasColumn('passkey_accounts', 'recovered_at')) {
                    $table->timestamp('recovered_at')->nullable()->after('gift_tx');
                }
            });
        }
        if (Schema::hasTable('passkey_transactions')) {
            Schema::table('passkey_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('passkey_transactions', 'fee')) {
                    $table->decimal('fee', 18, 8)->nullable()->after('signed_by');
                }
                if (! Schema::hasColumn('passkey_transactions', 'sponsor_amount')) {
                    $table->decimal('sponsor_amount', 18, 8)->nullable()->after('fee');
                }
            });
        }
        if (! Schema::hasTable('passkey_email_codes')) {
            Schema::create('passkey_email_codes', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->index();
                $table->string('purpose', 16); // signup | verify
                $table->foreignId('passkey_account_id')->nullable()->index();
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('ip', 45)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('passkey_wallet_links')) {
            Schema::create('passkey_wallet_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->index();
                $table->string('child_address', 18);
                $table->string('parent_address', 18)->index();
                $table->string('wallet', 16)->nullable(); // flow | dapper | other
                $table->string('status', 16)->default('proved'); // proved | published | linked | removed
                $table->string('publish_tx', 64)->nullable();
                $table->string('claim_tx', 64)->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('rewarded_at')->nullable(); // ĐUST quests: graduation reward paid once
                $table->timestamps();
                $table->unique(['passkey_account_id', 'parent_address']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_wallet_links');
        Schema::dropIfExists('passkey_email_codes');
    }
};
