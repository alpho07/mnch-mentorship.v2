<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\ActivityCompletionMatrix;
use App\Filament\Forms\Components\ActivityEnrollmentMatrix;
use App\Filament\Forms\Components\CardCheckboxList;
use App\Filament\Forms\Components\EmoncModulePicker;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Services\EmoncNotificationService;
use App\Services\ModuleUsageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ManageClassModules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-class-modules';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public function mount(Training $training, MentorshipClass $class): void
    {
        $this->training = $training;
        $this->class = $class->load(['training', 'classModules.programModule']);
    }

    public function getTitle(): string
    {
        return "Class > Modules — {$this->class->name}";
    }

    public function getSubheading(): ?string
    {
        $service = app(ModuleUsageService::class);
        $assigned = $this->class->classModules()->count();

        if ($this->isEmonc()) {
            $available = EmoncModulePicker::make('module_ids')
                ->training($this->training)
                ->class($this->class)
                ->getModules()
                ->sum(fn ($module) => $module->availableChildren?->count() ?? ($module->children?->count() ?? 0));

            return "{$assigned} class module(s) assigned · {$available} track/module(s) available to add";
        }

        $available = $service->getAvailableModules($this->training, $this->class)->count();

        return "{$assigned} class module(s) assigned · {$available} program module(s) available to add";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Header Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\MentorshipSetupNotice::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        $service = app(ModuleUsageService::class);
        $availableModules = $this->getModuleOptions($service);
        $hasAvailableModules = $this->isEmonc()
            ? EmoncModulePicker::make('module_ids')
                ->training($this->training)
                ->class($this->class)
                ->getModules()
                ->isNotEmpty()
            : count($availableModules) > 0;

        return [
            Actions\Action::make('back_to_class')
                ->label('Back to Classes')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('classes', ['record' => $this->training->id])),
            Actions\Action::make('add_modules')
                ->label('Add Modules')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn () => $hasAvailableModules && $this->class->status !== 'completed')
                ->slideOver()
                ->modalWidth('4xl')
                ->extraModalWindowAttributes([
                    'class' => 'scrollable-module-slideover',
                    'style' => 'height: 100vh; max-height: 100vh;',
                ])
                ->modalHeading('Add Modules to Class')
                ->modalDescription('A module can only be added once to this class, but it can be used in other classes.')
                ->form(fn () => array_merge(
                    $this->isEmonc()
                        ? $this->emoncModulePickerSchema()
                        : $this->standardModulePickerSchema(),
                    [
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('module_start_date')
                                ->label('Start Date')
                                ->native(false)
                                ->minDate(today()),
                            Forms\Components\DatePicker::make('module_end_date')
                                ->label('End Date')
                                ->native(false)
                                ->minDate(today())
                                ->afterOrEqual('module_start_date'),
                        ]),
                        Forms\Components\Toggle::make('auto_create_sessions')
                            ->label('Auto-populate sessions from program template')
                            ->default(true)
                            ->disabled()
                            ->dehydrated(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->rows(2),
                    ]
                ))
                ->action(function (array $data) use ($service) {
                    $createdModuleIds = [];
                    $created = $service->assignModulesToClass(
                        $this->training,
                        $this->class,
                        $data['module_ids'],
                        null,
                        function (ClassModule $classModule) use (&$createdModuleIds) {
                            $createdModuleIds[] = $classModule->id;
                        }
                    );

                    if ($created > 0 && (! empty($data['module_start_date']) || ! empty($data['module_end_date']))) {
                        ClassModule::whereIn('id', $createdModuleIds)->update([
                            'start_date' => $data['module_start_date'] ?? null,
                            'end_date' => $data['module_end_date'] ?? null,
                        ]);
                    }

                    // Auto-enroll existing participants into activities for newly added modules
                    if ($created > 0 && ! empty($createdModuleIds)) {
                        $newModules = ClassModule::with('programModule.activities')
                            ->whereIn('id', $createdModuleIds)
                            ->get();

                        $participants = ClassParticipant::where('mentorship_class_id', $this->class->id)
                            ->whereIn('status', ['enrolled', 'active'])
                            ->pluck('id');

                        $rows = [];
                        foreach ($newModules as $classModule) {
                            $activities = $classModule->programModule?->activities ?? collect();
                            foreach ($activities as $activity) {
                                foreach ($participants as $participantId) {
                                    $rows[] = [
                                        'class_module_id' => $classModule->id,
                                        'class_participant_id' => $participantId,
                                        'activity_id' => $activity->id,
                                        'status' => 'pending',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                }
                            }
                        }
                        if (! empty($rows)) {
                            ClassModuleActivityParticipant::insertOrIgnore($rows);
                        }
                    }

                    $createdSessions = 0;

                    if (($data['auto_create_sessions'] ?? true) && $created > 0) {
                        $this->class->load('classModules');
                        foreach ($this->class->classModules as $cm) {
                            if (method_exists($cm, 'autoCreateSessions')) {
                                $createdSessions += (int) $cm->autoCreateSessions();
                            }
                        }
                    }

                    $sessionText = $createdSessions > 0 ? " with {$createdSessions} sessions auto-created" : '';
                    Notification::make()->success()->title("{$created} class module(s) added{$sessionText}")->send();
                }),
            Actions\Action::make('manage_class_mentees')
                ->label('Add Mentees')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn () => $this->class->status !== 'completed')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-mentees', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                ])),
            Actions\Action::make('view_report')
                ->label('Class Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->url(fn () => route('reports.class.html', $this->class->id))
                ->openUrlInNewTab(),
        ];
    }

    private function standardModulePickerSchema(): array
    {
        $available = app(ModuleUsageService::class)
            ->getAvailableModules($this->training, $this->class)
            ->mapWithKeys(fn ($module) => [$module->id => $module->name])
            ->toArray();

        return [
            CardCheckboxList::make('module_ids')
                ->label('Available Program Modules')
                ->options($available)
                ->required()
                ->helperText('Modules already added to this class are excluded. Modules used in other classes remain available.'),
        ];
    }

    private function emoncModulePickerSchema(): array
    {
        return [
            EmoncModulePicker::make('module_ids')
                ->label('Available Program Modules')
                ->training($this->training)
                ->class($this->class)
                ->required()
                ->helperText('Click a module to select all its tracks, or pick tracks individually. The module header ticks when any of its tracks are selected.'),
        ];
    }

    private function isEmonc(): bool
    {
        $program = Program::find($this->training->program_id);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClassModule::query()
                    ->with(['programModule.parent', 'programModule.activities', 'sessions', 'menteeProgress'])
                    ->where('mentorship_class_id', $this->class->id)
                    ->orderBy('order_sequence')
            )
            ->reorderable('order_sequence')
            ->defaultSort('order_sequence')
            ->columns([
                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('#')
                    ->width(40)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'not_started' => 'Not Started',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'not_started' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'not_started' => 'heroicon-m-clock',
                        'in_progress' => 'heroicon-m-play',
                        'completed' => 'heroicon-m-check-circle',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('module_name')
                    ->label($this->isEmonc() ? 'Module / Track' : 'Module')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('programModule', fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhereHas('parent', fn ($pq) => $pq->where('name', 'like', "%{$search}%")
                            )
                        );
                    })
                    ->weight('medium')
                    ->html()
                    ->getStateUsing(function (ClassModule $record) {
                        $programModule = $record->programModule;

                        if (! $programModule) {
                            return 'Module';
                        }

                        $trackName = e($programModule->name);

                        if ($programModule->parent) {
                            $parentName = e($programModule->parent->name);

                            return "<span class='text-primary-600 dark:text-primary-400 font-semibold'>{$parentName}</span><br><span class='text-sm text-gray-600 dark:text-gray-400'>{$trackName}</span>";
                        }

                        return $trackName;
                    })
                    ->description(fn (ClassModule $record) => $record->programModule?->description ? \Illuminate\Support\Str::limit($record->programModule->description, 80) : null),
                Tables\Columns\TextColumn::make($this->isEmonc() ? 'activities_count' : 'sessions_count')
                    ->label($this->isEmonc() ? 'Activities' : 'Sessions')
                    ->getStateUsing(fn (ClassModule $record) => $this->isEmonc()
                        ? $record->programModule?->activities?->count() ?? 0
                        : $record->sessions->count())
                    ->alignCenter()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d M Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End')
                    ->date('d M Y')
                    ->placeholder('—'),
                // ── Attendance ────────────────────────────────────────────────
                // Uses MenteeModuleProgress (status in_progress|completed) as the
                // source of truth — correct for both new records (class_module_id
                // set on ClassAttendance) and legacy records (class_module_id = null).
                Tables\Columns\TextColumn::make('attendance_summary')
                    ->label('Attendance')
                    ->getStateUsing(function (ClassModule $record) {
                        if ($record->status === 'not_started') {
                            return '—';
                        }

                        $confirmed = MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereIn('status', ['in_progress', 'completed'])
                            ->count();

                        $total = ClassParticipant::where('mentorship_class_id', $record->mentorship_class_id)
                            ->whereIn('status', ['enrolled', 'active'])
                            ->count();

                        if ($total === 0) {
                            return '—';
                        }

                        $pct = round(($confirmed / $total) * 100);

                        return "{$confirmed}/{$total} ({$pct}%)";
                    })
                    ->color(function (ClassModule $record) {
                        if ($record->status === 'not_started') {
                            return 'gray';
                        }
                        $confirmed = MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereIn('status', ['in_progress', 'completed'])
                            ->count();

                        return $confirmed > 0 ? 'success' : 'danger';
                    })
                    ->icon(function (ClassModule $record) {
                        if ($record->status === 'not_started') {
                            return null;
                        }
                        $confirmed = MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereIn('status', ['in_progress', 'completed'])
                            ->count();

                        return $confirmed > 0 ? 'heroicon-m-check-circle' : null;
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->date('d M Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->date('d M Y')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('start_module')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->button()
                    ->visible(fn (ClassModule $record) => $record->status === 'not_started')
                    ->requiresConfirmation()
                    ->modalHeading(function (ClassModule $record) {
                        $missingDates = $this->isEmonc() && (empty($record->start_date) || empty($record->end_date));
                        if ($missingDates) {
                            return '⚠️ Module Dates Required';
                        }

                        $hasMentees = ClassParticipant::where('mentorship_class_id', $this->class->id)->exists();

                        return $hasMentees ? 'Start "'.($record->programModule?->name ?? 'Module').'"?' : '⚠️ No Mentees Enrolled';
                    })
                    ->modalDescription(function (ClassModule $record) {
                        // ── EmONC: require dates before starting ─────────────────────────
                        $missingDates = $this->isEmonc() && (empty($record->start_date) || empty($record->end_date));
                        if ($missingDates) {
                            $missing = collect([
                                empty($record->start_date) ? 'Start Date' : null,
                                empty($record->end_date) ? 'End Date' : null,
                            ])->filter()->implode(' and ');

                            return new \Illuminate\Support\HtmlString('
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#713f12;line-height:1.6;">
                        <strong>Cannot start this module — '.$missing.' is not set.</strong><br>
                        EmONC modules require a start and end date so mentees can see their scheduled session.
                    </div>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#1e40af;line-height:1.7;">
                        <strong>How to fix:</strong><br>
                        Click <strong>Cancel</strong> below, then use the <strong>✏️ pencil (Edit) icon</strong> on this module row to set the <strong>'.$missing.'</strong>, then come back to start the module.
                    </div>
                </div>
            ');
                        }

                        $menteeCount = ClassParticipant::where('mentorship_class_id', $this->class->id)->count();

                        if ($menteeCount === 0) {
                            return new \Illuminate\Support\HtmlString('
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#713f12;line-height:1.6;">
                        <strong>You cannot start a module without enrolled mentees.</strong><br>
                        Attendance links and progress tracking only work when mentees are present in the class.
                    </div>
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;font-size:0.875rem;color:#14532d;line-height:1.7;">
                        <strong>What to do next:</strong><br>
                        Click <strong>"Add Mentees"</strong> below to go to the mentee management page where you can:<br>
                        &bull; <strong>Add from List</strong> — enrol existing users already in the system<br>
                        &bull; <strong>Add Mentee</strong> — create a new user account and enrol them
                    </div>
                </div>
            ');
                        }

                        return "This will open the attendance link for {$menteeCount} mentee(s). The class will be activated if still in draft.";
                    })
                    ->modalSubmitActionLabel(function (ClassModule $record) {
                        $missingDates = $this->isEmonc() && (empty($record->start_date) || empty($record->end_date));
                        if ($missingDates) {
                            return 'OK, I\'ll set the dates';
                        }

                        $hasMentees = ClassParticipant::where('mentorship_class_id', $this->class->id)->exists();

                        return $hasMentees ? 'Yes, Start Module' : 'Add Mentees →';
                    })
                    ->modalCancelActionLabel('Cancel')
                    ->action(function (ClassModule $record) {
                        // ── EmONC gate: dates must be set — auto-open edit modal ────────
                        if ($this->isEmonc() && (empty($record->start_date) || empty($record->end_date))) {
                            $key = $record->getKey();
                            $this->js("setTimeout(() => \$wire.mountTableAction('edit', {$key}), 300)");

                            return;
                        }

                        $menteeCount = ClassParticipant::where('mentorship_class_id', $this->class->id)->count();

                        // ── No mentees → redirect to mentee page ────────────────────────
                        if ($menteeCount === 0) {
                            $url = MentorshipTrainingResource::getUrl('class-mentees', [
                                'training' => $this->training->id,
                                'class' => $this->class->id,
                            ]);

                            $this->redirect($url);

                            return;
                        }

                        // ── Has mentees → proceed with start ────────────────────────────
                        try {
                            $freshClass = MentorshipClass::find($this->class->id);

                            if ($freshClass->status === 'draft') {
                                $freshClass->update(['status' => 'active']);
                            }

                            $record->start();

                            Notification::make()
                                ->success()
                                ->title('Module Started')
                                ->body("Attendance link is now active for {$menteeCount} mentee(s).")
                                ->send();
                        } catch (\LogicException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Start Module')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('complete_module')
                    ->label('Complete')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->button()
                    ->visible(fn (ClassModule $record) => $record->status === 'in_progress')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Module')
                    ->modalDescription(fn (ClassModule $record) => implode("\n", [
                        'Completing this module will:',
                        '• Close attendance confirmation for mentees',
                        '• Calculate final attendance rates',
                        '• Update all mentee progress records',
                        '',
                        "Attendance: {$record->attendanceRate()}% ({$record->confirmedAttendanceCount()} of {$record->enrolledMenteeCount()} confirmed)",
                    ]))
                    ->action(function (ClassModule $record) {
                        try {
                            $record->complete();

                            Notification::make()
                                ->success()
                                ->title('Module Completed')
                                ->body("Final attendance: {$record->attendanceRate()}%")
                                ->send();
                        } catch (\LogicException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Complete Module')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('manage_sessions')
                    ->label('Sessions')
                    ->icon('heroicon-o-calendar-days')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Sessions')
                    ->visible(fn () => ! $this->isEmonc())
                    ->url(fn (ClassModule $record) => MentorshipTrainingResource::getUrl('module-sessions', [
                        'training' => $this->training->id,
                        'class' => $this->class->id,
                        'module' => $record->id,
                    ])),
                Tables\Actions\Action::make('manage_mentees')
                    ->label('Mentees')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Mentees & Attendance')
                    ->badge(function (ClassModule $record) {
                        $pending = MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereNotNull('hands_on_video_url')
                            ->where('video_review_status', 'pending')
                            ->count();

                        return $pending ?: null;
                    })
                    ->badgeColor('danger')
                    ->extraAttributes(function (ClassModule $record) {
                        $pending = MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereNotNull('hands_on_video_url')
                            ->where('video_review_status', 'pending')
                            ->count();

                        return $pending ? ['class' => 'fi-pending-review'] : [];
                    })
                    ->url(fn (ClassModule $record) => MentorshipTrainingResource::getUrl('module-mentees', [
                        'training' => $this->training->id,
                        'class' => $this->class->id,
                        'module' => $record->id,
                    ])),
                Tables\Actions\Action::make('manage_activity_mentees')
                    ->label('Activities')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Enroll Mentees in Activities')
                    ->visible(fn () => $this->isEmonc())
                    ->modalWidth('5xl')
                    ->modalHeading(fn (ClassModule $record) => 'Activity Enrollments — '.($record->programModule?->name ?? 'Module'))
                    ->modalDescription('Check the activities each mentee should be enrolled in for this track.')
                    ->form(function (ClassModule $record) {
                        $participants = ClassParticipant::with('user')
                            ->where('mentorship_class_id', $this->class->id)
                            ->whereIn('status', ['enrolled', 'active'])
                            ->get()
                            ->map(fn ($p) => [
                                'id' => $p->id,
                                'name' => $p->user?->name ?? 'Unknown',
                                'email' => $p->user?->email ?? '',
                            ])
                            ->toArray();

                        $activities = $record->programModule?->activities
                            ?->map(fn ($a) => [
                                'id' => $a->id,
                                'name' => $a->name,
                            ])
                            ->toArray() ?? [];

                        $enrolledActivityIds = $record->activityEnrollments
                            ->groupBy('class_participant_id')
                            ->map(fn ($items) => $items->pluck('activity_id')->toArray())
                            ->toArray();

                        $defaultPayload = collect($enrolledActivityIds)
                            ->map(fn ($activityIds, $participantId) => [
                                'participantId' => (int) $participantId,
                                'activityIds' => $activityIds,
                            ])
                            ->values()
                            ->all();

                        return [
                            ActivityEnrollmentMatrix::make('enrollment_matrix')
                                ->label('')
                                ->participants($participants)
                                ->activities($activities)
                                ->enrolledActivityIds($enrolledActivityIds)
                                ->default(json_encode($defaultPayload)),
                        ];
                    })
                    ->action(function (array $data, ClassModule $record) {
                        $enrollments = json_decode($data['enrollment_matrix'] ?? '[]', true) ?? [];

                        try {
                            $this->saveActivityEnrollments($record->id, $enrollments);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error saving enrollments')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('mark_activities_complete')
                    ->label('Complete Activities')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Mark Activities as Complete')
                    ->visible(fn () => $this->isEmonc())
                    ->modalWidth('5xl')
                    ->modalHeading(fn (ClassModule $record) => 'Activity Completion — '.($record->programModule?->name ?? 'Module'))
                    ->modalDescription('Check the activities each mentee has completed for this track.')
                    ->form(function (ClassModule $record) {
                        $participants = ClassParticipant::with('user')
                            ->where('mentorship_class_id', $this->class->id)
                            ->whereIn('status', ['enrolled', 'active', 'completed'])
                            ->get();

                        $participantIds = $participants->pluck('id');

                        $participantArray = $participants
                            ->map(fn ($p) => [
                                'id' => $p->id,
                                'name' => $p->user?->name ?? 'Unknown',
                                'email' => $p->user?->email ?? '',
                            ])
                            ->toArray();

                        $progressMap = \App\Models\MenteeModuleProgress::where('class_module_id', $record->id)
                            ->whereIn('class_participant_id', $participantIds)
                            ->get()
                            ->keyBy('class_participant_id');

                        $videoReviews = $participants
                            ->mapWithKeys(fn ($p) => [
                                $p->id => $progressMap->get($p->id)?->video_review_status ?? 'not_submitted',
                            ])
                            ->toArray();

                        $certificateStatuses = $participants
                            ->mapWithKeys(fn ($p) => [
                                $p->id => [
                                    'mentor_approved' => ! empty($p->mentor_approved_at),
                                    'head_drmh_approved' => ! empty($p->head_drmh_approved_at),
                                    'certified' => $p->isCertified(),
                                ],
                            ])
                            ->toArray();

                        $activities = $record->programModule?->activities
                            ?->map(fn ($a) => [
                                'id' => $a->id,
                                'name' => $a->name,
                            ])
                            ->toArray() ?? [];

                        $completedActivityIds = $record->activityEnrollments()
                            ->where('status', 'completed')
                            ->get()
                            ->groupBy('class_participant_id')
                            ->map(fn ($items) => $items->pluck('activity_id')->toArray())
                            ->toArray();

                        $defaultPayload = collect($completedActivityIds)
                            ->map(fn ($activityIds, $participantId) => [
                                'participantId' => (int) $participantId,
                                'activityIds' => $activityIds,
                            ])
                            ->values()
                            ->all();

                        return [
                            ActivityCompletionMatrix::make('completion_matrix')
                                ->label('')
                                ->participants($participantArray)
                                ->activities($activities)
                                ->completedActivityIds($completedActivityIds)
                                ->videoReviews($videoReviews)
                                ->certificateStatuses($certificateStatuses)
                                ->default(json_encode($defaultPayload)),
                        ];
                    })
                    ->action(function (array $data, ClassModule $record) {
                        $completions = json_decode($data['completion_matrix'] ?? '[]', true) ?? [];

                        try {
                            $this->saveActivityCompletions($record->id, $completions);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error saving activity completions')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('module_summary')
                    ->label('Summary')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Summary & Analytics')
                    ->visible(fn () => ! $this->isEmonc())
                    ->url(fn (ClassModule $record) => MentorshipTrainingResource::getUrl('module-summary', [
                        'training' => $this->training->id,
                        'class' => $this->class->id,
                        'module' => $record->id,
                    ])),
                Tables\Actions\Action::make('module_resources')
                    ->label('Resources')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Module Resources')
                    ->url(fn (ClassModule $record) => MentorshipTrainingResource::getUrl('module-resources', [
                        'training' => $this->training->id,
                        'class' => $this->class->id,
                        'module' => $record->id,
                    ])),
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->iconButton()
                    ->tooltip('Edit Module Settings')
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->native(false)
                                ->minDate(today()),
                            Forms\Components\DatePicker::make('end_date')
                                ->label('End Date')
                                ->native(false)
                                ->minDate(today())
                                ->afterOrEqual('start_date'),
                        ]),
                        Forms\Components\TextInput::make('order_sequence')
                            ->label('Display Order')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Textarea::make('notes')
                            ->label('Module Notes')
                            ->rows(3),
                    ])
                    ->visible(fn (ClassModule $record) => $record->status !== 'completed'),
                Tables\Actions\Action::make('remove_module')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Remove from Class')
                    ->visible(fn (ClassModule $record) => $record->status === 'not_started')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Module from Class')
                    ->modalDescription('This removes the module from this class and makes it available for other classes. Sessions will also be deleted.')
                    ->action(function (ClassModule $record) {
                        $service = app(ModuleUsageService::class);

                        if (! $record->canBeRemoved()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot Remove Module')
                                ->body('Module has sessions or progress records.')
                                ->send();

                            return;
                        }

                        $service->removeModuleFromClass($this->training, $this->class, $record);
                        Notification::make()->success()->title('Module Removed')->send();
                    }),
            ])
            ->emptyStateHeading('No Modules Added Yet')
            ->emptyStateDescription('Add modules from the program curriculum to get started.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getModuleOptions(ModuleUsageService $service): array
    {
        return $service->getAvailableModules($this->training, $this->class)
            ->mapWithKeys(fn ($module) => [$module->id => $module->name])
            ->toArray();
    }

    public function saveActivityEnrollments(int $classModuleId, array $enrollments): void
    {
        $classModule = ClassModule::with('programModule.activities')->findOrFail($classModuleId);
        $activityIds = $classModule->programModule?->activities?->pluck('id')->toArray() ?? [];
        $participantIds = ClassParticipant::where('mentorship_class_id', $this->class->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->pluck('id')
            ->toArray();

        $toInsert = [];
        foreach ($enrollments as $entry) {
            $participantId = (int) ($entry['participantId'] ?? 0);
            $activities = $entry['activityIds'] ?? [];

            if (! in_array($participantId, $participantIds)) {
                continue;
            }

            foreach ($activities as $activityId) {
                $activityId = (int) $activityId;
                if (! in_array($activityId, $activityIds)) {
                    continue;
                }
                $toInsert[] = [
                    'class_module_id' => $classModule->id,
                    'class_participant_id' => $participantId,
                    'activity_id' => $activityId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::transaction(function () use ($classModule, $participantIds, $activityIds, $toInsert) {
            ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->whereIn('class_participant_id', $participantIds)
                ->whereIn('activity_id', $activityIds)
                ->delete();

            if (! empty($toInsert)) {
                ClassModuleActivityParticipant::insert($toInsert);
            }
        });

        Notification::make()->success()->title('Activity enrollments saved')->send();
    }

    public function saveActivityCompletions(int $classModuleId, array $completions): void
    {
        $classModule = ClassModule::with('programModule.activities')->findOrFail($classModuleId);
        $activityIds = $classModule->programModule?->activities?->pluck('id')->toArray() ?? [];
        $participantIds = ClassParticipant::where('mentorship_class_id', $this->class->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->pluck('id')
            ->toArray();

        $completedKeys = [];
        foreach ($completions as $entry) {
            $participantId = (int) ($entry['participantId'] ?? 0);
            foreach ($entry['activityIds'] ?? [] as $activityId) {
                $completedKeys["{$participantId}:".(int) $activityId] = true;
            }
        }

        $newlyCompletedParticipantIds = [];

        DB::transaction(function () use ($classModule, $participantIds, $activityIds, $completedKeys, $completions, &$newlyCompletedParticipantIds) {
            $existing = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->whereIn('class_participant_id', $participantIds)
                ->whereIn('activity_id', $activityIds)
                ->get();

            foreach ($existing as $record) {
                $key = "{$record->class_participant_id}:{$record->activity_id}";
                $shouldBeCompleted = isset($completedKeys[$key]);

                if ($shouldBeCompleted && $record->status !== 'completed') {
                    $record->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'completed_by' => auth()->id(),
                    ]);
                } elseif (! $shouldBeCompleted && $record->status === 'completed') {
                    $record->update([
                        'status' => 'pending',
                        'completed_at' => null,
                        'completed_by' => null,
                    ]);
                }
            }

            foreach ($completions as $entry) {
                $participantId = (int) ($entry['participantId'] ?? 0);

                if (! in_array($participantId, $participantIds)) {
                    continue;
                }

                foreach ($entry['activityIds'] ?? [] as $activityId) {
                    $activityId = (int) $activityId;

                    if (! in_array($activityId, $activityIds)) {
                        continue;
                    }

                    $exists = $existing->contains(
                        fn ($r) => $r->class_participant_id === $participantId && $r->activity_id === $activityId
                    );

                    if (! $exists) {
                        ClassModuleActivityParticipant::create([
                            'class_module_id' => $classModule->id,
                            'class_participant_id' => $participantId,
                            'activity_id' => $activityId,
                            'status' => 'completed',
                            'completed_at' => now(),
                            'completed_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // ── Auto-complete mentee progress when all activities done ───────
            foreach ($participantIds as $participantId) {
                $completedCount = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                    ->where('class_participant_id', $participantId)
                    ->where('status', 'completed')
                    ->count();

                if ($completedCount === count($activityIds)) {
                    $progress = MenteeModuleProgress::firstOrCreate(
                        [
                            'class_participant_id' => $participantId,
                            'class_module_id' => $classModule->id,
                        ],
                        ['status' => 'not_started']
                    );

                    if (! in_array($progress->status, ['completed', 'exempted'])) {
                        $progress->markCompleted();
                        $newlyCompletedParticipantIds[] = $participantId;
                    }
                }
            }

            // ── Auto-complete class module when all enrolled mentees are done ─
            $totalParticipants = count($participantIds);
            $completedParticipants = MenteeModuleProgress::where('class_module_id', $classModule->id)
                ->whereIn('status', ['completed', 'exempted'])
                ->count();

            if ($totalParticipants > 0 && $completedParticipants === $totalParticipants && $classModule->status === 'in_progress') {
                $classModule->complete();
            }
        });

        // ── Notify mentees whose module progress was auto-completed ─────────
        if (! empty($newlyCompletedParticipantIds)) {
            $notificationService = app(EmoncNotificationService::class);
            $participants = ClassParticipant::with('user')->whereIn('id', $newlyCompletedParticipantIds)->get();
            foreach ($participants as $participant) {
                $notificationService->activityCompleted($participant, $classModule);
            }
        }

        Notification::make()->success()->title('Activity completions saved')->send();
    }
}
