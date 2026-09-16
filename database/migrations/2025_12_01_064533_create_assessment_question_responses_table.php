<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessment_question_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_question_id')->constrained()->cascadeOnDelete();

            // Response Data
            $table->text('response_value')->nullable(); // Stores: Yes/No/Number/Text
            $table->text('explanation')->nullable();
            $table->json('metadata')->nullable(); // For proportion: {sample_size, positive_count, proportion}
            // Scoring (Auto-calculated from question's scoring_map)
            $table->decimal('score', 5, 2)->nullable();

            $table->timestamps();

            // One response per question per assessment
            $table->unique(['assessment_id', 'assessment_question_id'], 'assessment_question_unique');
            $table->index('assessment_id');
            $table->index('assessment_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessment_question_responses');
    }
};
