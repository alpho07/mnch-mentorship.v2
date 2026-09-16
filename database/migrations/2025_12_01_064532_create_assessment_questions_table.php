<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_section_id')->constrained()->cascadeOnDelete();

            // Question Details
            $table->string('question_code')->unique(); // INFRA_Q1, SKILLS_Q1, etc.
            $table->text('question_text');
            $table->text('help_text')->nullable();

            // Question Type: yes_no | yes_no_partial | number | text | proportion | select | radio
            $table->enum('question_type', [
                'yes_no',
                'yes_no_partial',
                'number',
                'text',
                'proportion',
                'select',
                'radio'
            ]);

            // Options for select/radio (JSON array)
            $table->json('options')->nullable();

            // Validation
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable(); // {min: 0, max: 100}
            // Conditional Logic
            $table->json('display_conditions')->nullable(); // Show/hide based on other answers
            $table->json('requires_explanation_on')->nullable(); // ["No", "Partially"]
            $table->string('explanation_label')->default('Comments/Recommendations');
            $table->json('skip_logic')->nullable(); // {if_response: "No", skip_to: "Q5"}
            // Scoring
            $table->json('scoring_map')->nullable(); // {"Yes": 1, "No": 0, "Partially": 0.5}
            $table->boolean('is_scored')->default(true);

            // Organization
            $table->integer('order')->default(0);
            $table->string('group')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['assessment_section_id', 'order']);
            $table->index('question_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessment_questions');
    }
};
