<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGptAssistantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gpt_assistants', function (Blueprint $table) {
            $table->id();
            $table->string('gpt_id')->unique()->index();
            $table->string('voice_id')->nullable();
            $table->text('description')->nullable();
            $table->text('user_instruction')->nullable();
            $table->text('system_instruction')->nullable();
            $table->json('suggestions')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->json('metadata')->nullable();
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
        Schema::dropIfExists('gpt_assistants');
    }
}
