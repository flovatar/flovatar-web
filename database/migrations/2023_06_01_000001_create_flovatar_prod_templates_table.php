<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * flovatar_prod_templates is a scratch copy of flovatar_templates used by
 * `flovatar:sync-templates` (FlovatarProdTemplate model). It never had a migration and is
 * not in the production dump; create it with the flovatar_templates columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flovatar_prod_templates')) {
            return;
        }

        Schema::create('flovatar_prod_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('color')->nullable()->index();
            $table->integer('series')->nullable();
            $table->string('rarity')->nullable();
            $table->longText('svg')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_mintable')->default(0);
            $table->integer('burned')->default(0);
            $table->integer('flow_id')->nullable()->index();
            $table->string('flow_address')->nullable();
            $table->json('flow_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flovatar_prod_templates');
    }
};
