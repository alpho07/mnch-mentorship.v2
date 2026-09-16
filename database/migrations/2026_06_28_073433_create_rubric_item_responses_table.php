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
        Schema::create('rubric_item_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_assessment_id')->constrained('rubric_assessments')->cascadeOnDelete();
            $table->foreignId('rubric_item_id')->constrained('rubric_items')->cascadeOnDelete();
            $table->boolean('performed')->default(false);
            $table->timestamps();

            $table->unique(['rubric_assessment_id', 'rubric_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubric_item_responses');
    }
};
