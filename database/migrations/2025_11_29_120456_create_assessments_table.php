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
        // Superseded by 2025_12_01_064530_create_assessments_table.php
        return;
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_number')->unique(); // e.g., ASS-2024-001
            
            // Facility being assessed
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            
            // Assessment Type (e.g., MNCH Baseline, MNCH Follow-up, etc.)
            $table->foreignId('assessment_type_id')->constrained('assessment_types')->restrictOnDelete();
            
            // Assessor Information
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assessor_name')->nullable(); // Backup if not a system user
            $table->string('assessor_designation')->nullable();
            
            // Assessment Details
            $table->date('assessment_date');
            $table->date('scheduled_date')->nullable();
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'submitted',
                'approved',
                'rejected',
                'cancelled'
            ])->default('scheduled');
            
            // Scoring
            $table->decimal('total_score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade')->nullable(); // Excellent, Good, Satisfactory, etc.
            
            // Additional Information
            $table->text('purpose')->nullable();
            $table->text('observations')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('metadata')->nullable(); // Additional flexible data
            
            // Timestamps and Tracking
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('assessment_number');
            $table->index('facility_id');
            $table->index('assessment_type_id');
            $table->index('status');
            $table->index('assessment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};