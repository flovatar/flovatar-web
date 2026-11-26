<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asteroid Islands (ISLANDS_MODE, KB notes/islands-build.md). Additive only: new tables, filled by the event indexer
 * (App\Services\Islands\IslandEventProcessor) and `islands:sync`. Empty and unused while the feature is off.
 *  - islands: one row per island NFT (flow_id = serial), owner from Terraformed / NonFungibleToken.Deposited
 *  - island_residents: one row per (island, chain slot); set_by = island owner when the reference was set
 *  - island_templates: chain template cache (metadata + SVG chunks joined), for server/browser composition
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('islands')) {
            Schema::create('islands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->unique();
                $table->string('rarity', 16)->index();
                $table->string('name', 64)->nullable();
                $table->text('description')->nullable();
                $table->json('layers')->nullable();
                $table->string('tint', 7)->nullable();
                $table->json('deed_ids')->nullable();
                $table->string('deed_rarity', 16)->nullable();
                $table->boolean('fused')->default(false);
                $table->string('flow_address', 18)->nullable()->index();
                $table->unsignedSmallInteger('residents_count')->default(0);
                $table->timestamp('terraformed_at')->nullable();
                $table->string('terraform_tx', 64)->nullable();
                $table->unsignedBigInteger('block_id')->nullable();
                $table->timestamp('chain_synced_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('island_residents')) {
            Schema::create('island_residents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('island_flow_id')->index();
                $table->string('slot', 16);
                $table->string('nft_type', 160);
                $table->unsignedBigInteger('nft_id');
                $table->string('set_by', 18)->nullable();
                $table->timestamp('set_at')->nullable();
                $table->boolean('valid')->default(true);
                $table->string('reason', 32)->nullable();
                $table->timestamps();
                $table->unique(['island_flow_id', 'slot']);
                $table->index(['nft_type', 'nft_id']);
            });
        }
        if (! Schema::hasTable('island_templates')) {
            Schema::create('island_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->unique();
                $table->string('layer', 32)->index();
                $table->string('rarity', 16)->index();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('svg_length')->default(0);
                $table->unsignedInteger('svg_front_length')->default(0);
                $table->longText('svg')->nullable();
                $table->longText('svg_front')->nullable();
                $table->string('svg_hash', 40)->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('usage')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('island_templates');
        Schema::dropIfExists('island_residents');
        Schema::dropIfExists('islands');
    }
};
