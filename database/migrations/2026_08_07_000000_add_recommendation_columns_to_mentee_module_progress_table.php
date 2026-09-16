<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mentor_recommendation and recommendation_written_at exist live in the
     * real database (both `text`, nullable) and are actively used —
     * MentorDashboard's activity feed (app/Filament/Pages/MentorDashboard.php)
     * orders by recommendation_written_at and displays mentor_recommendation
     * excerpts, and CoordinatorExceptionResolver::tier2InactiveMentors() reads
     * recommendation_written_at as one of three "last mentor activity"
     * signals. Neither column has ever had a migration.
     *
     * Found because SQLite silently mishandles a MAX() over a genuinely
     * missing column here: instead of erroring like MySQL would, SQLite
     * treats the unresolved quoted identifier as a string literal, so
     * MAX("recommendation_written_at") across ≥1 matching row returns the
     * literal string "recommendation_written_at" instead of NULL or a real
     * timestamp — which then fails to parse as a date. This is a test-
     * environment gap, not a production bug: the column already exists in
     * the real database, so production has never hit this.
     */
    public function up(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('mentee_module_progress', 'mentor_recommendation')) {
                $table->text('mentor_recommendation')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('mentee_module_progress', 'recommendation_written_at')) {
                $table->text('recommendation_written_at')->nullable()->after('mentor_recommendation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mentee_module_progress', function (Blueprint $table) {
            $table->dropColumn(['mentor_recommendation', 'recommendation_written_at']);
        });
    }
};
