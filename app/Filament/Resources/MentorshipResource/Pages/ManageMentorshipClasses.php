<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Mail\CoMentorInvitation;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageMentorshipClasses extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-mentorship-classes';

    public Training $record;

    public ?MentorshipClass $selectedClass = null;

    public bool $viewingModules = false;

    public function mount(): void
    {
        // $this->record is automatically bound by Filament from the route
        // Check if we have a 'class' query parameter for viewing modules
        $classId = request()->query('class');

        if ($classId) {
            $this->selectedClass = MentorshipClass::findOrFail($classId);
            $this->viewingModules = true;
        }
    }

    public function getTitle(): string
    {
        if ($this->viewingModules && $this->selectedClass) {
            return "Modules - {$this->selectedClass->name}";
        }

        return 'Classes/Cohort';
    }

    public function getSubheading(): ?string
    {
        if ($this->viewingModules && $this->selectedClass) {
            return "{$this->selectedClass->module_count} modules {$this->selectedClass->session_count} sessions";
        }

        return 'Manage mentorship cohorts and modules';
    }

    protected function getHeaderActions(): array
    {
        if ($this->viewingModules) {
            return $this->getModuleHeaderActions();
        }

        return $this->getClassHeaderActions();
    }

    private function getClassHeaderActions(): array
    {
        return [
            Actions\Action::make('create_class')
                ->label('Create New Class/Cohort')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Class/Cohort Name')
                        ->required()
                        ->placeholder('e.g., January 2025 Cohort')
                        ->maxLength(255),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(fn () => ! $this->isEmonc())
                            ->visible(fn () => ! $this->isEmonc())
                            ->native(false)
                            ->live()
                            ->minDate(fn () => $this->record->start_date)
                            ->maxDate(fn () => $this->record->end_date)
                            ->helperText(fn () => $this->record->start_date && $this->record->end_date
                                ? 'Between '.$this->record->start_date->format('M j, Y').' and '.$this->record->end_date->format('M j, Y')
                                : null),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->required(fn () => ! $this->isEmonc())
                            ->visible(fn () => ! $this->isEmonc())
                            ->native(false)
                            ->minDate(fn (Forms\Get $get) => $get('start_date') ?: $this->record->start_date)
                            ->maxDate(fn () => $this->record->end_date)
                            ->afterOrEqual('start_date'),
                    ]),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->placeholder('Describe the gap identified and how this class will be delivered.')
                        ->helperText('Make this detailed: describe the gap identified, the mentorship focus, and how the class will be done.'),
                ])
                ->action(fn (array $data) => $this->createClass($data)),
            Actions\Action::make('invite_co_mentor')
                ->label('Invite Co-Mentor')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading('Invite Co-Mentor')
                ->modalDescription("Invite another mentor to collaborate on '{$this->record->title}'.")
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Select Mentor')
                        ->options(function () {
                            $existingCoMentorIds = $this->record->coMentors()->pluck('user_id')->toArray();
                            $existingCoMentorIds[] = $this->record->mentor_id;

                            return User::whereNotIn('id', $existingCoMentorIds)
                                ->where('status', 'active')
                                ->whereHas('roles', fn ($q) => $q->where('name', 'like', '%mentor%'))
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn ($user) => [
                                    $user->id => $user->full_name.
                                    ' ('.($user->facility?->name ?? 'No Facility').') - '.
                                    ($user->cadre?->name ?? 'No Cadre'),
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->helperText('Select a user to invite as co-mentor'),
                    Forms\Components\Textarea::make('invitation_message')
                        ->label('Invitation Message (Optional)')
                        ->rows(3)
                        ->placeholder('Add a personal message to the invitation'),
                ])
                ->action(fn (array $data) => $this->inviteCoMentor($data)),
            Actions\Action::make('back_to_training')
                ->label('Back to Mentorships')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('index')),
        ];
    }

    private function getModuleHeaderActions(): array
    {
        return [
            Actions\Action::make('invite_mentees')
                ->label('Manage/Invite Mentees')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-mentees', [
                    'training' => $this->record->id,
                    'class' => $this->selectedClass->id,
                ]))
                ->badge(fn () => $this->selectedClass->participants()->count()),
            Actions\Action::make('back_to_classes')
                ->label('Back to Classes')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('classes', [
                    'record' => $this->record,
                ])),
        ];
    }

    public function table(Table $table): Table
    {
        if ($this->viewingModules) {
            return $this->getModulesTable($table);
        }

        return $this->getClassesTable($table);
    }

    private function getClassesTable(Table $table): Table
    {
        $isEmonc = $this->isEmonc();

        return $table
            ->query(
                MentorshipClass::query()
                    ->where('training_id', $this->record->id)
                    ->with(['creator'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Class Name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (MentorshipClass $record): string => $record->description ?? ''
                    ),
                //                            Tables\Columns\BadgeColumn::make('status')
                //                            ->colors([
                //                                'secondary' => 'draft',
                //                                'warning' => 'active',
                //                                'success' => 'completed',
                //                                'danger' => 'cancelled',
                //                            ]),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable()
                    ->hidden($isEmonc),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('M j, Y')
                    ->sortable()
                    ->hidden($isEmonc),
                Tables\Columns\TextColumn::make('module_count')
                    ->label('Modules')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('session_count')
                    ->label($isEmonc ? 'Activities' : 'Sessions')
                    ->getStateUsing(function (MentorshipClass $record) use ($isEmonc) {
                        if ($isEmonc) {
                            return $record->classModules()
                                ->with('programModule.activities')
                                ->get()
                                ->sum(fn ($cm) => $cm->programModule?->activities?->count() ?? 0);
                        }

                        return $record->session_count;
                    })
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Mentees')
                    ->counts('participants')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state >= 80 ? 'success' : ($state >= 50 ? 'warning' : 'danger')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_modules')
                        ->label('View Modules')
                        ->icon('heroicon-o-book-open')
                        ->color('primary')
                        ->url(fn (MentorshipClass $record): string => MentorshipTrainingResource::getUrl('class-modules', [
                            'training' => $this->record->id,
                            'class' => $record->id,
                        ])
                        ),
                    Tables\Actions\Action::make('invite_mentees')
                        ->label('Manage/Invite Mentees')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->url(fn (MentorshipClass $record): string => MentorshipTrainingResource::getUrl('class-mentees', [
                            'training' => $this->record->id,
                            'class' => $record->id,
                        ])
                        ),
                    //                                Tables\Actions\Action::make('activate')
                    //                                ->label('Activate')
                    //                                ->icon('heroicon-o-check')
                    //                                ->color('warning')
                    //                                ->visible(fn(MentorshipClass $record) => $record->status === 'draft')
                    //                                ->requiresConfirmation()
                    //                                ->action(function (MentorshipClass $record) {
                    //                                    $record->activate();
                    //                                    Notification::make()
                    //                                            ->success()
                    //                                            ->title('Class Activated')
                    //                                            ->send();
                    //                                }),
                    Tables\Actions\EditAction::make()
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->required(),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->required(fn () => ! $this->isEmonc())
                                    ->visible(fn () => ! $this->isEmonc())
                                    ->native(false)
                                    ->live()
                                    ->minDate(fn () => $this->record->start_date)
                                    ->maxDate(fn () => $this->record->end_date),
                                Forms\Components\DatePicker::make('end_date')
                                    ->required(fn () => ! $this->isEmonc())
                                    ->visible(fn () => ! $this->isEmonc())
                                    ->native(false)
                                    ->minDate(fn (Forms\Get $get) => $get('start_date') ?: $this->record->start_date)
                                    ->maxDate(fn () => $this->record->end_date)
                                    ->afterOrEqual('start_date'),
                            ]),
                            Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->placeholder('Describe the gap identified and how this class will be delivered.')
                                ->helperText('Make this detailed: describe the gap identified, the mentorship focus, and how the class will be done.'),
                        ]),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No Classes Yet')
            ->emptyStateDescription('Create your first class cohort to start the mentorship program.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Tables\Actions\Action::make('create_first_class')
                    ->label('Create First Class')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->action(function () {
                        $this->mountAction('create_class');
                    }),
            ]);
    }

    private function getModulesTable(Table $table): Table
    {
        return $table
            ->query(
                ClassModule::query()
                    ->where('mentorship_class_id', $this->selectedClass->id)
                    ->with(['programModule'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('#')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('programModule.name')
                    ->label('Module Name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (ClassModule $record): string => $record->programModule->description ?? ''
                    ),
                //                            Tables\Columns\BadgeColumn::make('status')
                //                            ->colors([
                //                                'secondary' => 'not_started',
                //                                'warning' => 'in_progress',
                //                                'success' => 'completed',
                //                            ])
                //                            ->formatStateUsing(fn(string $state): string =>
                //                                    match ($state) {
                //                                        'not_started' => 'Not Started',
                //                                        'in_progress' => 'In Progress',
                //                                        'completed' => 'Completed',
                //                                        default => ucfirst($state),
                //                                    }
                //                            ),
                Tables\Columns\TextColumn::make('session_count')
                    ->label('Sessions')
                    ->badge()
                    ->color('primary'),
                //                            ->description(function (ClassModule $record) {
                //                                $completed = $record->sessions()->where('status', 'completed')->count();
                //                                return $completed > 0 ? "{$completed} completed" : 'None completed';
                //                            }),
                //                            Tables\Columns\TextColumn::make('progress_percentage')
                //                            ->label('Progress')
                //                            ->suffix('%')
                //                            ->badge()
                //                            ->color(fn($state) => $state >= 100 ? 'success' : ($state >= 50 ? 'warning' : 'danger')),
                //                            Tables\Columns\TextColumn::make('programModule.duration_weeks')
                //                            ->label('Duration')
                //                            ->suffix(' weeks')
                //                            ->toggleable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('add_sessions')
                        ->label('Add Sessions')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->url(fn (ClassModule $record): string => MentorshipTrainingResource::getUrl('module-sessions', [
                            'training' => $this->record->id,
                            'class' => $this->selectedClass->id,
                            'module' => $record->id,
                        ])
                        ),
                    Tables\Actions\Action::make('start_module')
                        ->label('Start Module')
                        ->icon('heroicon-o-play')
                        ->color('primary')
                        ->visible(fn (ClassModule $record) => $record->status === 'not_started')
                        ->requiresConfirmation()
                        ->action(function (ClassModule $record) {
                            $record->start();
                            Notification::make()
                                ->success()
                                ->title('Module Started')
                                ->send();
                        }),
                    Tables\Actions\Action::make('complete_module')
                        ->label('Complete Module')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (ClassModule $record) => $record->status === 'in_progress')
                        ->requiresConfirmation()
                        ->action(function (ClassModule $record) {
                            $record->complete();
                            Notification::make()
                                ->success()
                                ->title('Module Completed')
                                ->send();
                        }),
                ]),
            ])
            ->reorderable('order_sequence')
            ->emptyStateHeading('No Modules Configured')
            ->emptyStateDescription('Modules should be auto-populated from the mentorship program.');
    }

    private function createClass(array $data): void
    {
        $class = MentorshipClass::create([
            'training_id' => $this->record->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        Notification::make()
            ->success()
            ->title('Class Created')
            ->body("Class '{$data['name']}' created. Now add modules to it.")
            ->send();

        // Navigate to the class modules page to manually add modules
        redirect(MentorshipTrainingResource::getUrl('class-modules', [
            'training' => $this->record->id,
            'class' => $class->id,
        ]));
    }

    private function inviteCoMentor(array $data): void
    {
        $token = Str::random(64);

        $coMentor = MentorshipCoMentor::create([
            'training_id' => $this->record->id,
            'user_id' => $data['user_id'],
            'invited_by' => auth()->id(),
            'invited_at' => now(),
            'status' => 'pending',
            'invitation_token' => $token,
            'permissions' => [
                'can_facilitate' => true,
                'can_create_classes' => false,
                'can_invite_mentors' => false,
            ],
        ]);

        $user = User::find($data['user_id']);
        $invitationLink = url("/co-mentor/accept/{$token}");

        // Send invitation email
        if ($user->email) {
            Mail::to($user->email)->send(new CoMentorInvitation($this->record, $coMentor, $data['invitation_message'] ?? null));
        }

        Notification::make()
            ->success()
            ->title('Co-Mentor Invited Successfully')
            ->body(new HtmlString(
                "<p><strong>{$user->full_name}</strong> has been invited as a co-mentor.</p>".
                ($user->email ? "<p class='mt-2 text-sm text-gray-600'>An invitation email has been sent to <strong>{$user->email}</strong>.</p>" : '').
                "<p class='mt-2 text-sm font-medium'>Invitation Link:</p>".
                "<div class='font-mono text-xs break-all bg-gray-100 dark:bg-gray-800 p-2 rounded mt-1'>{$invitationLink}</div>".
                "<p class='mt-2 text-xs text-gray-500'>Share this link with the co-mentor to accept the invitation.</p>"
            ))
            ->persistent()
            ->send();
    }

    /**
     * Determine whether the current mentorship is the EmONC package.
     */
    private function isEmonc(): bool
    {
        $program = Program::find($this->record->program_id);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
}
