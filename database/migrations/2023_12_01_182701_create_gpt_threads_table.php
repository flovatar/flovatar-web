<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGptThreadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gpt_threads', function (Blueprint $table) {
            $table->id();
            $table->string('gpt_id')->unique()->index();
            $table->foreignId('gpt_assistant_id')->nullable()->index();
            $table->string('flow_address')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();


            $table->foreign('gpt_assistant_id')
                ->references('id')->on('gpt_assistants')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gpt_threads');
    }
}
