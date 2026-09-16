<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_status_logs', function (Blueprint $table) {
            $table->id();

            // Scope: either TrainingParticipant OR MentorshipParticipant
            $table->unsignedBigInteger('training_participant_id')->nullable()->index();
            $table->unsignedBigInteger('mentorship_participant_id')->nullable()->index();

            // Month granularity
            $table->unsignedTinyInteger('month_number'); // 1, 2, 3... 12

            // Status details
            $table->string('status_type'); // e.g. transferred, cadre_change, dept_change, facility_change, deceased
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('notes')->nullable();

            // Audit fields
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamp('recorded_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('training_participant_id')->references('id')->on('training_participants')->onDelete('cascade');
            $table->foreign('mentorship_participant_id')->references('id')->on('training_participants')->onDelete('cascade'); // if you later separate mentorship_participants, update here
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_status_logs');
    }
};
