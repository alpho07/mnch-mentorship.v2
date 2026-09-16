<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('file_type');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'is_primary']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_files');
    }
};