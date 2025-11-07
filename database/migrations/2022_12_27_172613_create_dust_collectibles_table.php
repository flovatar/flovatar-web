<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDustCollectiblesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dust_collectibles', function (Blueprint $table) {
            $table->id();
            $table->integer('mint')->nullable()->unsigned();
            $table->string('name')->nullable()->index();
            $table->foreignId('dust_series_id')->nullable()->index();
            $table->longText('svg')->nullable();
            $table->integer('rare_count')->unsigned()->default(0);
            $table->integer('epic_count')->unsigned()->default(0);
            $table->integer('legendary_count')->unsigned()->default(0);
            $table->integer('rarity_score')->nullable();
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
        Schema::dropIfExists('dust_collectibles');
    }
}
