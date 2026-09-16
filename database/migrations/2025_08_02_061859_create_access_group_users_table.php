<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('access_group_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['access_group_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('access_group_users');
    }
};
