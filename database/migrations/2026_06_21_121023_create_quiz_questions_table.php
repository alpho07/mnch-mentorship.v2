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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_module_quiz_id')->constrained('program_module_quizzes')->cascadeOnDelete();
            $table->longText('question_text');
            $table->longText('explanation')->nullable();
            $table->integer('order_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('program_module_quiz_id');
            $table->index('order_sequence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
