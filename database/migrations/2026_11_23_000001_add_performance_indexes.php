<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance 2026-11-23: indexes for the marketplace / transactions lists and the
 * per-item sale lookups (morphMany item_type+item_id), found with the slow query log +
 * EXPLAIN (full table scans + filesort). Additive only; InnoDB builds them online.
 */
return new class extends Migration
{
    private array $indexes = [
        'sales' => [
            'sales_item_type_item_id_created_at_index' => ['item_type', 'item_id', 'created_at'],
            'sales_is_dapper_created_at_index' => ['is_dapper', 'created_at'],
        ],
        'sale_offers' => [
            'sale_offers_item_type_item_id_index' => ['item_type', 'item_id'],
            'sale_offers_is_dapper_created_at_index' => ['is_dapper', 'created_at'],
            'sale_offers_is_dapper_price_index' => ['is_dapper', 'price'],
        ],
    ];

    private function exists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! $this->exists($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if ($this->exists($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
