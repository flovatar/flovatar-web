<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passkey (WebAuthn / FLIP-264) proof of concept: new tables only (testnet feature behind PASSKEY_POC_ENABLED).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('passkey_accounts')) {
            Schema::create('passkey_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('address', 18)->nullable()->unique();
                $table->string('email')->nullable();
                $table->string('status', 16)->default('pending');
                $table->string('created_ip', 45)->nullable()->index();
                $table->string('creation_tx', 64)->nullable();
                $table->string('gift_tx', 64)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('passkey_credentials')) {
            Schema::create('passkey_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->index();
                $table->string('credential_id', 255)->unique();
                $table->string('public_key', 128);
                $table->unsignedInteger('key_index')->nullable();
                $table->string('user_handle', 128);
                $table->string('aaguid', 36)->nullable();
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->string('label', 120)->nullable();
                $table->boolean('backed_up')->default(false);
                $table->string('status', 16)->default('pending');
                $table->string('add_key_tx', 64)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('passkey_transactions')) {
            Schema::create('passkey_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passkey_account_id')->nullable()->index();
                $table->string('kind', 32);
                $table->string('tx_id', 64)->index();
                $table->string('status', 16)->default('Pending');
                $table->text('error')->nullable();
                $table->string('signed_by', 32)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_transactions');
        Schema::dropIfExists('passkey_credentials');
        Schema::dropIfExists('passkey_accounts');
    }
};
