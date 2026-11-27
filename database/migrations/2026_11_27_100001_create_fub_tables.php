<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUB v1 (KB notes/fub/FUB-BUILD.md):
 *  - fub_drafts / fub_draft_layers / fub_draft_items: the creator's off-chain draft (sanitised SVG), synced to the chain
 *    by the operator after Commit; originals are kept privately (svg_original) and purged with the draft (90 days).
 *  - fubs / fub_items / funs: index of the chain (FlovatarUniverseBuilder events + script refresh).
 *  - fub_reports: report button (Q11 b); hidden_at on fubs/fub_items = site-only delist (Q13 a: never touches chain).
 *  - fub_tool_calls: audit of the draft-tool API (design assistant actions, undo list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fub_drafts', function (Blueprint $t) {
            $t->id();
            $t->string('owner_address', 18)->index();
            $t->unsignedBigInteger('chain_fub_id')->nullable()->unique();
            $t->string('name', 64);
            $t->text('description')->nullable();
            $t->json('settings')->nullable();          // royalty, unique, max_supply, license
            $t->string('status', 16)->default('sketch'); // sketch | committed | published | abandoned
            $t->string('sync_state', 16)->default('idle'); // idle | pending | running | error
            $t->text('sync_error')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('fub_draft_layers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fub_draft_id')->index();
            $t->string('key', 16);
            $t->string('name', 32);
            $t->boolean('required')->default(true);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['fub_draft_id', 'key']);
        });
        Schema::create('fub_draft_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fub_draft_id')->index();
            $t->string('layer_key', 16);
            $t->string('name', 64);
            $t->string('description', 400)->nullable();
            $t->string('rarity', 16)->default('common');
            $t->decimal('price', 20, 8);
            $t->unsignedBigInteger('max_mint')->default(0);
            $t->decimal('increase', 10, 8)->default(0);
            $t->decimal('price_cap', 20, 8)->default(0);
            $t->boolean('large')->default(false);
            $t->longText('svg');
            $t->longText('svg_original')->nullable();
            $t->string('sha3', 64);
            $t->unsignedInteger('size');
            $t->json('report')->nullable();
            $t->boolean('ai_assisted')->default(false);
            $t->unsignedBigInteger('chain_item_id')->nullable()->index();
            $t->string('chain_state', 12)->default('new'); // new | synced | dirty | deleted
            $t->string('chain_hash', 64)->nullable();      // svg sha3 last written
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::create('fubs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('flow_id')->unique();
            $t->string('creator', 18)->index();
            $t->string('payout', 18)->nullable();
            $t->string('status', 16)->index();
            $t->boolean('suspended')->default(false);
            $t->string('tier', 16);
            $t->unsignedInteger('slots');
            $t->unsignedBigInteger('mint_cap');
            $t->unsignedBigInteger('max_supply');
            $t->unsignedBigInteger('minted')->default(0);
            $t->string('name', 64);
            $t->text('description')->nullable();
            $t->json('layers')->nullable();
            $t->decimal('royalty', 10, 8)->default(0);
            $t->boolean('unique_combos')->default(false);
            $t->string('license', 48)->nullable();
            $t->unsignedBigInteger('island_id')->nullable()->index();
            $t->string('manifest', 64)->nullable();
            $t->string('pending', 64)->nullable();
            $t->unsignedInteger('items')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('checks_passed_at')->nullable();
            $t->timestamp('hidden_at')->nullable();
            $t->string('hidden_reason', 200)->nullable();
            $t->json('data')->nullable();
            $t->timestamp('chain_read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('fub_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('flow_id')->unique();
            $t->unsignedBigInteger('fub_flow_id')->index();
            $t->string('layer', 16);
            $t->string('name', 64);
            $t->string('description', 400)->nullable();
            $t->string('rarity', 16);
            $t->decimal('price', 20, 8);
            $t->decimal('current_price', 20, 8);
            $t->unsignedBigInteger('max_mint')->default(0);
            $t->unsignedBigInteger('minted')->default(0);
            $t->decimal('increase', 10, 8)->default(0);
            $t->decimal('price_cap', 20, 8)->default(0);
            $t->unsignedInteger('size')->default(0);
            $t->string('hash', 64)->nullable();
            $t->string('state', 12);
            $t->boolean('live')->default(false);
            $t->boolean('suspended')->default(false);
            $t->timestamp('hidden_at')->nullable();
            $t->longText('svg')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('funs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('flow_id')->unique();
            $t->unsignedBigInteger('fub_flow_id')->index();
            $t->unsignedBigInteger('serial');
            $t->string('owner', 18)->nullable()->index();
            $t->json('picks');
            $t->decimal('price', 20, 8);
            $t->string('tx_id', 64)->nullable();
            $t->timestamp('minted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('fub_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('fub_flow_id')->nullable()->index();
            $t->unsignedBigInteger('item_flow_id')->nullable();
            $t->unsignedBigInteger('fun_flow_id')->nullable();
            $t->string('reporter', 18)->nullable();
            $t->string('reason', 32);
            $t->string('details', 1000)->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->string('status', 16)->default('open'); // open | dismissed | actioned
            $t->timestamps();
        });
        Schema::create('fub_tool_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fub_draft_id')->index();
            $t->string('actor', 18);
            $t->string('source', 16);  // studio | assistant
            $t->string('tool', 40);
            $t->boolean('ok');
            $t->string('summary', 300)->nullable();
            $t->json('undo')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fub_tool_calls', 'fub_reports', 'funs', 'fub_items', 'fubs', 'fub_draft_items', 'fub_draft_layers', 'fub_drafts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
