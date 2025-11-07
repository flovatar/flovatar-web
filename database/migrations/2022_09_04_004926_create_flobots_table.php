<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlobotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flobots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('series')->nullable();
            $table->longText('svg')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->string('rarity')->nullable()->default(0);
            $table->string('flow_address')->nullable()->index();
            $table->json('flow_metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('flobots');
    }
}
