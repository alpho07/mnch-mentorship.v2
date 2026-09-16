<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;

class ManageModuleAttendance extends Page implements HasTable {

    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-attendance';
    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load(['programModule', 'mentorshipClass']);
    }

    public function getTitle(): string {
        return "Module Attendance - {$this->module->programModule->name}";
    }

    public function getSubheading(): ?string {
        return "{$this->module->mentorshipClass->name} • Override attendance and completion status";
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\Action::make('mark_all_attended')
                    ->label('Mark All as Attended')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark All Mentees as Attended')
                    ->modalDescription('This will mark all non-exempted mentees as having attended this module.')
                    ->action(function () {
                        $count = MenteeModuleProgress::whereHas('classParticipant', function ($query) {
                                    $query->where('mentorship_class_id', $this->module->mentorship_class_id);
                                })
                                ->where('class_module_id', $this->module->id)
                                ->where('status', '!=', 'exempted')
                                ->update([
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                    'attendance_percentage' => 100,
                        ]);

                        Notification::make()
                                ->success()
                                ->title('Bulk Update Complete')
                                ->body("{$count} mentees marked as attended")
                                ->send();
                    }),
                    Actions\Action::make('back')
                    ->label('Back to Sessions')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(fn() => MentorshipTrainingResource::getUrl('module-sessions', [
                                'training' => $this->training,
                                'class' => $this->class,
                                'module' => $this->module->id,
                            ])),
        ];
    }

    public function table(Table $table): Table {
        return $table
                        ->query(
                                ClassParticipant::query()
                                ->where('mentorship_class_id', $this->module->mentorship_class_id)
                                ->with([
                                    'user.facility',
                                    'moduleProgress' => function ($query) {
                                        $query->where('class_module_id', $this->module->id);
                                    }
                                ])
                        )
                        ->columns([
                            Tables\Columns\TextColumn::make('user.full_name')
                            ->label('Mentee')
                            ->searchable(['first_name', 'last_name'])
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('user.facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->toggleable(),
                            Tables\Columns\BadgeColumn::make('progress_status')
                            ->label('Module Status')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                if (!$progress)
                                    return 'not_enrolled';
                                return $progress->status;
                            })
                            ->colors([
                                'secondary' => 'not_started',
                                'info' => 'exempted',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'not_enrolled',
                            ])
                            ->formatStateUsing(fn(string $state): string =>
                                    match ($state) {
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'exempted' => 'Exempted',
                                        'not_enrolled' => 'Not Enrolled',
                                        default => ucfirst($state),
                                    }
                            ),
                            Tables\Columns\IconColumn::make('is_exempted')
                            ->label('Exempted')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->is_exempted ?? false;
                            })
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('info')
                            ->falseColor('gray'),
                            Tables\Columns\TextColumn::make('attendance_percentage')
                            ->label('Attendance')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->attendance_percentage ?? 0;
                            })
                            ->suffix('%')
                            ->badge()
                            ->color(fn($state) => $state >= 80 ? 'success' : ($state >= 60 ? 'warning' : 'danger')),
                            Tables\Columns\TextColumn::make('assessment_score')
                            ->label('Assessment')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->assessment_score ?
                                        number_format($progress->assessment_score, 1) . '%' : 'Not assessed';
                            })
                            ->badge()
                            ->color(fn($state) => $state === 'Not assessed' ? 'gray' : 'primary'),
                            Tables\Columns\TextColumn::make('completed_at')
                            ->label('Completed')
                            ->getStateUsing(function ($record) {
                                $progress = $record->moduleProgress->first();
                                return $progress?->completed_at?->format('M j, Y') ?? '-';
                            }),
                        ])
                        ->filters([
                            Tables\Filters\SelectFilter::make('status')
                            ->options([
                                'not_started' => 'Not Started',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'exempted' => 'Exempted',
                            ])
                            ->query(function ($query, $state) {
                                if (!$state['value'])
                                    return $query;

                                return $query->whereHas('moduleProgress', function ($q) use ($state) {
                                            $q->where('class_module_id', $this->module->id)
                                                    ->where('status', $state['value']);
                                        });
                            }),
                        ])
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\Action::make('override_attendance')
                                ->label('Override Status')
                                ->icon('heroicon-o-pencil')
                                ->color('warning')
                                ->form([
                                    Forms\Components\Select::make('status')
                                    ->label('Module Status')
                                    ->options([
                                        'not_started' => 'Not Started',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                    ])
                                    ->required(),
                                    Forms\Components\TextInput::make('attendance_percentage')
                                    ->label('Attendance Percentage')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(100),
                                    Forms\Components\DateTimePicker::make('completed_at')
                                    ->label('Completion Date')
                                    ->visible(fn(Forms\Get $get) => $get('status') === 'completed'),
                                    Forms\Components\Textarea::make('notes')
                                    ->label('Override Reason/Notes')
                                    ->rows(3)
                                    ->placeholder('Why are you manually overriding this?'),
                                ])
                                ->fillForm(function ($record) {
                                    $progress = $record->moduleProgress->first();
                                    return [
                                        'status' => $progress?->status ?? 'not_started',
                                        'attendance_percentage' => $progress?->attendance_percentage ?? 0,
                                        'completed_at' => $progress?->completed_at,
                                        'notes' => $progress?->notes,
                                    ];
                                })
                                ->action(function ($record, array $data) {
                                    $progress = MenteeModuleProgress::firstOrCreate(
                                            [
                                                'class_participant_id' => $record->id,
                                                'class_module_id' => $this->module->id,
                                            ],
                                            [
                                                'status' => 'not_started',
                                            ]
                                    );

                                    $progress->update([
                                        'status' => $data['status'],
                                        'attendance_percentage' => $data['attendance_percentage'] ?? null,
                                        'completed_at' => $data['status'] === 'completed' ?
                                        ($data['completed_at'] ?? now()) : null,
                                        'notes' => $data['notes'] ?? null,
                                    ]);

                                    Notification::make()
                                            ->success()
                                            ->title('Status Updated')
                                            ->body("Module status updated for {$record->user->full_name}")
                                            ->send();
                                }),
                                Tables\Actions\Action::make('mark_exempted')
                                ->label('Mark as Exempted')
                                ->icon('heroicon-o-shield-check')
                                ->color('info')
                                ->visible(function ($record) {
                                    $progress = $record->moduleProgress->first();
                                    return !$progress?->is_exempted;
                                })
                                ->requiresConfirmation()
                                ->action(function ($record) {
                                    $progress = MenteeModuleProgress::firstOrCreate(
                                            [
                                                'class_participant_id' => $record->id,
                                                'class_module_id' => $this->module->id,
                                            ],
                                            [
                                                'status' => 'not_started',
                                            ]
                                    );

                                    $progress->update([
                                        'status' => 'exempted',
                                        'exempted_at' => now(),
                                    ]);

                                    Notification::make()
                                            ->success()
                                            ->title('Mentee Exempted')
                                            ->send();
                                }),
                                Tables\Actions\Action::make('remove_exemption')
                                ->label('Remove Exemption')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->visible(function ($record) {
                                    $progress = $record->moduleProgress->first();
                                    return $progress?->status === 'exempted' &&
                                            !$progress?->completed_in_previous_class;
                                })
                                ->requiresConfirmation()
                                ->action(function ($record) {
                                    $progress = $record->moduleProgress->first();
                                    $progress->update([
                                        'status' => 'not_started',
                                        'exempted_at' => null,
                                    ]);

                                    Notification::make()
                                            ->success()
                                            ->title('Exemption Removed')
                                            ->send();
                                }),
                            ]),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkAction::make('mark_completed')
                            ->label('Mark as Completed')
                            ->icon('heroicon-o-check')
                            ->color('success')
                            ->action(function ($records) {
                                $count = 0;
                                foreach ($records as $record) {
                                    MenteeModuleProgress::updateOrCreate(
                                            [
                                                'class_participant_id' => $record->id,
                                                'class_module_id' => $this->module->id,
                                            ],
                                            [
                                                'status' => 'completed',
                                                'completed_at' => now(),
                                                'attendance_percentage' => 100,
                                            ]
                                    );
                                    $count++;
                                }

                                Notification::make()
                                        ->success()
                                        ->title('Bulk Update Complete')
                                        ->body("{$count} mentees marked as completed")
                                        ->send();
                            }),
        ]);
    }
}
