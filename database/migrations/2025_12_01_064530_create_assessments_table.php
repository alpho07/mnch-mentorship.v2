<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();

            // Facility & Assessment Type
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->enum('assessment_type', ['baseline', 'midline', 'endline'])->default('baseline');
            $table->date('assessment_date');

            // Assessor (Auto-populated from logged-in user)
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assessor_name');
            $table->string('assessor_contact')->nullable();

            // Status
            $table->enum('status', ['draft', 'in_progress', 'completed', 'reviewed', 'approved'])->default('draft');

            // Overall Scores (Auto-calculated)
            $table->decimal('overall_score', 8, 2)->nullable();
            $table->decimal('overall_percentage', 5, 2)->nullable();
            $table->enum('overall_grade', ['green', 'yellow', 'red'])->nullable();

            // Progress Tracking
            $table->json('section_progress')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // Audit Trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('assessment_date');
            $table->index('status');
            $table->index('assessment_type');
            $table->index(['facility_id', 'assessment_date']);
            $table->index('assessor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assessments');
    }
};
