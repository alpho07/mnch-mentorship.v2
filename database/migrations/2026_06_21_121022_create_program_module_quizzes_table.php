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
        Schema::create('program_module_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
            $table->enum('type', ['pre_test', 'post_test', 'both'])->default('both');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('pass_mark_percentage', 5, 2)->default(85.00);
            $table->integer('order_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('program_module_id');
            $table->index('type');
            $table->unique(['program_module_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_module_quizzes');
    }
};
