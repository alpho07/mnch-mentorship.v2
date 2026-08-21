<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log of every "your mentorship stalled" reminder sent to a mentor —
     * lets MentorshipStallReminderService avoid re-sending the same
     * mentorship's reminder every day, and lets the admin center show a
     * "last reminded" column. `sent_by` is null for the scheduled command,
     * set to the acting user for a manual send from the admin page.
     */
    public function up(): void
    {
        Schema::create('mentorship_stall_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->string('bucket'); // 'no_class' | 'no_mentee' | 'no_modules'
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['training_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_stall_reminders');
    }
};
