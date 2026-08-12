<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->string('question_code')->unique();
            $table->text('question_text');
            $table->text('help_text')->nullable();
            $table->string('question_type', 100);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('display_conditions')->nullable();
            $table->json('requires_explanation_on')->nullable();
            $table->string('explanation_label')->default('Comments/Recommendations');
            $table->json('scoring_map')->nullable();
            $table->boolean('is_scored')->default(true);
            $table->integer('order')->default(0);
            $table->string('group')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['survey_section_id', 'order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
