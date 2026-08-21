<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name')->nullable();
            $table->string('path');
            $table->timestamp('created_at');

            $table->index('route_name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};

