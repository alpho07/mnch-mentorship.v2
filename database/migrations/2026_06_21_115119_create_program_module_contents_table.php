<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('program_module_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
            $table->enum('type', ['introduction', 'video', 'case_scenario']);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_path')->nullable();
            $table->integer('order_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('program_module_id');
            $table->index('type');
            $table->index('order_sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_module_contents');
    }
};
