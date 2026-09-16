<?php

namespace App\Services;

use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles all enrollment logic for mentees.
 * 
 * DOMAIN RULES:
 * - Enrollment creates ClassParticipant + MenteeModuleProgress per module.
 * - Exemption check: if mentee completed a module in any previous class, auto-exempt.
 * - Auto-attendance: enrollment via invite link triggers AttendanceService (auto source).
 * - Pages and controllers MUST delegate enrollment to this service.
 */
class EnrollmentService
{
    /**
     * Enroll a user in a class.
     * Creates participant record and module progress with exemption checks.
     * 
     * @param User             $user   The user to enroll
     * @param MentorshipClass  $class  The class to enroll into
     * @param string           $source 'manual' or 'link'
     * 
     * @return ClassParticipant The created participant
     */
    public function enrollInClass(User $user, MentorshipClass $class, string $source = 'manual'): ClassParticipant
    {
        return DB::transaction(function () use ($user, $class, $source) {
            // Create participant
            $participant = ClassParticipant::create([
                'mentorship_class_id' => $class->id,
                'user_id'             => $user->id,
                'status'              => 'enrolled',
                'enrolled_at'         => now(),
                'enrollment_source'   => $source,
            ]);

            // Create module progress for each module in the class
            $this->createModuleProgressForParticipant($participant, $class);

            return $participant;
        });
    }

    /**
     * Remove a participant from a class.
     * Deletes participant and all related module progress.
     */
    public function removeFromClass(ClassParticipant $participant): void
    {
        DB::transaction(function () use ($participant) {
            // Delete module progress first
            $participant->moduleProgress()->delete();

            // Delete assessment results
            $participant->assessmentResults()->delete();

            // Delete participant
            $participant->delete();
        });
    }

    /**
     * Create module progress records for a participant with exemption logic.
     * 
     * If a mentee has completed a module in a previous class (anywhere in the system),
     * the module is automatically marked as 'exempted'.
     */
    private function createModuleProgressForParticipant(
        ClassParticipant $participant,
        MentorshipClass $class
    ): void {
        $completedModuleIds = $this->getUserCompletedModules($participant->user_id);

        $class->load('classModules.programModule.activities');

        foreach ($class->classModules as $classModule) {
            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            MenteeModuleProgress::create([
                'class_participant_id'        => $participant->id,
                'class_module_id'             => $classModule->id,
                'status'                      => $isExempted ? 'exempted' : 'not_started',
                'completed_in_previous_class' => $isExempted,
                'exempted_at'                 => $isExempted ? now() : null,
            ]);

            // Auto-enroll in all activities for this module (status=pending; mentor marks completion)
            $activities = $classModule->programModule?->activities ?? collect();
            if ($activities->isNotEmpty()) {
                $rows = $activities->map(fn ($activity) => [
                    'class_module_id'      => $classModule->id,
                    'class_participant_id' => $participant->id,
                    'activity_id'          => $activity->id,
                    'status'               => 'pending',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ])->toArray();

                // insertOrIgnore so re-enrolling an existing mentee doesn't duplicate records
                ClassModuleActivityParticipant::insertOrIgnore($rows);
            }
        }
    }

    /**
     * Get module IDs that a user has completed in ANY previous class.
     */
    public function getUserCompletedModules(int $userId): array
    {
        return DB::table('class_participants')
            ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
            ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
            ->where('class_participants.user_id', $userId)
            ->where('mentee_module_progress.status', 'completed')
            ->pluck('class_modules.program_module_id')
            ->unique()
            ->toArray();
    }

    /**
     * Check if a user is already enrolled in a class.
     */
    public function isEnrolled(int $userId, int $classId): bool
    {
        return ClassParticipant::where('mentorship_class_id', $classId)
            ->where('user_id', $userId)
            ->exists();
    }
}
