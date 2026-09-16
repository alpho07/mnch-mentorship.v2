<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        // Module progress tracking per mentee
        Schema::create('mentee_module_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_participant_id')->constrained('class_participants')->cascadeOnDelete();
            $table->foreignId('class_module_id')->constrained('class_modules')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'exempted'])->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('exempted_at')->nullable();
            $table->boolean('completed_in_previous_class')->default(false);
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('assessment_score', 5, 2)->nullable();
            $table->enum('assessment_status', ['pending', 'passed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['class_participant_id', 'class_module_id'], 'unique_participant_module');
            $table->index(['class_participant_id', 'status']);
        });

        // Module assessments (dynamic per module)
        Schema::create('module_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_module_id')->constrained('class_modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('assessment_type', ['manual', 'checklist', 'score', 'mcq'])->default('score');
            $table->decimal('pass_threshold', 5, 2)->default(70.00); // Percentage
            $table->decimal('max_score', 8, 2)->default(100.00);
            $table->decimal('weight_percentage', 5, 2)->default(100.00); // For multiple assessments
            $table->boolean('is_active')->default(true);
            $table->json('questions_data')->nullable(); // For MCQ or checklist items
            $table->integer('order_sequence')->default(1);
            $table->timestamps();

            $table->index(['class_module_id', 'is_active']);
        });

        // Assessment results per mentee
        Schema::create('module_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_assessment_id')->constrained('module_assessments')->cascadeOnDelete();
            $table->foreignId('class_participant_id')->constrained('class_participants')->cascadeOnDelete();
            $table->foreignId('mentee_progress_id')->nullable()->constrained('mentee_module_progress')->cascadeOnDelete();
            $table->decimal('score', 5, 2); // Score achieved
            $table->enum('status', ['passed', 'failed'])->default('failed');
            $table->text('feedback')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at');
            $table->json('answers_data')->nullable(); // For MCQ answers
            $table->timestamps();

            $table->index(['class_participant_id', 'status']);
            $table->index(['module_assessment_id', 'status']);
        });

        // Add fields to class_modules to track assessment requirement (guard against duplicates)
        Schema::table('class_modules', function (Blueprint $table) {
            if (!Schema::hasColumn('class_modules', 'requires_assessment')) {
                $table->boolean('requires_assessment')->default(false)->after('status');
            }
            if (!Schema::hasColumn('class_modules', 'min_attendance_percentage')) {
                $table->decimal('min_attendance_percentage', 5, 2)->default(75.00)->after('requires_assessment');
            }
        });
    }

    public function down(): void {
        Schema::table('class_modules', function (Blueprint $table) {
            $table->dropColumn(['requires_assessment', 'min_attendance_percentage']);
        });

        Schema::dropIfExists('module_assessment_results');
        Schema::dropIfExists('module_assessments');
        Schema::dropIfExists('mentee_module_progress');
    }
};
