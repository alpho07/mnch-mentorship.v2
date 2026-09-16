<?php

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;

class ListTrainingExports extends ListRecords
{
    protected static string $resource = TrainingExportResource::class;
    protected static string $view = 'filament.pages.training-export-dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configure_export')
                ->label('Configure New Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->size('lg')
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }

    public function getTitle(): string
    {
        return 'Trainings | Mentorships Data Exports';
    }

    public function getSubheading(): ?string
    {
        return 'Export comprehensive training participant/mentee data with customizable fields and filters';
    }

    // Override the table query to show export statistics instead
    public function getViewData(): array
    {
        return [
            'stats' => $this->getExportStatistics(),
            'recentTrainings' => $this->getRecentTrainings(),
            'availableExportTypes' => $this->getAvailableExportTypes(),
        ];
    }

    protected function getExportStatistics(): array
    {
        return [
            'total_trainings' => Training::count(),
            'global_trainings' => Training::where('type', 'global_training')->count(),
            'facility_mentorships' => Training::where('type', 'facility_mentorship')->count(),
            'total_participants' => TrainingParticipant::count(),
            'completed_participants' => TrainingParticipant::where('completion_status', 'completed')->count(),
            'ongoing_trainings' => Training::where('status', 'ongoing')->count(),
            'trainings_with_participants' => Training::whereHas('participants')->count(),
            'trainings_with_assessments' => Training::where('assess_participants', true)->count(),
        ];
    }

    protected function getRecentTrainings(): \Illuminate\Database\Eloquent\Collection
    {
        return Training::with(['facility', 'county', 'participants'])
            ->whereHas('participants')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    protected function getAvailableExportTypes(): array
    {
        return [
            [
                'key' => 'training_participants',
                'title' => 'Participants | Mentee Export',
                'description' => 'Export detailed participant/mentee lists from selected trainings. Each training/mentorship becomes a separate worksheet with participant or mentee details, assessments, and status information.',
                'icon' => 'heroicon-o-users',
                'color' => 'success',
                'use_cases' => [
                    'Attendance tracking and reporting',
                    'Participant performance analysis',
                    'Certificate generation lists',
                    'Follow-up communication planning'
                ]
            ],
            [
                'key' => 'participant_trainings',
                'title' => 'Participant | Mentee  History',
                'description' => 'Export complete trainingmentorship history for selected participants/mentee. Shows all trainings/mentorsips each person has attended across the entire system.',
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'info',
                'use_cases' => [
                    'Individual performance tracking',
                    'Career development planning',
                    'Competency progression analysis',
                    'Training/Mentorship needs assessment'
                ]
            ],
            [
                'key' => 'training_summary',
                'title' => 'Training/Mentorship Summary Report',
                'description' => 'Export high-level overview and statistics of selected trainings/mentorship. Perfect for management reports and program evaluation.',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'warning',
                'use_cases' => [
                    'Management reporting',
                    'Program evaluation',
                    'Funding reports',
                    'Strategic planning'
                ]
            ]
        ];
    }
}