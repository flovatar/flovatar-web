<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlovatarTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flovatar_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('color')->nullable()->index();
            $table->string('series')->nullable()->index();
            $table->string('rarity')->nullable()->index();
            $table->longText('svg')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_mintable')->nullable();
            $table->integer('burned')->default(0);
            $table->integer('flow_id')->nullable()->unique();
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
        Schema::dropIfExists('flovatar_templates');
    }
}
