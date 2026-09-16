<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;

trait AuthorizesClassAccess
{
    /**
     * Abort 403 unless the authenticated user is the mentor, a co-mentor, or an above-site admin
     * for the training that owns the given class.
     */
    private function authorizeClassAccess(MentorshipClass $class): void
    {
        $user = request()->user();
        if ($user->isAboveSite()) return;

        $training = $class->training;
        abort_if(!$training, 404, 'Mentorship not found.');

        $isMentor   = $training->mentor_id === $user->id;
        $isCoMentor = $training->acceptedCoMentors()->where('user_id', $user->id)->exists();

        abort_if(!$isMentor && !$isCoMentor, 403, 'Not authorised for this class.');
    }

    /**
     * Authorize access via the module's parent class.
     */
    private function authorizeModuleAccess(ClassModule $module): void
    {
        $this->authorizeClassAccess($module->mentorshipClass);
    }

    /**
     * Authorize access via the participant's class.
     */
    private function authorizeParticipantAccess(ClassParticipant $participant): void
    {
        $this->authorizeClassAccess($participant->mentorshipClass);
    }
}
