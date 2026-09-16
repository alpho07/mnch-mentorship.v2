<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Enrollment-Level Attendance (class join via invite link)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-mark class-level attendance when a mentee enrolls via invite link.
     * source = 'auto', session_id = null, class_module_id = null.
     * Idempotent — safe to call multiple times.
     */
    public function markEnrollmentAttendance(User $user, MentorshipClass $class): ClassAttendance
    {
        return ClassAttendance::firstOrCreate(
            [
                'class_id'        => $class->id,
                'class_module_id' => null,
                'session_id'      => null,
                'user_id'         => $user->id,
            ],
            [
                'marked_by' => $user->id,
                'marked_at' => now(),
                'source'    => 'auto',
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Module-Level Attendance (mentee self-confirms via dashboard)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Confirm a mentee's attendance for a specific module.
     *
     * Rules:
     *  - Module must be in_progress and attendance_link_active.
     *  - User must be an enrolled participant in the parent class.
     *  - Idempotent — returns false (no error) if already confirmed.
     *
     * @return array{success: bool, message: string}
     */
    public function confirmModuleAttendance(User $user, ClassModule $classModule): array
    {
        // Guard: module must be active
        if ($classModule->status !== 'in_progress') {
            return ['success' => false, 'message' => 'This module is not currently active.'];
        }

        if (!$classModule->attendance_link_active) {
            return ['success' => false, 'message' => 'Attendance confirmation is not open for this module.'];
        }

        // Guard: user must be enrolled
        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->first();

        if (!$participant) {
            return ['success' => false, 'message' => 'You are not enrolled in this class.'];
        }

        // Idempotency check
        $alreadyConfirmed = ClassAttendance::where('class_id', $classModule->mentorship_class_id)
            ->where('class_module_id', $classModule->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyConfirmed) {
            return ['success' => false, 'message' => 'You have already confirmed attendance for this module.'];
        }

        try {
            DB::transaction(function () use ($user, $classModule, $participant) {
                // Write audit-safe attendance record
                ClassAttendance::create([
                    'class_id'        => $classModule->mentorship_class_id,
                    'class_module_id' => $classModule->id,
                    'session_id'      => null,
                    'user_id'         => $user->id,
                    'marked_by'       => $user->id,
                    'marked_at'       => now(),
                    'source'          => 'auto',
                ]);

                // Update mentee progress to in_progress if it was not_started
                \App\Models\MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->where('class_module_id', $classModule->id)
                    ->where('status', 'not_started')
                    ->update([
                        'status'     => 'in_progress',
                        'started_at' => now(),
                    ]);
            });

            return ['success' => true, 'message' => 'Attendance confirmed successfully.'];
        } catch (\Exception $e) {
            Log::error('Module attendance confirmation failed', [
                'user_id'         => $user->id,
                'class_module_id' => $classModule->id,
                'error'           => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Manual Attendance (mentor marks present/absent)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mentor manually marks a mentee's attendance for a module.
     * Overwrites any existing record (marked_by = mentor, source = manual).
     *
     * @param  string $status 'present'|'absent'
     */
    public function markManualModuleAttendance(
        User $mentor,
        ClassModule $classModule,
        User $mentee,
        string $status = 'present'
    ): array {
        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
            ->where('user_id', $mentee->id)
            ->first();

        if (!$participant) {
            return ['success' => false, 'message' => 'Mentee is not enrolled in this class.'];
        }

        try {
            DB::transaction(function () use ($mentor, $mentee, $classModule, $participant, $status) {
                $present = $status === 'present';

                ClassAttendance::updateOrCreate(
                    [
                        'class_id'        => $classModule->mentorship_class_id,
                        'class_module_id' => $classModule->id,
                        'user_id'         => $mentee->id,
                    ],
                    [
                        'session_id' => null,
                        'marked_by'  => $mentor->id,
                        'marked_at'  => now(),
                        'source'     => 'manual',
                    ]
                );

                if ($present) {
                    \App\Models\MenteeModuleProgress::updateOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id'      => $classModule->id,
                        ],
                        [
                            'status'                => 'in_progress',
                            'started_at'            => now(),
                            'attendance_percentage' => 100.0,
                        ]
                    );
                }
            });

            return ['success' => true, 'message' => "Marked as {$status}."];
        } catch (\Exception $e) {
            Log::error('Manual attendance marking failed', [
                'mentor_id'       => $mentor->id,
                'mentee_id'       => $mentee->id,
                'class_module_id' => $classModule->id,
                'error'           => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'An error occurred.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get attendance summary for a module:
     * [confirmed => int, total_enrolled => int, rate => float, records => Collection]
     */
    public function getModuleAttendanceSummary(ClassModule $classModule): array
    {
        $confirmed = ClassAttendance::where('class_id', $classModule->mentorship_class_id)
            ->where('class_module_id', $classModule->id)
            ->count();

        $totalEnrolled = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
            ->whereIn('status', ['enrolled', 'active'])
            ->count();

        $rate = $totalEnrolled > 0 ? round(($confirmed / $totalEnrolled) * 100, 1) : 0.0;

        return [
            'confirmed'      => $confirmed,
            'total_enrolled' => $totalEnrolled,
            'rate'           => $rate,
        ];
    }

    /**
     * Get the list of all enrolled mentees with their confirmation status for a module.
     * Used by ManageModuleMentees to show the attendance panel.
     *
     * @return \Illuminate\Support\Collection<array{participant: ClassParticipant, confirmed: bool, confirmed_at: ?Carbon, source: ?string}>
     */
    public function getModuleAttendanceRoster(ClassModule $classModule): \Illuminate\Support\Collection
    {
        $participants = ClassParticipant::with('user')
            ->where('mentorship_class_id', $classModule->mentorship_class_id)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        $attendanceMap = ClassAttendance::where('class_id', $classModule->mentorship_class_id)
            ->where('class_module_id', $classModule->id)
            ->get()
            ->keyBy('user_id');

        return $participants->map(function (ClassParticipant $participant) use ($attendanceMap) {
            $record = $attendanceMap->get($participant->user_id);

            return [
                'participant'  => $participant,
                'user'         => $participant->user,
                'confirmed'    => $record !== null,
                'confirmed_at' => $record?->marked_at,
                'source'       => $record?->source,
                'marked_by'    => $record?->marked_by,
            ];
        });
    }
}