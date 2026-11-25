<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: how the user signs in. NULL = wallet (every existing row, wallet logins never write it),
 * 'passkey' = walletless passkey account created by Flovatar (set by App\Support\PasskeyLogin::loginSite).
 * Marker for passkey-only features (ĐUST quests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'auth_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('auth_type', 16)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'auth_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['auth_type']);
                $table->dropColumn('auth_type');
            });
        }
    }
};
