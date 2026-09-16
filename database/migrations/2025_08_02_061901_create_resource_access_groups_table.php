<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resource_access_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('access_group_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['resource_id', 'access_group_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resource_access_groups');
    }
};
