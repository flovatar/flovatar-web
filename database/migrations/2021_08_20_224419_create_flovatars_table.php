<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlovatarsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flovatars', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('series')->nullable();
            $table->longText('svg')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->integer('rare_count')->unsigned()->default(0);
            $table->integer('epic_count')->unsigned()->default(0);
            $table->integer('legendary_count')->unsigned()->default(0);
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
        Schema::dropIfExists('flovatars');
    }
}
