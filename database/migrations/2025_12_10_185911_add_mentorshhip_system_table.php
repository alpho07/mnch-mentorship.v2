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
        // Create mentorship_classes table
        if (!Schema::hasTable('mentorship_classes')) {
            Schema::create('mentorship_classes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->string('name');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->string('enrollment_token')->nullable()->unique();
                $table->boolean('enrollment_link_active')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index('training_id');
                $table->index('status');
                $table->index('enrollment_token');
            });
        }

        // Create class_participants table (class-specific enrollments)
        if (!Schema::hasTable('class_participants')) {
            Schema::create('class_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mentorship_class_id')->constrained('mentorship_classes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['enrolled', 'active', 'completed', 'dropped'])->default('enrolled');
                $table->timestamp('enrolled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('dropped_at')->nullable();
                $table->text('drop_reason')->nullable();
                $table->timestamps();

                $table->unique(['mentorship_class_id', 'user_id']);
                $table->index('status');
            });
        }

        // Create program_modules table
        if (!Schema::hasTable('program_modules')) {
            Schema::create('program_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('order_sequence')->default(0);
                $table->integer('duration_weeks')->nullable();
                $table->json('objectives')->nullable();
                $table->json('content')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('program_id');
                $table->index('order_sequence');
            });
        }

        // Create class_modules table
        if (!Schema::hasTable('class_modules')) {
            Schema::create('class_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mentorship_class_id')->constrained('mentorship_classes')->cascadeOnDelete();
                $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
                $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
                $table->integer('order_sequence')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->boolean('requires_assessment')->default(false);
                $table->decimal('min_attendance_percentage', 5, 2)->default(75);
                $table->string('attendance_token')->nullable()->unique();
                $table->boolean('attendance_link_active')->default(false);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('mentorship_class_id');
                $table->index('status');
            });
        }

        // Create class_sessions table
        if (!Schema::hasTable('class_sessions')) {
            Schema::create('class_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_module_id')->constrained('class_modules')->cascadeOnDelete();
                $table->integer('session_number')->default(1);
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('scheduled_date')->nullable();
                $table->time('scheduled_time')->nullable();
                $table->date('actual_date')->nullable();
                $table->time('actual_time')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('location')->nullable();
                $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
                $table->boolean('attendance_taken')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('class_module_id');
                $table->index('status');
                $table->index('scheduled_date');
            });
        }

        // Create mentorship_co_mentors table
        if (!Schema::hasTable('mentorship_co_mentors')) {
            Schema::create('mentorship_co_mentors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->enum('status', ['pending', 'accepted', 'declined', 'removed'])->default('pending');
                $table->json('permissions')->nullable();
                $table->timestamps();

                $table->unique(['training_id', 'user_id']);
                $table->index('status');
            });
        }

        // Create session_attendance table
        if (!Schema::hasTable('session_attendance')) {
            Schema::create('session_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('class_sessions')->cascadeOnDelete();
                $table->foreignId('class_participant_id')->constrained('class_participants')->cascadeOnDelete();
                $table->enum('status', ['present', 'absent', 'excused', 'late'])->default('absent');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['session_id', 'class_participant_id']);
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_attendance');
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('class_modules');
        Schema::dropIfExists('program_modules');
        Schema::dropIfExists('mentorship_co_mentors');
        Schema::dropIfExists('class_participants');
        Schema::dropIfExists('mentorship_classes');
    }
};