<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asteroid Islands round 4 (KB notes/islands-build.md): residents keep their MetadataViews.Display name + thumbnail URL
 * (read from chain by App\Jobs\RefreshIsland), so the island page / API can list the display's carousel without a
 * wallet and the renderer can draw the first standard resident on the display screen. Additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('island_residents') && ! Schema::hasColumn('island_residents', 'name')) {
            Schema::table('island_residents', function (Blueprint $table) {
                $table->string('name', 160)->nullable();
                $table->text('thumbnail')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('island_residents', 'name')) {
            Schema::table('island_residents', function (Blueprint $table) {
                $table->dropColumn(['name', 'thumbnail']);
            });
        }
    }
};
