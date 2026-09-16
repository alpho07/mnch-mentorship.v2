<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Cadre;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Setting;
use App\Models\Training;
use App\Services\MentorshipWizardService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

class QuickMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.quick-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Same enforcement pattern as GuidedMentorshipSetup::canAccess() — a
     * ?training= query string means someone is resuming a session already
     * started, always allowed; a fresh visit requires the Settings toggle.
     */
    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED);
    }

    public ?array $data = [];

    #[Url(as: 'training')]
    public ?int $trainingId = null;

    #[Url(as: 'class')]
    public ?int $classId = null;

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public bool $basicsSaved = false;

    public bool $firstClassSaved = false;

    public bool $modulesSaved = false;

    public bool $menteesSaved = false;

    public int $enrolledCount = 0;

    public int $invitedCount = 0;

    public bool $classStarted = false;

    public array $moduleDates = [];

    public function updatedModuleDates(): void
    {
        $this->saveWizardDraft('moduleDates', $this->moduleDates);
    }

    public function mount(): void
    {
        $fill = [
            'module_ids' => [],
            'selected_users' => [],
            'auto_create_sessions' => true,
        ];

        if ($this->trainingId) {
            $this->training = Training::find($this->trainingId);

            if ($this->training) {
                $this->basicsSaved = true;
                $fill['is_pilot'] = (int) $this->training->is_pilot;
                $fill['county_id'] = $this->training->county_id;
                $fill['facility_id'] = $this->training->facility_id;
                $fill['program_id'] = $this->training->program_id;
                $fill['start_date'] = $this->training->start_date;
                $fill['end_date'] = $this->training->end_date;
                $fill['max_participants'] = $this->training->max_participants;

                $this->moduleDates = $this->training->guided_setup_draft['moduleDates'] ?? [];
            }
        }

        if ($this->classId) {
            $this->class = MentorshipClass::find($this->classId);

            if ($this->class) {
                $this->firstClassSaved = true;
                $fill['class_name'] = $this->class->name;
                $fill['class_start_date'] = $this->class->start_date;
                $fill['class_end_date'] = $this->class->end_date;
                $fill['class_description'] = $this->class->description;

                $draft = $this->training->guided_setup_draft ?? [];

                $fill['module_ids'] = array_key_exists('module_ids', $draft)
                    ? $draft['module_ids']
                    : $this->class->classModules()->pluck('program_module_id')->toArray();

                $fill['selected_users'] = array_key_exists('selected_users', $draft)
                    ? $draft['selected_users']
                    : $this->class->participants()->pluck('user_id')->toArray();

                $this->modulesSaved = $this->class->classModules()->exists()
                    || array_key_exists('module_ids', $draft);
                $this->menteesSaved = $this->class->participants()->exists()
                    || array_key_exists('selected_users', $draft);
            }
        }

        $this->form->fill($fill);
    }

    public function getTitle(): string
    {
        return 'Quick Setup';
    }

    public function getSubheading(): ?string
    {
        return 'Everything in one place — fill each section as you go.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basics')
                    ->description('Run type, location, program, and schedule.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Forms\Components\Radio::make('is_pilot')
                            ->label('')
                            ->options([0 => 'Live Mentorship', 1 => 'Pilot Run'])
                            ->descriptions([
                                0 => 'Counts in dashboards, KPI badges, and analytics reports.',
                                1 => 'Excluded from all counts, badges, and analytics. Use for testing.',
                            ])
                            ->default(0)
                            ->required()
                            ->inline(false),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->options(fn () => County::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('facility_id', null))
                                ->prefixIcon('heroicon-o-map'),
                            Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->options(function (Get $get) {
                                    $countyId = $get('county_id');
                                    if (! $countyId) {
                                        return [];
                                    }

                                    return Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $countyId))
                                        ->get()
                                        ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} — {$f->name}"]);
                                })
                                ->searchable()
                                ->required()
                                ->disabled(fn (Get $get) => ! $get('county_id'))
                                ->prefixIcon('heroicon-o-building-office-2'),
                        ]),
                        \App\Filament\Forms\Components\ProgramPicker::make('program_id')
                            ->label('Mentorship Program')
                            ->helperText('Tap a programme card to select it.')
                            ->required()
                            ->validationMessages([
                                'required' => 'Please pick a programme card.',
                            ])
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                $program = $value ? Program::find($value) : null;

                                if ($program && ! $program->isSelectableBy(auth()->user())) {
                                    $fail('That program is not active — pick a different one.');
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->native(false)
                                ->minDate(today())
                                ->displayFormat('M j, Y'),
                            Forms\Components\DatePicker::make('end_date')
                                ->label('End Date')
                                ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->native(false)
                                ->minDate(fn (Get $get) => $get('start_date') ?? now())
                                ->afterOrEqual('start_date')
                                ->displayFormat('M j, Y'),
                            Forms\Components\TextInput::make('max_participants')
                                ->label('Number of Mentees')
                                ->numeric()
                                ->default(10)
                                ->minValue(2)
                                ->maxValue(10)
                                ->suffix('mentees'),
                        ]),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_basics')
                                ->label('Continue')
                                ->action('saveBasics'),
                        ])->visible(fn () => ! $this->basicsSaved),
                    ]),
                Forms\Components\Section::make('First Class')
                    ->description("Let's create your first class or cohort.")
                    ->icon('heroicon-o-user-group')
                    ->visible(fn () => $this->basicsSaved)
                    ->schema([
                        Forms\Components\TextInput::make('class_name')
                            ->label('Class/Cohort Name')
                            ->required(fn () => $this->basicsSaved)
                            ->placeholder('e.g., January 2027 Cohort')
                            ->maxLength(255),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('class_start_date')
                                ->label('Start Date')
                                ->required(fn () => $this->basicsSaved && ! $this->isEmoncProgram($this->training?->program_id))
                                ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                ->native(false)
                                ->minDate(fn () => $this->training?->start_date)
                                ->maxDate(fn () => $this->training?->end_date),
                            Forms\Components\DatePicker::make('class_end_date')
                                ->label('End Date')
                                ->required(fn () => $this->basicsSaved && ! $this->isEmoncProgram($this->training?->program_id))
                                ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                ->native(false)
                                ->minDate(fn (Get $get) => $get('class_start_date') ?: $this->training?->start_date)
                                ->maxDate(fn () => $this->training?->end_date)
                                ->afterOrEqual('class_start_date'),
                        ]),
                        Forms\Components\Textarea::make('class_description')
                            ->label('Description')
                            ->rows(3)
                            ->placeholder('Describe the gap identified and how this class will be delivered.'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_first_class')
                                ->label('Continue')
                                ->action('saveFirstClass'),
                        ])->visible(fn () => ! $this->firstClassSaved),
                    ]),
                Forms\Components\Section::make('Modules')
                    ->description('Pick as many or as few modules as you like — you can add more later.')
                    ->icon('heroicon-o-book-open')
                    ->visible(fn () => $this->firstClassSaved)
                    ->schema(function () {
                        if (! $this->training || ! $this->class) {
                            return [];
                        }

                        if ($this->isEmoncProgram($this->training->program_id)) {
                            $picker = \App\Filament\Forms\Components\EmoncModulePicker::make('module_ids')
                                ->label('Available Program Modules')
                                ->training($this->training)
                                ->class($this->class)
                                ->includeAssigned()
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                ->helperText('Already-added modules/tracks are pre-checked — uncheck one to remove it.')
                                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                    if ($error = $this->validateModuleDates($value ?? [])) {
                                        $fail($error);
                                    }
                                });
                        } else {
                            $allModules = ProgramModule::where('program_id', $this->training->program_id)
                                ->where('is_active', true)
                                ->whereNull('parent_id')
                                ->orderBy('order_sequence')
                                ->pluck('name', 'id')
                                ->toArray();

                            $picker = \App\Filament\Forms\Components\CardCheckboxList::make('module_ids')
                                ->label('Available Program Modules')
                                ->options($allModules)
                                ->default([])
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                ->helperText('Already-added modules are pre-checked — uncheck one to remove it.');
                        }

                        return [
                            $picker,
                            Forms\Components\Toggle::make('auto_create_sessions')
                                ->label('Auto-populate sessions from program template')
                                ->default(true)
                                ->disabled()
                                ->dehydrated(true),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('continue_modules')
                                    ->label('Continue')
                                    ->action('saveModules'),
                            ])->visible(fn () => ! $this->modulesSaved),
                        ];
                    }),
                Forms\Components\Section::make('Mentees')
                    ->description('Who will be mentored in this class? You can skip this and enroll mentees later.')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn () => $this->modulesSaved)
                    ->schema([
                        Forms\Components\Hidden::make('mentee_page')->default(1),
                        Forms\Components\Hidden::make('show_new_mentee_form')->default(false),
                        Forms\Components\TextInput::make('mentee_search')
                            ->label('Search')
                            ->placeholder('Search by name, phone, email, or facility...')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('mentee_page', 1))
                            ->prefixIcon('heroicon-o-magnifying-glass'),
                        \App\Filament\Forms\Components\CardCheckboxList::make('selected_users')
                            ->label('Existing Users')
                            ->options(fn (Get $get) => app(MentorshipWizardService::class)->menteeOptions(
                                $get('mentee_search'),
                                (int) ($get('mentee_page') ?? 1),
                                collect($get('selected_users') ?? [])->map(fn ($id) => (int) $id)->all()
                            ))
                            ->maxSelections(fn () => $this->training?->max_participants)
                            ->default([])
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('selected_users', $state))
                            ->columnSpanFull()
                            ->helperText('Search and check existing users to enroll.'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('mentee_previous')
                                ->label('Previous Page')
                                ->color('gray')
                                ->action(fn (Forms\Set $set, Get $get) => $set('mentee_page', max(1, (int) $get('mentee_page') - 1))),
                            Forms\Components\Actions\Action::make('mentee_next')
                                ->label('Next Page')
                                ->color('gray')
                                ->action(fn (Forms\Set $set, Get $get) => $set('mentee_page', (int) $get('mentee_page') + 1)),
                        ])->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('reveal_new_mentee_form')
                                ->label(fn (Get $get) => trim((string) $get('mentee_search')) !== ''
                                    ? "Mentee \"{$get('mentee_search')}\" not found — click here to add"
                                    : '+ Add a new mentee')
                                ->icon('heroicon-o-user-plus')
                                ->color(fn (Get $get) => trim((string) $get('mentee_search')) !== '' ? 'warning' : 'gray')
                                ->action(function (Forms\Set $set, Get $get) {
                                    $set('show_new_mentee_form', true);

                                    $search = trim((string) $get('mentee_search'));
                                    if ($search === '') {
                                        return;
                                    }

                                    if (str_contains($search, '@')) {
                                        $set('new_mentee.email', $search);

                                        return;
                                    }

                                    $parts = preg_split('/\s+/', $search, 2);
                                    $set('new_mentee.first_name', $parts[0] ?? null);
                                    $set('new_mentee.last_name', $parts[1] ?? null);
                                }),
                        ])
                            ->visible(fn (Get $get) => ! $get('show_new_mentee_form'))
                            ->columnSpanFull(),
                        Forms\Components\Fieldset::make('Or Add a New Mentee')
                            ->visible(fn (Get $get) => (bool) $get('show_new_mentee_form'))
                            ->schema([
                                Forms\Components\TextInput::make('new_mentee.email')
                                    ->label('Email Address')
                                    ->email(),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('new_mentee.first_name')
                                        ->label('First Name')
                                        ->requiredWith('new_mentee.email'),
                                    Forms\Components\TextInput::make('new_mentee.last_name')
                                        ->label('Last Name')
                                        ->requiredWith('new_mentee.email'),
                                ]),
                                Forms\Components\TextInput::make('new_mentee.phone')
                                    ->label('Phone')
                                    ->tel(),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('new_mentee.cadre_id')
                                        ->label('Cadre')
                                        ->options(Cadre::orderBy('name')->pluck('name', 'id')),
                                    Forms\Components\Select::make('new_mentee.department_id')
                                        ->label('Department')
                                        ->options(Department::orderBy('name')->pluck('name', 'id')),
                                ]),
                                Forms\Components\Select::make('new_mentee.facility_id')
                                    ->label('Facility')
                                    ->options(fn () => Facility::orderBy('name')
                                        ->limit(200)
                                        ->get()
                                        ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} - {$f->name}"])),
                            ]),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_mentees')
                                ->label('Continue')
                                ->action('saveMentees'),
                        ])->visible(fn () => ! $this->menteesSaved),
                    ]),
                Forms\Components\Section::make('Invite')
                    ->description('Time to invite your mentees!')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn () => $this->menteesSaved)
                    ->schema([
                        Forms\Components\Radio::make('recipients')
                            ->label('Who should receive the email?')
                            ->options([
                                'all' => 'All mentees with email addresses',
                                'not_sent' => 'Only those not yet invited',
                            ])
                            ->default('all')
                            ->required(fn () => $this->menteesSaved),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('submit')
                                ->label('Create Mentorship')
                                ->color('primary')
                                ->action('submit'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        try {
            $this->sendInvitations(['recipients' => $state['recipients'] ?? 'all']);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Something Went Wrong')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function sendInvitations(array $data): array
    {
        $result = app(MentorshipWizardService::class)->sendInvitations($data, $this->training, $this->class);

        $this->invitedCount = $result['sent'] + $result['resent'];
        $this->completed = true;

        if ($this->class->fresh()->status === 'active') {
            $this->classStarted = true;
        }

        return $result;
    }

    public function saveMentees(): void
    {
        $state = $this->form->getState();

        $this->enrollMentees([
            'selected_users' => $state['selected_users'] ?? [],
            'new_mentee' => ($state['new_mentee']['email'] ?? null) ? $state['new_mentee'] : null,
        ]);

        $this->menteesSaved = true;
    }

    public function enrollMentees(array $data): int
    {
        $count = app(MentorshipWizardService::class)->enrollMentees($data, $this->class);
        $this->enrolledCount = $count;

        return $count;
    }

    public function saveModules(): void
    {
        $state = $this->form->getState();

        $this->assignModules([
            'module_ids' => $state['module_ids'] ?? [],
            'auto_create_sessions' => $state['auto_create_sessions'] ?? true,
            'module_dates' => $this->moduleDates,
        ]);

        $this->modulesSaved = true;
    }

    public function assignModules(array $data): int
    {
        $created = app(MentorshipWizardService::class)->assignModules($data, $this->training, $this->class);
        $this->moduleDates = [];

        return $created;
    }

    public function validateModuleDates(array $moduleIds): ?string
    {
        return app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates);
    }

    private function saveWizardDraft(string $key, mixed $state): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->saveWizardDraft($this->training, $key, $state);
    }

    private function clearWizardDraft(string $key): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->clearWizardDraft($this->training, $key);
    }

    public function saveFirstClass(): void
    {
        $state = $this->form->getState();

        $this->createFirstClass([
            'name' => $state['class_name'],
            'start_date' => $state['class_start_date'] ?? null,
            'end_date' => $state['class_end_date'] ?? null,
            'description' => $state['class_description'] ?? null,
        ]);

        $this->firstClassSaved = true;
    }

    public function createFirstClass(array $data): MentorshipClass
    {
        $this->class = app(MentorshipWizardService::class)->createFirstClass($data, $this->training, $this->class);
        $this->classId = $this->class->id;

        return $this->class;
    }

    public function saveBasics(): void
    {
        $state = $this->form->getState();

        $this->createTraining([
            'is_pilot' => $state['is_pilot'],
            'county_id' => $state['county_id'],
            'facility_id' => $state['facility_id'],
            'program_id' => $state['program_id'],
            'start_date' => $state['start_date'] ?? null,
            'end_date' => $state['end_date'] ?? null,
            'max_participants' => $state['max_participants'],
        ]);

        $this->training->update(['guided_setup_method' => 'quick']);
        $this->basicsSaved = true;
    }

    public function createTraining(array $data): Training
    {
        $this->training = app(MentorshipWizardService::class)->createTraining($data, $this->training);
        $this->trainingId = $this->training->id;

        return $this->training;
    }

    private function isEmoncProgram(?int $programId): bool
    {
        return app(MentorshipWizardService::class)->isEmoncProgram($programId);
    }
}
