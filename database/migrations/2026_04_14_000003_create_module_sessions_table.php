<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('module_sessions')) {
            Schema::create('module_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
                $table->string('name');
                $table->integer('time_minutes')->nullable();
                $table->foreignId('methodology_id')->nullable()->constrained('methodologies')->nullOnDelete();
                $table->integer('order_sequence')->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('program_module_id');
                $table->index('order_sequence');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_sessions');
    }
};
