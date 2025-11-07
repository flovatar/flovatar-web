<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('secret')->nullable()->index();
            $table->string('signature')->nullable();
            $table->integer('flow_id')->nullable()->unique();
            $table->integer('spark_count')->default(0)->index();
            $table->string('series')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('flow_address')->nullable()->index();
            $table->json('flow_metadata')->nullable();
            $table->boolean('opened')->default(0);
            $table->foreignId('user_id')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();


            $table->foreign('user_id')
                ->references('id')->on('users')
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
        Schema::dropIfExists('packs');
    }
}
