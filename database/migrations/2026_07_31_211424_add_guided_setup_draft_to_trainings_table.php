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
        Schema::table('trainings', function (Blueprint $table) {
            // Holds the guided wizard's not-yet-committed Modules/Enroll
            // Mentees checkbox picks (module_ids, selected_users) once a
            // Training exists. The #[Url] mirrors used elsewhere in the
            // wizard only survive within the same browser tab/URL — this
            // column is what lets "Continue" on the pending-setup banner
            // restore those picks from a completely fresh session.
            $table->json('guided_setup_draft')->nullable()->after('guided_setup_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn('guided_setup_draft');
        });
    }
};
