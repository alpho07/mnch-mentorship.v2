<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Models\MentorshipClass;
use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\ClassParticipant;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ViewMentorshipTraining extends ViewRecord {

    protected static string $resource = MentorshipTrainingResource::class;

    public function mount(int|string $record): void {
        $this->record = Training::where('type', 'facility_mentorship')
                ->with([
                    'facility:id,name',
                    'mentor:id,first_name,last_name,cadre_id',
                    'mentor.cadre:id,name',
                    'organizer:id,first_name,last_name,department_id',
                    'organizer.department:id,name',
                    'programs:id,name,description',
                    'modules:id,name,program_id,description',
                    'modules.program:id,name',
                    'methodologies:id,name,description',
                    'assessmentCategories:id,name,description,assessment_method',
                    'trainingMaterials:id,training_id,inventory_item_id,quantity_planned,quantity_used,total_cost,usage_notes',
                    'trainingMaterials.inventoryItem:id,name',
                ])
                ->findOrFail($record);
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\EditAction::make()
                    ->color('warning'),
                    Actions\Action::make('manage_classes')
                    ->label('Manage Classes')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('classes', ['record' => $this->record])),
                    Actions\Action::make('manage_co_mentors')
                    ->label('Co-Mentors')
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->url(fn() => static::getResource()::getUrl('co-mentors', ['record' => $this->record])),
                    Actions\Action::make('manage_mentees')
                    ->label('Mentees')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn() => static::getResource()::getUrl('mentees', ['record' => $this->record])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            // ==========================================
                            // MENTORSHIP PROGRESS TRACKER
                            // ==========================================
                            Section::make('Mentorship Progress')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Grid::make(5)
                                ->schema([
                                    TextEntry::make('progress_classes')
                                    ->label('① Classes')
                                    ->getStateUsing(function ($record) {
                                        $total = $record->mentorshipClasses()->count();
                                        $active = $record->mentorshipClasses()->where('status', 'active')->count();
                                        $completed = $record->mentorshipClasses()->where('status', 'completed')->count();
                                        return "{$total} total ({$active} active, {$completed} done)";
                                    })
                                    ->icon('heroicon-o-rectangle-group')
                                    ->badge()
                                    ->color(fn($record) =>
                                            $record->mentorshipClasses()->count() > 0 ? 'success' : 'danger'
                                    ),
                                    TextEntry::make('progress_modules')
                                    ->label('② Modules')
                                    ->getStateUsing(function ($record) {
                                        $total = ClassModule::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        $completed = ClassModule::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->where('status', 'completed')->count();
                                        return $total > 0 ? "{$completed}/{$total} completed" : 'None added';
                                    })
                                    ->icon('heroicon-o-book-open')
                                    ->badge()
                                    ->color(function ($record) {
                                        $total = ClassModule::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        return $total > 0 ? 'success' : 'danger';
                                    }),
                                    TextEntry::make('progress_sessions')
                                    ->label('③ Sessions')
                                    ->getStateUsing(function ($record) {
                                        $total = ClassSession::whereHas('classModule.mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        $completed = ClassSession::whereHas('classModule.mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->where('status', 'completed')->count();
                                        return $total > 0 ? "{$completed}/{$total} completed" : 'None scheduled';
                                    })
                                    ->icon('heroicon-o-calendar')
                                    ->badge()
                                    ->color(function ($record) {
                                        $total = ClassSession::whereHas('classModule.mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        return $total > 0 ? 'success' : 'warning';
                                    }),
                                    TextEntry::make('progress_mentees')
                                    ->label('④ Mentees')
                                    ->getStateUsing(function ($record) {
                                        $total = ClassParticipant::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        $unique = ClassParticipant::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->distinct('user_id')->count('user_id');
                                        return $total > 0 ? "{$unique} mentees ({$total} enrollments)" : 'None enrolled';
                                    })
                                    ->icon('heroicon-o-users')
                                    ->badge()
                                    ->color(function ($record) {
                                        $total = ClassParticipant::whereHas('mentorshipClass', fn($q) =>
                                                        $q->where('training_id', $record->id)
                                                )->count();
                                        return $total > 0 ? 'success' : 'warning';
                                    }),
                                    TextEntry::make('progress_comentors')
                                    ->label('⑤ Co-Mentors')
                                    ->getStateUsing(function ($record) {
                                        $accepted = $record->coMentors()->where('status', 'accepted')->count();
                                        $pending = $record->coMentors()->where('status', 'pending')->count();
                                        if ($accepted + $pending === 0)
                                            return 'None invited';
                                        return "{$accepted} active" . ($pending > 0 ? ", {$pending} pending" : '');
                                    })
                                    ->icon('heroicon-o-user-group')
                                    ->badge()
                                    ->color(function ($record) {
                                        $any = $record->coMentors()
                                                ->whereIn('status', ['accepted', 'pending'])
                                                ->count();
                                        return $any > 0 ? 'info' : 'gray';
                                    }),
                                ]),
                                // Per-class breakdown
                                TextEntry::make('class_breakdown')
                                ->label('Class Details')
                                ->getStateUsing(function ($record) {
                                    $classes = $record->mentorshipClasses()
                                            ->withCount(['classModules', 'participants'])
                                            ->get();

                                    if ($classes->isEmpty()) {
                                        return 'No classes created yet. Click "Manage Classes" to get started.';
                                    }

                                    $lines = [];
                                    foreach ($classes as $class) {
                                        $sessionCount = ClassSession::whereHas('classModule', fn($q) =>
                                                        $q->where('mentorship_class_id', $class->id)
                                                )->count();

                                        $statusIcon = match ($class->status) {
                                            'active' => '🟢',
                                            'completed' => '✅',
                                            'cancelled' => '🔴',
                                            default => '⚪',
                                        };

                                        $lines[] = "{$statusIcon} {$class->name}: {$class->class_modules_count} modules, {$sessionCount} sessions, {$class->participants_count} mentees";
                                    }

                                    return implode("\n", $lines);
                                })
                                ->columnSpanFull(),
                            ]),
                            // ==========================================
                            // MENTORSHIP OVERVIEW
                            // ==========================================
                            Section::make('Mentorship Overview')
                            ->schema([
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('title')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                    TextEntry::make('identifier')
                                    ->label('Mentorship ID')
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
                                                'new' => 'New',
                                                'ongoing' => 'success',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'new',
                                            }),
                                    TextEntry::make('facility.name')
                                    ->label('Facility')
                                    ->icon('heroicon-o-building-office-2')
                                    ->badge()
                                    ->color('info'),
                                    TextEntry::make('max_participants')
                                    ->label('Max Mentees')
                                    ->icon('heroicon-o-users'),
                                ]),
                            ]),
                            Section::make('Schedule & Team')
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
                                Grid::make(2)
                                ->schema([
                                    TextEntry::make('mentor_info')
                                    ->label('Lead Mentor')
                                    ->getStateUsing(fn($record) =>
                                            $record->mentor ? "{$record->mentor->first_name} {$record->mentor->last_name}" .
                                            ($record->mentor->cadre ? " ({$record->mentor->cadre->name})" : '') : 'Not assigned'
                                    )
                                    ->icon('heroicon-o-academic-cap')
                                    ->weight(FontWeight::Medium),
                                    TextEntry::make('lead_organization')
                                    ->label('Lead Organization')
                                    ->getStateUsing(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => $record->division?->name ?? 'Ministry of Health',
                                            'county' => $record->county?->name ?? 'County not specified',
                                            'partner' => $record->partner?->name ?? 'Partner not specified',
                                            default => 'Not specified',
                                        };
                                    })
                                    ->icon(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'heroicon-o-building-office-2',
                                            'county' => 'heroicon-o-map-pin',
                                            'partner' => 'heroicon-o-users',
                                            default => 'heroicon-o-question-mark-circle',
                                        };
                                    })
                                    ->badge(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'National',
                                            'county' => 'County',
                                            'partner' => 'Partner',
                                            default => 'Unknown',
                                        };
                                    })
                                    ->color(function ($record) {
                                        return match ($record->lead_type) {
                                            'national' => 'primary',
                                            'county' => 'success',
                                            'partner' => 'warning',
                                            default => 'gray',
                                        };
                                    })
                                    ->weight(FontWeight::Medium),
                                ]),
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
                                ->label('Approaches')
                                ->listWithLineBreaks()
                                ->badge()
                                ->visible(fn($state) => !empty($state)),
                            ]),
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
                                Section::make('Mentee Statistics')
                                ->schema([
                                    Grid::make(4)
                                    ->schema([
                                        TextEntry::make('participants_count')
                                        ->label('Total Mentees')
                                        ->getStateUsing(fn($record) => $record->participants()->count())
                                        ->badge()
                                        ->color('success'),
                                        TextEntry::make('completion_rate')
                                        ->label('Completion Rate')
                                        ->suffix('%')
                                        ->badge()
                                        ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                                        TextEntry::make('departments_represented')
                                        ->label('Departments Represented')
                                        ->getStateUsing(fn($record) =>
                                                $record->participants()->with('user.department')->get()
                                                ->pluck('user.department.name')->filter()->unique()->count()
                                        )
                                        ->badge()
                                        ->color('info'),
                                        TextEntry::make('assessment_categories_count')
                                        ->label('Assessment Categories')
                                        ->getStateUsing(fn($record) => $record->assessmentCategories()->count())
                                        ->badge()
                                        ->color('warning'),
                                    ]),
                                ]),
                            ])
                            ->collapsible(),
                            Section::make('Mentorship Materials & Resources')
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
                                TextEntry::make('total_material_cost')
                                ->label('Total Material Cost')
                                ->getStateUsing(fn($record) => 'KES ' . number_format($record->trainingMaterials->sum('total_cost'), 2))
                                ->badge()
                                ->color('primary')
                                ->visible(fn() => $this->record->trainingMaterials->isNotEmpty()),
                            ])
                            ->collapsible(),
                            Section::make('Additional Information')
                            ->schema([
                                TextEntry::make('notes')
                                ->prose()
                                ->columnSpanFull()
                                ->visible(fn($state) => !empty($state)),
                                Grid::make(3)
                                ->schema([
                                    TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                    TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                                    TextEntry::make('facility_assessment_status')
                                    ->label('Facility Assessment')
                                    ->getStateUsing(function ($record) {
                                        $assessment = \App\Models\FacilityAssessment::where('facility_id', $record->facility_id)
                                                ->where('status', 'approved')
                                                ->where('next_assessment_due', '>', now())
                                                ->latest()
                                                ->first();

                                        return $assessment ? 'Valid until ' . $assessment->next_assessment_due->format('M j, Y') : 'No valid assessment';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        $assessment = \App\Models\FacilityAssessment::where('facility_id', $record->facility_id)
                                                ->where('status', 'approved')
                                                ->where('next_assessment_due', '>', now())
                                                ->latest()
                                                ->first();

                                        return $assessment ? 'success' : 'danger';
                                    }),
                                ]),
                            ])
                            ->collapsible(),
        ]);
    }

    private function getMaterialStatus($material): string {
        if ($material->quantity_planned <= 0) {
            return 'Unknown';
        }

        $usagePercent = ($material->quantity_used / $material->quantity_planned) * 100;

        if ($usagePercent == 0)
            return 'Not Used';
        if ($usagePercent < 50)
            return 'Underutilized';
        if ($usagePercent <= 110)
            return 'As Planned';
        return 'Overused';
    }

    public function getTitle(): string {
        return 'View Mentorship';
    }
}
