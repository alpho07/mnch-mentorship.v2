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
        // Superseded by 2025_12_01_064532_create_assessment_questions_table.php
        return;
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('assessment_sections')->cascadeOnDelete();
            $table->string('question_code')->unique(); // e.g., 'Q1.1'
            $table->text('question_text');
            
            // Question Type
            $table->enum('response_type', [
                'yes_no',
                'yes_no_partial',
                'yes_no_na',
                'number',
                'text',
                'textarea',
                'date',
                'matrix',
                'checkbox',
                'radio',
                'select'
            ]);
            
            // For matrix questions
            $table->json('matrix_locations')->nullable(); // ['Skills Lab', 'NBU', 'Maternity', etc.]
            
            // Options for select/radio/checkbox
            $table->json('options')->nullable(); // ['Option 1', 'Option 2', etc.]
            
            // Validation and Requirements
            $table->boolean('is_required')->default(false);
            $table->boolean('requires_explanation')->default(false);
            $table->string('explanation_label')->nullable(); // "Please explain" or custom label
            
            // Scoring
            $table->json('scoring_map')->nullable(); // {'Yes': 1, 'No': 0, 'Partially': 0.5, 'N/A': null}
            $table->boolean('include_in_scoring')->default(true);
            
            // Skip Logic
            $table->json('skip_logic')->nullable();
            
            // Display
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Additional
            $table->text('help_text')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index(['section_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};