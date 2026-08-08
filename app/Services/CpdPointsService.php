<?php

namespace App\Services;

use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CpdPointsService
{
    const CERT_POINTS = 3;

    const MODULE_POINTS = 1;

    const LEVELS = [
        ['min' => 0,  'max' => 5,           'name' => 'Foundation',           'short' => 'F',  'color' => '#6B7280'],
        ['min' => 6,  'max' => 15,          'name' => 'Practitioner',          'short' => 'P',  'color' => '#3B82F6'],
        ['min' => 16, 'max' => 30,          'name' => 'Advanced Practitioner', 'short' => 'AP', 'color' => '#10B981'],
        ['min' => 31, 'max' => 50,          'name' => 'Expert',                'short' => 'E',  'color' => '#8B5CF6'],
        ['min' => 51, 'max' => PHP_INT_MAX, 'name' => 'Master Practitioner',   'short' => 'MP', 'color' => '#F59E0B'],
    ];

    // ── Mentee ───────────────────────────────────────────────────────────────

    public function forMentee(User $user): array
    {
        // 3 pts per certificate issued (head DRMH approved)
        $certs = ClassParticipant::where('user_id', $user->id)
            ->whereNotNull('head_drmh_approved_at')
            ->count();

        // 1 pt per completed module, awarded the moment that module is done —
        // independent of whether the class as a whole has finished.
        $completedModules = MenteeModuleProgress::where('status', 'completed')
            ->whereHas('classParticipant', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $certPoints = $certs * self::CERT_POINTS;
        $modulePoints = $completedModules * self::MODULE_POINTS;
        $total = $certPoints + $modulePoints;

        return [
            'total' => $total,
            'level' => $this->level($total),
            'cert_points' => $certPoints,
            'module_points' => $modulePoints,
            'certificates' => $certs,
            'completed_modules' => $completedModules,
        ];
    }

    /**
     * Batch-compute mentee CPD for a set of user IDs (2 aggregate SQL queries).
     * Module points only count when the class is completed.
     */
    public function batchForMentees(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        // Certificates per user
        $certs = ClassParticipant::whereIn('user_id', $userIds)
            ->whereNotNull('head_drmh_approved_at')
            ->selectRaw('user_id, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->toArray();

        // Completed modules per user, awarded per module regardless of class status
        $modules = DB::table('mentee_module_progress as mp')
            ->join('class_participants as cp', 'cp.id', '=', 'mp.class_participant_id')
            ->whereIn('cp.user_id', $userIds)
            ->where('mp.status', 'completed')
            ->selectRaw('cp.user_id, COUNT(*) as cnt')
            ->groupBy('cp.user_id')
            ->pluck('cnt', 'user_id')
            ->toArray();

        $result = [];
        foreach ($userIds as $uid) {
            $certCount = $certs[$uid] ?? 0;
            $modCount = $modules[$uid] ?? 0;
            $total = ($certCount * self::CERT_POINTS) + ($modCount * self::MODULE_POINTS);

            $result[$uid] = [
                'total' => $total,
                'level' => $this->level($total),
                'certificates' => $certCount,
                'modules' => $modCount,
            ];
        }

        return $result;
    }

    // ── Mentor ───────────────────────────────────────────────────────────────

    public function forMentor(User $user): array
    {
        // 1 pt per module the mentor has facilitated to completion, awarded
        // the moment that module is done — independent of whether the class
        // as a whole has finished.
        $completedModuleCount = DB::table('class_modules as cm')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cm.mentorship_class_id')
            ->join('trainings as t', 't.id', '=', 'mc.training_id')
            ->where('t.mentor_id', $user->id)
            ->where('cm.status', 'completed')
            ->count();

        $total = $completedModuleCount * self::MODULE_POINTS;

        return [
            'total' => $total,
            'level' => $this->level($total),
            'module_points' => $total,
            'completed_modules' => $completedModuleCount,
        ];
    }

    /**
     * Batch-compute mentor CPD for a set of mentor user IDs (2 aggregate SQL queries).
     */
    public function batchForMentors(array $mentorIds): array
    {
        if (empty($mentorIds)) {
            return [];
        }

        // 1 pt per completed module, awarded per module regardless of class status
        $modules = DB::table('class_modules as cm')
            ->join('mentorship_classes as mc', 'mc.id', '=', 'cm.mentorship_class_id')
            ->join('trainings as t', 't.id', '=', 'mc.training_id')
            ->whereIn('t.mentor_id', $mentorIds)
            ->where('cm.status', 'completed')
            ->selectRaw('t.mentor_id, COUNT(*) as cnt')
            ->groupBy('t.mentor_id')
            ->pluck('cnt', 'mentor_id')
            ->toArray();

        $result = [];
        foreach ($mentorIds as $uid) {
            $modCount = $modules[$uid] ?? 0;
            $total = $modCount * self::MODULE_POINTS;

            $result[$uid] = [
                'total' => $total,
                'level' => $this->level($total),
                'completed_modules' => $modCount,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function level(int $total): array
    {
        foreach (array_reverse(self::LEVELS) as $lvl) {
            if ($total >= $lvl['min']) {
                return $lvl;
            }
        }

        return self::LEVELS[0];
    }
}
