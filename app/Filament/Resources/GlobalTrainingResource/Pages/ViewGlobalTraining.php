<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\Training;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewGlobalTraining extends ViewRecord {

    protected static string $resource = GlobalTrainingResource::class;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'global_training')
                ->with([
                    'mentor:id,first_name,last_name,cadre_id,facility_id',
                    'mentor.cadre:id,name',
                    'mentor.facility:id,name',
                    'organizer:id,first_name,last_name,department_id',
                    'organizer.department:id,name',
                    'division:id,name',
                    'county:id,name',
                    'partner:id,name',
                    'programs:id,name,description',
                    'modules:id,name,program_id,description',
                    'modules.program:id,name',
                    'methodologies:id,name,description',
                    'locations:id,name,type,address',
                    'assessmentCategories:id,name,description,assessment_method',
                    'trainingMaterials:id,training_id,inventory_item_id,quantity_planned,quantity_used,total_cost,usage_notes',
                    'trainingMaterials.inventoryItem:id,name'
                ])
                ->findOrFail($record);
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\EditAction::make()
                    ->color('warning'),
                    Actions\Action::make('manage_participants')
                    ->label('Manage Participants')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('participants', ['record' => $this->record])),
                    Actions\Action::make('manage_assessments')
                    ->label('Assessment Matrix')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn() => static::getResource()::getUrl('assessments', ['record' => $this->record]))
                    ->visible(fn() => $this->record->assess_participants === true),
                    Actions\Action::make('export_participants')
                    ->label('Export Participants')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        return $this->exportTrainingParticipants();
                    })
                    ->visible(fn() => $this->record->participants()->exists()),
                    Actions\DeleteAction::make()
                    ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Section::make('Training Overview')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('title')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                    TextEntry::make('identifier')
                                    ->label('Training ID')
                                    ->badge()
                                    ->color('primary'),
                                ]),
                                TextEntry::make('description')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'new' => 'gray',
                                                'ongoing' => 'success',
                                                'repeat' => 'warning',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'gray',
                                            }),
                                    TextEntry::make('type')
                                    ->label('Training Type')
                                    ->formatStateUsing(fn($state) => match ($state) {
                                                'global_training' => 'MOH Training',
                                                'facility_mentorship' => 'Facility Mentorship',
                                                default => ucfirst(str_replace('_', ' ', $state))
                                            })
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('max_participants')
                                    ->label('Max Participants')
                                    ->icon('heroicon-o-users'),
                                ]),
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('assess_participants')
                                    ->label('Assessment Enabled')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                    TextEntry::make('provide_materials')
                                    ->label('Materials Planned')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                                ]),
                            ]),
                            Section::make('Training Leadership')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('lead_type')
                                    ->label('Lead Type')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                                'national' => 'primary',
                                                'county' => 'success',
                                                'partner' => 'warning',
                                                default => 'gray',
                                            })
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                                'national' => 'National',
                                                'county' => 'County',
                                                'partner' => 'Partner Led',
                                                default => ucfirst($state),
                                            }),
                                    TextEntry::make('lead_organization')
                                    ->label('Lead Organization')
                                    ->getStateUsing(function ($record): string {
                                        return match ($record->lead_type) {
                                            'national' => $record->division?->name ?? 'Ministry of Health',
                                            'county' => $record->county?->name ?? 'County not specified',
                                            'partner' => $record->partner?->name ?? 'Partner not specified',
                                            default => 'Not specified',
                                        };
                                    })
                                    ->icon(fn($record): string => match ($record->lead_type) {
                                                'national' => 'heroicon-o-building-office-2',
                                                'county' => 'heroicon-o-map',
                                                'partner' => 'heroicon-o-building-office',
                                                default => 'heroicon-o-question-mark-circle',
                                            }),
                                ]),
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('mentor_info')
                                    ->label('Created By')
                                    ->getStateUsing(fn($record) =>
                                            $record->mentor ? "{$record->mentor->first_name} {$record->mentor->last_name}" .
                                            ($record->mentor->cadre ? " ({$record->mentor->cadre->name})" : "") : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Medium)
                                    ->helperText(fn($record) => $record->mentor?->facility?->name ?? 'No facility'),
                                    TextEntry::make('organizer_info')
                                    ->label('Training Coordinator')
                                    ->getStateUsing(fn($record) =>
                                            $record->organizer ? "{$record->organizer->first_name} {$record->organizer->last_name}" .
                                            ($record->organizer->department ? " ({$record->organizer->department->name})" : "") : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::Medium)
                                    ->visible(fn($record) => $record->organizer !== null),
                                ]),
                            ]),
                            Section::make('Schedule & Logistics')
                            ->schema([
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('start_date')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                    TextEntry::make('end_date')
                                    ->date('M j, Y')
                                    ->icon('heroicon-o-calendar'),
                                    TextEntry::make('duration_days')
                                    ->label('Duration')
                                    ->getStateUsing(fn($record) =>
                                            $record->start_date && $record->end_date ? $record->start_date->diffInDays($record->end_date) + 1 . ' days' : 'Not set'
                                    )
                                    ->icon('heroicon-o-clock'),
                                ]),
                                TextEntry::make('locations.name')
                                ->label('Training Locations')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('info')
                                ->getStateUsing(function ($record) {
                                    if ($record->locations && $record->locations->isNotEmpty()) {
                                        return $record->locations->map(function ($location) {
                                                    $address = $location->address ? " - {$location->address}" : "";
                                                    return "{$location->name}{$address}";
                                                })->toArray();
                                    }
                                    return ['No location specified'];
                                })
                                ->visible(fn($record) => $record->locations->isNotEmpty()),
                            ]),
                            Section::make('Content & Programs')
                            ->schema([
                                TextEntry::make('programs.name')
                                ->label('Programs')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('info')
                                ->visible(fn($record) => $record->programs->isNotEmpty()),
                                TextEntry::make('modules.name')
                                ->label('Modules')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('success')
                                ->formatStateUsing(function ($state, $record) {
                                    return $record->modules->map(function ($module) {
                                                return "{$module->program->name} - {$module->name}";
                                            })->toArray();
                                })
                                ->visible(fn($record) => $record->modules->isNotEmpty()),
                                TextEntry::make('methodologies.name')
                                ->label('Methodologies')
                                ->listWithLineBreaks()
                                ->badge()
                                ->color('warning')
                                ->visible(fn($record) => $record->methodologies->isNotEmpty()),
                                TextEntry::make('training_approaches')
                                ->label('Training Approaches')
                                ->listWithLineBreaks()
                                ->badge()
                                ->visible(fn($state) => !empty($state)),
                            ]),
                            Section::make('Learning Outcomes & Prerequisites')
                            ->schema([
                                TextEntry::make('learning_outcomes')
                                ->label('Expected Learning Outcomes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                TextEntry::make('prerequisites')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                            ])
                            ->collapsible()
                            ->visible(fn($record) => !empty($record->learning_outcomes) || !empty($record->prerequisites)),
                            // Assessment Framework Section (only visible if assess_participants is true)
                            Section::make('Assessment Framework')
                            ->schema([
                                RepeatableEntry::make('assessmentCategories')
                                ->label('Assessment Categories')
                                ->schema([
                                    Grid::make(4)
                                    ->schema([
                                        TextEntry::make('name')
                                        ->weight(FontWeight::Medium),
                                        TextEntry::make('pivot.weight_percentage')
                                        ->label('Weight')
                                        ->suffix('%')
                                        ->badge()
                                        ->color('warning'),
                                        TextEntry::make('pivot.pass_threshold')
                                        ->label('Pass Score')
                                        ->suffix('%')
                                        ->badge()
                                        ->color('info'),
                                        TextEntry::make('assessment_method')
                                        ->label('Method')
                                        ->badge()
                                        ->color('success'),
                                    ]),
                                    TextEntry::make('description')
                                    ->prose()
                                    ->columnSpanFull()
                                    ->visible(fn($state) => !empty($state)),
                                ])
                                ->contained(false)
                                ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                Grid::make(4)
                                ->schema([
                                    TextEntry::make('total_weight')
                                    ->label('Total Weight')
                                    ->getStateUsing(fn($record) =>
                                            $record->assessmentCategories->sum('pivot.weight_percentage') . '%'
                                    )
                                    ->badge()
                                    ->color(function ($record) {
                                        $total = $record->assessmentCategories->sum('pivot.weight_percentage');
                                        return abs($total - 100) < 0.1 ? 'success' : 'danger';
                                    })
                                    ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                    TextEntry::make('required_categories')
                                    ->label('Required Categories')
                                    ->getStateUsing(fn($record) =>
                                            $record->assessmentCategories->where('pivot.is_required', true)->count()
                                    )
                                    ->badge()
                                    ->color('warning')
                                    ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                                    TextEntry::make('participants_count')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn($record) => $record->participants()->count())
                                    ->badge()
                                    ->color('success'),
                                    TextEntry::make('assessment_completion')
                                    ->label('Assessment Progress')
                                    ->getStateUsing(function ($record) {
                                        if ($record->assessmentCategories->isEmpty()) {
                                            return 'No assessments configured';
                                        }
                                        $summary = $record->getAssessmentSummary();
                                        return $summary['completion_rate'] . '% Complete';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if ($record->assessmentCategories->isEmpty()) {
                                            return 'gray';
                                        }
                                        $summary = $record->getAssessmentSummary();
                                        $rate = $summary['completion_rate'];
                                        return $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                    }),
                                ])
                                ->visible(fn() => $this->record->assessmentCategories->isNotEmpty()),
                            ])
                            ->collapsible()
                            ->visible(fn() => $this->record->assess_participants === true),
                            // Training Materials Section (only visible if provide_materials is true)
                            Section::make('Training Materials & Resources')
                            ->schema([
                                RepeatableEntry::make('trainingMaterials')
                                ->label('Materials Used')
                                ->schema([
                                    Grid::make(5)
                                    ->schema([
                                        TextEntry::make('inventoryItem.name')
                                        ->label('Material')
                                        ->weight(FontWeight::Medium),
                                        TextEntry::make('quantity_planned')
                                        ->label('Planned')
                                        ->numeric(),
                                        TextEntry::make('quantity_used')
                                        ->label('Used')
                                        ->numeric()
                                        ->badge()
                                        ->color(fn($record) => match ($this->getMaterialStatus($record)) {
                                                    'As Planned' => 'success',
                                                    'Underutilized' => 'warning',
                                                    'Overused' => 'danger',
                                                    default => 'gray',
                                                }),
                                        TextEntry::make('total_cost')
                                        ->label('Cost')
                                        ->money('KES')
                                        ->badge()
                                        ->color('primary'),
                                        TextEntry::make('usage_percentage')
                                        ->label('Usage %')
                                        ->getStateUsing(fn($record) =>
                                                $record->quantity_planned > 0 ? round(($record->quantity_used / $record->quantity_planned) * 100, 1) . '%' : '0%'
                                        )
                                        ->badge(),
                                    ]),
                                    TextEntry::make('usage_notes')
                                    ->label('Notes')
                                    ->prose()
                                    ->visible(fn($state) => !empty($state))
                                    ->columnSpanFull(),
                                ])
                                ->contained(false)
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('total_material_cost')
                                    ->label('Total Material Cost')
                                    ->getStateUsing(fn($record) => 'KES ' . number_format($record->trainingMaterials->sum('total_cost'), 2))
                                    ->badge()
                                    ->color('primary'),
                                    TextEntry::make('materials_count')
                                    ->label('Materials Planned')
                                    ->getStateUsing(fn($record) => $record->trainingMaterials->count() . ' items')
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('material_utilization')
                                    ->label('Overall Utilization')
                                    ->getStateUsing(function ($record) {
                                        $materials = $record->trainingMaterials;
                                        if ($materials->isEmpty())
                                            return '0%';

                                        $totalPlanned = $materials->sum('quantity_planned');
                                        $totalUsed = $materials->sum('quantity_used');

                                        return $totalPlanned > 0 ? round(($totalUsed / $totalPlanned) * 100, 1) . '%' : '0%';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        $materials = $record->trainingMaterials;
                                        if ($materials->isEmpty())
                                            return 'gray';

                                        $totalPlanned = $materials->sum('quantity_planned');
                                        $totalUsed = $materials->sum('quantity_used');
                                        $utilization = $totalPlanned > 0 ? ($totalUsed / $totalPlanned) * 100 : 0;

                                        return $utilization >= 80 ? 'success' : ($utilization >= 60 ? 'warning' : 'danger');
                                    }),
                                ])
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                            ])
                            ->collapsible()
                            ->visible(fn() => $this->record->provide_materials === true),
                            Section::make('Participant Statistics')
                            ->schema([
                                Grid::make(4)
                                ->schema([
                                    TextEntry::make('participants_count')
                                    ->label('Total Participants')
                                    ->getStateUsing(fn($record) => $record->participants()->count())
                                    ->badge()
                                    ->color('success'),
                                    TextEntry::make('completion_rate')
                                    ->label('Completion Rate')
                                    ->suffix('%')
                                    ->badge()
                                    ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                                    TextEntry::make('facilities_represented')
                                    ->label('Facilities Represented')
                                    ->getStateUsing(fn($record) => $record->participants()->with('user.facility')->get()->pluck('user.facility.name')->filter()->unique()->count())
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('average_score')
                                    ->label('Average Score')
                                    ->suffix('%')
                                    ->badge()
                                    ->color('primary'),
                                ]),
                            ]),
                            Section::make('Additional Information')
                            ->schema([
                                TextEntry::make('notes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                    TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
        ]);
    }

    private function getMaterialStatus($material): string {
        if ($material->quantity_planned <= 0)
            return 'Unknown';

        $usagePercent = ($material->quantity_used / $material->quantity_planned) * 100;

        if ($usagePercent == 0)
            return 'Not Used';
        if ($usagePercent < 50)
            return 'Underutilized';
        if ($usagePercent <= 110)
            return 'As Planned';
        return 'Overused';
    }

    /**
     * Export participants for this specific training
     */
    protected function exportTrainingParticipants(): StreamedResponse {
        $participants = $this->record->participants()
                ->with([
                    'user.facility.subcounty.county.division',
                    'user.department',
                    'user.cadre',
                    'training.programs',
                    'training.modules.program',
                    'training.methodologies',
                    'training.mentor',
                    'training.county',
                    'training.partner',
                    'assessmentResults.assessmentCategory',
                    'outcome'
                ])
                ->get();

        $filename = "training_participants_{$this->record->identifier}_" . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($participants) {
                    $file = fopen('php://output', 'w');

                    // CSV Headers
                    $headers = [
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
                        'Overall Assessment Score (%)',
                        'Overall Assessment Status',
                        'Assessment Categories Completed',
                        'Total Assessment Categories',
                        'Assessment Progress (%)',
                        'Individual Category Results',
                        'Final Outcome',
                        'Participant Notes'
                    ];

                    fputcsv($file, $headers);

                    $totalCategories = $this->record->assessmentCategories()->count();

                    foreach ($participants as $participant) {
                        $user = $participant->user;
                        $facility = $user->facility;
                        $assessmentResults = $participant->assessmentResults;
                        $assessedCategories = $assessmentResults->count();

                        // Calculate overall assessment score and status
                        $overallCalculation = $this->record->calculateOverallScore($participant);

                        // Individual category results
                        $categoryResults = $assessmentResults->map(function ($result) {
                                    return "{$result->assessmentCategory->name}: {$result->result}";
                                })->implode('; ');

                        $row = [
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
                            $totalCategories > 0 ? number_format($overallCalculation['score'], 1) : 'N/A',
                            $totalCategories > 0 ? $overallCalculation['status'] : 'No Assessments',
                            $assessedCategories,
                            $totalCategories,
                            $totalCategories > 0 ? number_format(($assessedCategories / $totalCategories) * 100, 1) : '0',
                            $categoryResults,
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
}