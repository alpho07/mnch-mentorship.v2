<?php

namespace App\Filament\Widgets;

use App\Models\ClassParticipant;
use App\Models\Program;
use App\Models\Training;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class MentorshipStatsOverview extends Widget
{
    protected static string $view = 'filament.widgets.mentorship-stats-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $programs = $this->programStats();

        return [
            'overall' => $this->overallStats($programs),
            'programs' => $programs,
        ];
    }

    /**
     * Live mentorships only — pilot runs are excluded from every count here.
     */
    private function baseTrainingQuery(): Builder
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false);

        $user = auth()->user();
        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->forMentorOrCoMentor($user->id);
        }

        return $query;
    }

    private function menteesQuery(?int $programId = null): Builder
    {
        $user = auth()->user();

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

    /**
     * Mentee count is the sum of the per-program breakdown (not a cross-program
     * distinct count), so the "All Mentorships" card always reconciles with the
     * program cards below it — a mentee active in two programs is counted once
     * per program here, matching how the breakdown itself counts them.
     */
    private function overallStats(array $programs): array
    {
        return [
            'mentorships' => $this->baseTrainingQuery()->count(),
            'mentees' => array_sum(array_column($programs, 'mentees')),
        ];
    }

    private function programStats(): array
    {
        return Program::whereHas('trainings', fn (Builder $q) => $q
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false))
            ->orderBy('name')
            ->get()
            ->map(fn (Program $program) => [
                'name' => $program->name,
                'mentorships' => $this->baseTrainingQuery()->where('program_id', $program->id)->count(),
                'mentees' => $this->menteesQuery($program->id)->distinct('class_participants.user_id')->count('class_participants.user_id'),
            ])
            ->values()
            ->all();
    }
}
