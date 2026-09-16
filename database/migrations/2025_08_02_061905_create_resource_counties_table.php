<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resource_counties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('county_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['resource_id', 'county_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resource_counties');
    }
};

