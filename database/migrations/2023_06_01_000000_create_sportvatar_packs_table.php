<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sportvatar_packs existed in production without a migration (schema taken from the
 * 2026-11 production dump). Skipped when the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sportvatar_packs')) {
            return;
        }

        Schema::create('sportvatar_packs', function (Blueprint $table) {
            $table->id();
            $table->string('secret')->nullable()->index('packs_secret_index');
            $table->string('signature')->nullable();
            $table->integer('flow_id')->nullable()->unique('packs_flow_id_unique');
            $table->integer('flame_count')->default(0)->index('packs_flame_count_index');
            $table->unsignedBigInteger('sportvatar_series_id')->nullable()->index('packs_sportvatar_series_id_index');
            $table->string('name')->nullable();
            $table->string('flow_address')->nullable()->index('packs_flow_address_index');
            $table->json('flow_metadata')->nullable();
            $table->boolean('opened')->default(false);
            $table->unsignedBigInteger('user_id')->nullable()->index('packs_user_id_index');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Production table predates this migration; never drop it automatically.
    }
};
