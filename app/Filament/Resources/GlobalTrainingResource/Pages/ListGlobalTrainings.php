<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGlobalTrainings extends ListRecords
{
    protected static string $resource = GlobalTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New MOH Training')
                ->icon('heroicon-o-plus')
                ->color('primary'),

            Action::make('export_trainings')
                ->label('Export Trainings')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return $this->exportTrainingsToCsv();
                }),

            Action::make('export_all_participants')
                ->label('Export All Participants')
                ->icon('heroicon-o-users')
                ->color('info')
                ->action(function () {
                    return $this->exportAllParticipantsToCsv();
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'MOH Trainings';
    }

    public function getSubheading(): ?string
    {
        $stats = $this->getQuickStats();
        return "Multi-facility training programs • {$stats['total']} total • {$stats['ongoing']} active • {$stats['participants']} participants";
    }

    protected function getHeaderWidgets(): array
    {
        if (request()->get('tab') === 'home') {
            return [\App\Filament\Widgets\TrainingAnalyticsEmbed::class];
        }
        return [];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Trainings')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('trainings.deleted_at'))
                ->badge($this->getTabCount('all'))
                ->badgeColor('gray'),

            'national' => Tab::make('National Led')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('trainings.deleted_at')->where('lead_type', 'national'))
                ->badge($this->getTabCount('national'))
                ->badgeColor('primary'),

            'county' => Tab::make('County Led')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('trainings.deleted_at')->where('lead_type', 'county'))
                ->badge($this->getTabCount('county'))
                ->badgeColor('success'),

            'partner' => Tab::make('Partner Led')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('trainings.deleted_at')->where('lead_type', 'partner'))
                ->badge($this->getTabCount('partner'))
                ->badgeColor('warning'),

            'trashed' => Tab::make('Trashed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('trainings.deleted_at'))
                ->badge($this->getTabCount('trashed'))
                ->badgeColor('danger')
                ->icon('heroicon-o-trash'),

            'home' => Tab::make('Home')
                ->icon('heroicon-o-chart-bar-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0')),

            /*'ongoing' => Tab::make('Ongoing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'ongoing'))
                ->badge($this->getTabCount('ongoing'))
                ->badgeColor('success'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('start_date', '>', now()))
                ->badge($this->getTabCount('upcoming'))
                ->badgeColor('info'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge($this->getTabCount('completed'))
                ->badgeColor('primary'),

            'draft' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge($this->getTabCount('draft'))
                ->badgeColor('secondary'),*/
        ];
    }

    /**
     * Export Training Summary to CSV
     */
    protected function exportTrainingsToCsv(): StreamedResponse
    {
        $trainings = Training::where('type', 'global_training')
            ->with([
                'organizer.facility',
                'programs',
                'modules.program',
                'methodologies',
                'county',
                'partner',
                'participants.user.facility.subcounty.county'
            ])
            ->get();

        $filename = 'global_trainings_summary_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($trainings) {
            $file = fopen('php://output', 'w');

            // CSV Headers for Training Summary
            $headers = [
                'Training ID',
                'Training Title',
                'Description',
                'Status',
                'Type',
                'Lead Type',
                'Lead Organization',
                'Location',
                'Start Date',
                'End Date',
                'Registration Deadline',
                'Organizer Name',
                'Organizer Facility',
                'Max Participants',
                'Current Participants',
                'Completion Rate (%)',
                'Target Audience',
                'Programs',
                'Modules',
                'Methodologies',
                'Facilities Represented',
                'Counties Represented',
                'Learning Outcomes',
                'Prerequisites',
                'Training Approaches',
                'Created Date',
                'Notes'
            ];

            fputcsv($file, $headers);

            foreach ($trainings as $training) {
                $participantsByFacility = $training->participants->groupBy('user.facility.name')->keys();
                $participantsByCounty = $training->participants
                    ->map(fn($p) => $p->user->facility?->subcounty?->county?->name)
                    ->filter()
                    ->unique();

                $modules = $training->modules->map(function ($module) {
                    return "{$module->program->name} - {$module->name}";
                })->implode('; ');

                // Get lead organization based on lead type
                $leadOrganization = match ($training->lead_type) {
                    'national' => 'Ministry of Health',
                    'county' => $training->county?->name ?? 'Not specified',
                    'partner' => $training->partner?->name ?? 'Not specified',
                    default => 'Not specified',
                };

                $row = [
                    $training->identifier,
                    $training->title,
                    $training->description,
                    ucfirst($training->status),
                    ucfirst(str_replace('_', ' ', $training->type)),
                    ucfirst($training->lead_type),
                    $leadOrganization,
                    $training->location,
                    $training->start_date?->format('Y-m-d'),
                    $training->end_date?->format('Y-m-d'),
                    $training->registration_deadline?->format('Y-m-d H:i'),
                    $training->organizer?->full_name,
                    $training->organizer?->facility?->name,
                    $training->max_participants,
                    $training->participants->count(),
                    number_format($training->completion_rate, 1),
                    $training->target_audience,
                    $training->programs->pluck('name')->implode('; '),
                    $modules,
                    $training->methodologies->pluck('name')->implode('; '),
                    $participantsByFacility->count(),
                    $participantsByCounty->count(),
                    is_array($training->learning_outcomes)
                        ? implode('; ', $training->learning_outcomes)
                        : $training->learning_outcomes,
                    is_array($training->prerequisites)
                        ? implode('; ', $training->prerequisites)
                        : $training->prerequisites,
                    is_array($training->training_approaches)
                        ? implode('; ', $training->training_approaches)
                        : $training->training_approaches,
                    $training->created_at?->format('Y-m-d H:i'),
                    $training->notes
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export All Participants Data to CSV
     */
    protected function exportAllParticipantsToCsv(): StreamedResponse
    {
        $participants = \App\Models\TrainingParticipant::query()
            ->whereHas('training', function ($query) {
                $query->where('type', 'global_training');
            })
            ->with([
                'user.facility.subcounty.county.division',
                'user.department',
                'user.cadre',
                'training.programs',
                'training.modules.program',
                'training.methodologies',
                'training.organizer',
                'training.county',
                'training.partner',
                'objectiveResults.objective',
                'objectiveResults.grade',
                'outcome'
            ])
            ->get();

        $filename = 'global_training_participants_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($participants) {
            $file = fopen('php://output', 'w');

            // CSV Headers for Participant Data
            $headers = [
                'Training ID',
                'Training Title',
                'Training Status',
                'Training Lead Type',
                'Training Lead Organization',
                'Training Start Date',
                'Training End Date',
                'Training Location',
                'Organizer Name',
                'Programs',
                'Modules',
                'Methodologies',
                'Participant Name',
                'Phone Number',
                'Email',
                'ID Number',
                'Facility Name',
                'Facility MFL Code',
                'Department',
                'Cadre',
                'Subcounty',
                'County',
                'Division',
                'Registration Date',
                'Attendance Status',
                'Completion Status',
                'Completion Date',
                'Certificate Issued',
                'Overall Score (%)',
                'Overall Grade',
                'Pass/Fail Status',
                'Objectives Assessed',
                'Total Objectives',
                'Assessment Progress (%)',
                'Individual Objective Scores',
                'Assessment Feedback',
                'Final Outcome',
                'Participant Notes'
            ];

            fputcsv($file, $headers);

            foreach ($participants as $participant) {
                $user = $participant->user;
                $training = $participant->training;
                $facility = $user->facility;

                // Calculate assessment data
                $objectiveResults = $participant->objectiveResults;
                $totalObjectives = \App\Models\Objective::where('training_id', $training->id)->count();
                $assessedObjectives = $objectiveResults->count();
                $averageScore = $objectiveResults->avg('score');

                // Individual objective scores
                $individualScores = $objectiveResults->map(function ($result) {
                    return "{$result->objective->objective_text}: {$result->score}% ({$result->grade?->name})";
                })->implode('; ');

                // Assessment feedback
                $feedback = $objectiveResults->pluck('feedback')->filter()->implode('; ');

                // Pass/Fail determination
                $passFailStatus = 'Not Assessed';
                if ($averageScore !== null) {
                    $passFailStatus = $averageScore >= 70 ? 'PASS' : 'FAIL';
                }

                // Modules with program names
                $modules = $training->modules->map(function ($module) {
                    return "{$module->program->name} - {$module->name}";
                })->implode('; ');

                // Get lead organization based on lead type
                $leadOrganization = match ($training->lead_type) {
                    'national' => 'Ministry of Health',
                    'county' => $training->county?->name ?? 'Not specified',
                    'partner' => $training->partner?->name ?? 'Not specified',
                    default => 'Not specified',
                };

                $row = [
                    $training->identifier,
                    $training->title,
                    ucfirst($training->status),
                    ucfirst($training->lead_type),
                    $leadOrganization,
                    $training->start_date?->format('Y-m-d'),
                    $training->end_date?->format('Y-m-d'),
                    $training->location,
                    $training->organizer?->full_name,
                    $training->programs->pluck('name')->implode('; '),
                    $modules,
                    $training->methodologies->pluck('name')->implode('; '),
                    $user->full_name,
                    $user->phone,
                    $user->email,
                    $user->id_number,
                    $facility?->name,
                    $facility?->mfl_code,
                    $user->department?->name,
                    $user->cadre?->name,
                    $facility?->subcounty?->name,
                    $facility?->subcounty?->county?->name,
                    $facility?->subcounty?->county?->division?->name,
                    $participant->registration_date?->format('Y-m-d H:i'),
                    ucfirst($participant->attendance_status),
                    ucfirst($participant->completion_status),
                    $participant->completion_date?->format('Y-m-d'),
                    $participant->certificate_issued ? 'Yes' : 'No',
                    $averageScore ? number_format($averageScore, 1) : 'Not Assessed',
                    $participant->overall_grade,
                    $passFailStatus,
                    $assessedObjectives,
                    $totalObjectives,
                    $totalObjectives > 0 ? number_format(($assessedObjectives / $totalObjectives) * 100, 1) : '0',
                    $individualScores,
                    $feedback,
                    $participant->outcome?->name,
                    $participant->notes
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function getQuickStats(): array
    {
        $query = Training::where('type', 'global_training');

        return [
            'total' => $query->count(),
            'ongoing' => $query->where('status', 'ongoing')->count(),
            'participants' => $query->withCount('participants')->get()->sum('participants_count'),
        ];
    }

    protected function getTabCount(string $tab): int
    {
        $base    = Training::where('type', 'global_training');
        $trashed = Training::withTrashed()->where('type', 'global_training');

        return match ($tab) {
            'all'      => $base->count(),
            'national' => $base->where('lead_type', 'national')->count(),
            'county'   => $base->where('lead_type', 'county')->count(),
            'partner'  => $base->where('lead_type', 'partner')->count(),
            'trashed'  => $trashed->onlyTrashed()->count(),
            default    => 0,
        };
    }
}