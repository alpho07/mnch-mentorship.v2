<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mirror image of 2026_08_09_213024_activate_draft_mentorships_with_mentees:
     * that migration promoted drafts to active once they genuinely qualified.
     * This one demotes the opposite data error — non-pilot facility_mentorship
     * trainings marked active/completed despite never actually starting
     * (either zero classes at all, or classes that never left "draft"). Those
     * violate the new Training::canActivate() invariant. Nothing is deleted;
     * this only corrects the status field back to draft so the mentorship can
     * be started properly. Idempotent (no-op once no such rows remain).
     */
    public function up(): void
    {
        DB::table('trainings')
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->whereIn('status', ['active', 'completed'])
            // Training uses SoftDeletes — DB::table() bypasses that global
            // scope, so this has to be explicit or soft-deleted rows get
            // swept up too.
            ->whereNull('deleted_at')
            ->whereNotIn('id', function ($query) {
                $query->select('mentorship_classes.training_id')
                    ->from('mentorship_classes')
                    ->join('class_participants', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                    ->whereIn('mentorship_classes.status', ['active', 'completed'])
                    ->whereNull('mentorship_classes.deleted_at')
                    ->whereIn('class_participants.status', ['enrolled', 'active', 'completed']);
            })
            ->update(['status' => 'draft']);
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible — these were data errors (mentorships marked
     * active/completed without ever starting), so there is no meaningful
     * prior state worth restoring.
     */
    public function down(): void
    {
        //
    }
};
