<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TrainingOverviewWidget extends BaseWidget
{
    public ?Training $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $stats = $this->getTrainingStats();

        return [
            Stat::make('Total Mentees', $stats['total_mentees'])
                ->description('Enrolled participants')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Completion Rate', $stats['completion_rate'] . '%')
                ->description('Finished program')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($stats['completion_rate'] >= 80 ? 'success' : ($stats['completion_rate'] >= 60 ? 'warning' : 'danger')),

            Stat::make('Average Score', $stats['average_score'] . '%')
                ->description('Overall performance')
                ->descriptionIcon('heroicon-m-star')
                ->color($stats['average_score'] >= 80 ? 'success' : ($stats['average_score'] >= 70 ? 'warning' : 'danger')),

            Stat::make('Material Cost', 'KES ' . number_format($stats['total_material_cost'], 0))
                ->description('KES ' . number_format($stats['cost_per_mentee'], 0) . ' per mentee')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Assessment Progress', $stats['assessment_completion'] . '%')
                ->description('Assessments completed')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($stats['assessment_completion'] >= 90 ? 'success' : ($stats['assessment_completion'] >= 70 ? 'warning' : 'danger')),
        ];
    }

    private function getTrainingStats(): array
    {
        $totalMentees = $this->record->participants()->count();
        $totalCategories = $this->record->assessmentCategories()->count();
        
        if ($totalMentees == 0) {
            return [
                'total_mentees' => 0,
                'completion_rate' => 0,
                'pass_rate' => 0,
                'average_score' => 0,
                'departments_represented' => 0,
                'cadres_represented' => 0,
                'total_material_cost' => 0,
                'assessment_completion' => 0,
                'cost_per_mentee' => 0,
            ];
        }

        // Assessment completion rate
        $totalPossibleAssessments = $totalMentees * $totalCategories;
        $completedAssessments = \App\Models\MenteeAssessmentResult::whereHas('assessmentCategory', function ($query) {
            $query->where('training_id', $this->record->id);
        })->count();

        $assessmentCompletion = $totalPossibleAssessments > 0 
            ? round(($completedAssessments / $totalPossibleAssessments) * 100, 1)
            : 0;

        // Overall completion and pass rates
        $completedMentees = $this->record->participants()
            ->where('completion_status', 'completed')
            ->count();

        $passedMentees = $this->record->participants()
            ->whereHas('user.assessmentResults', function ($query) {
                $query->whereHas('assessmentCategory', function ($q) {
                    $q->where('training_id', $this->record->id);
                })
                ->select('participant_id')
                ->groupBy('participant_id')
                ->havingRaw('AVG(score) >= 70');
            })
            ->count();

        $completionRate = round(($completedMentees / $totalMentees) * 100, 1);
        $passRate = $totalMentees > 0 ? round(($passedMentees / $totalMentees) * 100, 1) : 0;

        // Average score calculation
        $averageScore = \App\Models\MenteeAssessmentResult::whereHas('assessmentCategory', function ($query) {
            $query->where('training_id', $this->record->id);
        })->avg('score') ?? 0;

        // Demographics
        $participantData = $this->record->participants()->with(['user.department', 'user.cadre'])->get();
        $departmentsRepresented = $participantData->pluck('user.department.name')->filter()->unique()->count();
        $cadresRepresented = $participantData->pluck('user.cadre.name')->filter()->unique()->count();

        // Material costs
        $totalMaterialCost = $this->record->trainingMaterials()->sum('actual_cost') ?? 0;

        return [
            'total_mentees' => $totalMentees,
            'completion_rate' => $completionRate,
            'pass_rate' => $passRate,
            'average_score' => round($averageScore, 1),
            'departments_represented' => $departmentsRepresented,
            'cadres_represented' => $cadresRepresented,
            'total_material_cost' => $totalMaterialCost,
            'assessment_completion' => $assessmentCompletion,
            'cost_per_mentee' => $totalMentees > 0 ? round($totalMaterialCost / $totalMentees, 2) : 0,
        ];
    }
}