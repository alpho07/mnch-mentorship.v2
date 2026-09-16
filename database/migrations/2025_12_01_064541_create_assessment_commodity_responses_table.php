<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessment_commodity_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_department_id')->constrained()->cascadeOnDelete();

            // Response
            $table->boolean('available')->default(false); // Yes/No toggle
            $table->text('notes')->nullable();
            $table->decimal('score', 5, 2)->default(0); // 1 if available, 0 if not

            $table->timestamps();

            // One response per commodity per department per assessment
            $table->unique(['assessment_id', 'commodity_id', 'assessment_department_id'], 'assessment_commodity_dept_unique');
            $table->index('assessment_id');
            $table->index('commodity_id');
            $table->index('assessment_department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessment_commodity_responses');
    }
};
