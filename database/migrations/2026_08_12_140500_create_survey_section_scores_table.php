<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_section_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('grade')->nullable();
            $table->integer('total_questions')->default(0);
            $table->integer('answered_questions')->default(0);
            $table->integer('skipped_questions')->default(0);
            $table->timestamps();

            $table->unique(['survey_response_id', 'survey_section_id'], 'survey_section_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_section_scores');
    }
};
