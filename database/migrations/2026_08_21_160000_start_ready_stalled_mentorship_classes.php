<?php

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Among the mentorships 2026_08_21_154503_revert_non_compliant_mentorships_to_draft
     * reverts to draft, some had already done the real work — a class
     * created, a mentee enrolled, curriculum modules assigned — and simply
     * never had "Start" clicked. Any class satisfying
     * MentorshipClass::canStart() is started for real (via the model
     * method, so the normal cascade runs: modules open) and its parent
     * training reactivated, which Training::canActivate() now permits since
     * it has a started class with an enrolled mentee.
     *
     * Discovers the eligible class list live (not a hardcoded ID list) so
     * this is safe to run in any environment — dev, staging, production —
     * each with its own data and its own row IDs. Idempotent: canStart()
     * only matches classes still in draft, so already-started classes are
     * naturally skipped on a re-run.
     *
     * start(notify: false) — this is a backend data correction for classes
     * that should have been started already, not a "just happened" event.
     * An unprompted "your class started!" email arriving after the fact
     * would be more confusing than useful. Mentors/mentees interact with
     * the class normally from here; no announcement email is sent.
     */
    public function up(): void
    {
        $readyClassIds = MentorshipClass::where('status', 'draft')
            ->whereHas('training', function ($query) {
                $query->where('type', 'facility_mentorship')
                    ->where('is_pilot', false)
                    ->where('status', 'draft');
            })
            ->whereHas('participants', fn ($query) => $query->whereIn('status', ['enrolled', 'active', 'completed']))
            ->whereHas('classModules')
            ->pluck('id');

        $started = 0;

        foreach ($readyClassIds as $classId) {
            $class = MentorshipClass::find($classId);

            if (! $class || ! $class->canStart()) {
                continue;
            }

            $class->start(notify: false);
            $started++;

            $training = $class->training;

            if ($training && $training->status === 'draft' && $training->canActivate()) {
                $training->update(['status' => 'active']);
            }
        }

        Log::info("[start_ready_stalled_mentorship_classes] Started {$started} of {$readyClassIds->count()} candidate class(es).");
    }

    /**
     * Reverse the migrations.
     *
     * Not reversible — starting a class cascades real side effects (opened
     * modules) that shouldn't be silently undone.
     */
    public function down(): void
    {
        //
    }
};
