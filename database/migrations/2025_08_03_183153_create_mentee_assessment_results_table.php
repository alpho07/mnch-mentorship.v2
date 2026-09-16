<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentee_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('training_participants')->onDelete('cascade');
            $table->foreignId('assessment_category_id')->constrained()->onDelete('cascade');
            $table->decimal('score', 5, 2);
            $table->foreignId('grade_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('assessed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('assessment_date');
            $table->text('feedback')->nullable();
            $table->integer('attempts')->default(1);
            $table->integer('time_taken_minutes')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'assessment_category_id'],'mar_participant_cat_unique');
            $table->index(['assessment_category_id', 'score'],'ass_cat_score');
            $table->index(['assessed_by', 'assessment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentee_assessment_results');
    }
};
