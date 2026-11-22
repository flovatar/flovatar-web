<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\Asteroid was referenced by AsteroidImageController but never existed.
 * Asteroid Islands are not minted yet; the table stays empty until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asteroids')) {
            return;
        }

        Schema::create('asteroids', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->longText('svg')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->string('flow_address')->nullable()->index();
            $table->json('flow_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asteroids');
    }
};
