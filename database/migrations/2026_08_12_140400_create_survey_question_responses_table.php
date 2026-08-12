<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_question_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->text('response_value')->nullable();
            $table->text('explanation')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['survey_response_id', 'survey_question_id'], 'survey_question_response_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_question_responses');
    }
};
