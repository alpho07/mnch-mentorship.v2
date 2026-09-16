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
        // Create training_programs pivot table (many-to-many)
        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['training_id', 'program_id']);
            $table->index(['training_id', 'program_id']);
        });

        // Create training_modules pivot table (many-to-many)
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['training_id', 'module_id']);
            $table->index(['training_id', 'module_id']);
        });

        // Create training_methodologies pivot table (many-to-many)
        Schema::create('training_methodologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('methodology_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['training_id', 'methodology_id']);
            $table->index(['training_id', 'methodology_id']);
        });

        // Create training_target_facilities (for global trainings)
        Schema::create('training_target_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['training_id', 'facility_id']);
            $table->index(['training_id', 'facility_id']);
        });

        // Update trainings table to support both types
        Schema::table('trainings', function (Blueprint $table) {
            // Add type column if it doesn't exist
            if (!Schema::hasColumn('trainings', 'type')) {
                $table->enum('type', ['global_training', 'facility_mentorship'])
                      ->default('global_training')
                      ->after('description');
            }

            // Add mentor_id if it doesn't exist
            if (!Schema::hasColumn('trainings', 'mentor_id')) {
                $table->foreignId('mentor_id')
                      ->nullable()
                      ->constrained('users')
                      ->onDelete('set null')
                      ->after('organizer_id');
            }

            // Make facility_id nullable for global trainings
            if (Schema::hasColumn('trainings', 'facility_id')) {
                $table->foreignId('facility_id')
                      ->nullable()
                      ->change();
            }

            // Update dates to be date only (not datetime) if they exist
            /*if (Schema::hasColumn('trainings', 'start_date')) {
                $table->date('start_date')->change();
            } else {
                $table->date('start_date')->after('location');
            }

            if (Schema::hasColumn('trainings', 'end_date')) {
                $table->date('end_date')->change();
            } else {
                $table->date('end_date')->after('start_date');
            }*/

            // Add new fields for enhanced functionality
            if (!Schema::hasColumn('trainings', 'target_audience')) {
                $table->text('target_audience')->nullable()->after('max_participants');
            }

            if (!Schema::hasColumn('trainings', 'completion_criteria')) {
                $table->json('completion_criteria')->nullable()->after('learning_outcomes');
            }

            if (!Schema::hasColumn('trainings', 'materials_needed')) {
                $table->json('materials_needed')->nullable()->after('completion_criteria');
            }

            if (!Schema::hasColumn('trainings', 'notes')) {
                $table->text('notes')->nullable()->after('training_approaches');
            }

            // Add indexes for better performance
           // $table->index(['type', 'status']);
           // $table->index(['start_date', 'end_date']);
           // $table->index(['facility_id', 'type']);
        });

        // Update training_participants table
        Schema::table('training_participants', function (Blueprint $table) {
            // Add completion fields if they don't exist
            if (!Schema::hasColumn('training_participants', 'completion_status')) {
                $table->enum('completion_status', ['registered', 'in_progress', 'completed', 'dropped'])
                      ->default('registered')
                      ->after('attendance_status');
            }

            if (!Schema::hasColumn('training_participants', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('completion_status');
            }

            if (!Schema::hasColumn('training_participants', 'certificate_issued')) {
                $table->boolean('certificate_issued')->default(false)->after('completion_date');
            }

            if (!Schema::hasColumn('training_participants', 'notes')) {
                $table->text('notes')->nullable()->after('certificate_issued');
            }

            // Add indexes
            $table->index(['training_id', 'attendance_status']);
            $table->index(['training_id', 'completion_status']);
            $table->index(['user_id', 'training_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot tables
        Schema::dropIfExists('training_target_facilities');
        Schema::dropIfExists('training_methodologies');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('training_programs');

        // Remove added columns from trainings table
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'mentor_id',
                'target_audience',
                'completion_criteria',
                'materials_needed',
                'notes'
            ]);
        });

        // Remove added columns from training_participants table
        Schema::table('training_participants', function (Blueprint $table) {
            $table->dropColumn([
                'completion_status',
                'completion_date',
                'certificate_issued',
                'notes'
            ]);
        });
    }
};
