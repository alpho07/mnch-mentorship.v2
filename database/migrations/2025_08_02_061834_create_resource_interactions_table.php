<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resource_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['like', 'dislike', 'bookmark', 'share']);
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->unique(['resource_id', 'user_id', 'type']);
            $table->index(['resource_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resource_interactions');
    }
};
