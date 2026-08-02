<?php

namespace App\Services;

use App\Models\ClassParticipant;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single scoped source of truth for mentorship/mentee counts — shared by
 * MentorshipStatsOverview (the dashboard widget) and MentorshipStatsToolProvider
 * (the MNCHGPT query tool) so they can never drift apart on what a given
 * user is allowed to see.
 */
class MentorshipStatsService
{
    /**
     * Live mentorships only — pilot runs are excluded from every count here.
     */
    public function baseTrainingQuery(User $user): Builder
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false);

        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->forMentorOrCoMentor($user->id);
        }

        return $query;
    }

    public function menteesQuery(User $user, ?int $programId = null): Builder
    {
        return ClassParticipant::query()
            ->whereHas('mentorshipClass.training', function (Builder $query) use ($user, $programId) {
                $query->where('type', 'facility_mentorship')->where('is_pilot', false);

                if ($programId) {
                    $query->where('program_id', $programId);
                }

                if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
                    $query->forMentorOrCoMentor($user->id);
                }
            });
    }

    public function overallStats(User $user): array
    {
        $programs = $this->programStats($user);

        return [
            'mentorships' => $this->baseTrainingQuery($user)->count(),
            'mentees' => array_sum(array_column($programs, 'mentees')),
        ];
    }

    public function programStats(User $user): array
    {
        return Program::whereHas('trainings', fn (Builder $q) => $q
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false))
            ->orderBy('name')
            ->get()
            ->map(fn (Program $program) => [
                'name' => $program->name,
                'mentorships' => $this->baseTrainingQuery($user)->where('program_id', $program->id)->count(),
                'mentees' => $this->menteesQuery($user, $program->id)->distinct('class_participants.user_id')->count('class_participants.user_id'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{overall: array{mentorships: int, mentees: int}, program: ?array{name: string, mentorships: int, mentees: int}}
     */
    public function countsFor(User $user, ?string $programName = null): array
    {
        $programs = $this->programStats($user);

        $program = $programName
            ? collect($programs)->first(fn ($p) => strcasecmp($p['name'], $programName) === 0)
            : null;

        return [
            'overall' => [
                'mentorships' => $this->baseTrainingQuery($user)->count(),
                'mentees' => array_sum(array_column($programs, 'mentees')),
            ],
            'program' => $program,
        ];
    }
}
