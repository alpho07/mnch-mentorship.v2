<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Non-pilot facility_mentorship trainings stuck in "draft" status
     * despite already having real classes and enrolled mentees (e.g. 5
     * trainings at Bungoma/Chuka County Referral Hospital with 1-5 mentees
     * each) — draft was never flipped to active even though the mentorship
     * is genuinely running. Reclassify any non-pilot draft training that
     * has at least one class with an enrolled/active/completed mentee as
     * active. Pilot trainings are left untouched — pilot/live status is a
     * separate concept from this fix. Idempotent (no-op once no such
     * draft rows remain).
     */
    public function up(): void
    {
        DB::table('trainings')
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->where('status', 'draft')
            ->whereIn('id', function ($query) {
                $query->select('mentorship_classes.training_id')
                    ->from('mentorship_classes')
                    ->join('class_participants', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
                    ->whereIn('class_participants.status', ['enrolled', 'active', 'completed']);
            })
            ->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible — these were data errors (real, running mentorships
     * mislabeled as draft), so there is no meaningful prior state worth
     * restoring.
     */
    public function down(): void
    {
        //
    }
};
