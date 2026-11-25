<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ĐUST marketplace (KB notes/dust-marketplace.md): listing / sale currency.
 * Additive: `currency` (FLOW | DUC | DUST, backfilled from is_dapper) on sale_offers + sales, `expires_at` on sale_offers
 * (storefront listing expiry). Nothing existing is renamed or dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sale_offers', 'sales'] as $table) {
            if (! Schema::hasColumn($table, 'currency')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('currency', 16)->nullable()->after('price')->index();
                });
            }
            DB::table($table)->whereNull('currency')->where('is_dapper', 1)->update(['currency' => 'DUC']);
            DB::table($table)->whereNull('currency')->update(['currency' => 'FLOW']);
        }
        if (! Schema::hasColumn('sale_offers', 'expires_at')) {
            Schema::table('sale_offers', function (Blueprint $t) {
                $t->timestamp('expires_at')->nullable()->after('listing_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_offers', 'expires_at')) {
            Schema::table('sale_offers', fn (Blueprint $t) => $t->dropColumn('expires_at'));
        }
        foreach (['sale_offers', 'sales'] as $table) {
            if (Schema::hasColumn($table, 'currency')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['currency']);
                    $t->dropColumn('currency');
                });
            }
        }
    }
};
