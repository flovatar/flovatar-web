<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDustTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dust_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->integer('layer')->unsigned()->nullable()->index();
            $table->string('rarity')->nullable()->index();
            $table->unsignedFloat('base_price')->default(0)->index();
            $table->unsignedFloat('current_price')->default(0)->index();
            $table->longText('svg')->nullable();
            $table->integer('max_mintable')->nullable();
            $table->integer('minted')->default(0);
            $table->foreignId('dust_series_id')->nullable()->index();
            $table->integer('flow_id')->nullable()->unique();
            $table->string('flow_address')->nullable()->index();
            $table->json('flow_metadata')->nullable();
            $table->timestamps();

            $table->foreign('dust_series_id')
                ->references('id')->on('dust_series')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dust_templates');
    }
}
