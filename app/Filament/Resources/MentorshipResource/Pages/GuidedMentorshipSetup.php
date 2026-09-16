<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\CardCheckboxList;
use App\Filament\Forms\Components\EmoncModulePicker;
use App\Filament\Forms\Components\ProgramPicker;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Cadre;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipWizardService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Livewire\Attributes\Url;

class GuidedMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.guided-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Backs the "New Mentorship Guided Setup" button's own ->disabled()
     * state on the list page (cosmetic) — this is the actual enforcement,
     * blocking a *fresh* /guided-setup visit while the method is turned off
     * in Mentorship Settings. A `?training=` query string means someone is
     * resuming a wizard they already started (e.g. via the pending-setup
     * banner's Continue link) — that's always allowed regardless of the
     * button's current state, so turning it off can't strand in-progress
     * work with modules/mentees already committed to the DB.
     */
    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return \App\Models\Setting::getBool(\App\Models\Setting::GUIDED_SETUP_BUTTON_ENABLED);
    }

    public ?array $data = [];

    #[Url(as: 'training')]
    public ?int $trainingId = null;

    #[Url(as: 'class')]
    public ?int $classId = null;

    // Mirrors of the Run Type / Location step fields, kept in sync via
    // afterStateUpdated() below. These steps run before createTraining()
    // ever fires, so nothing is in the DB yet to resume from on refresh —
    // stash the raw values in the URL instead, same idea as training/class.
    #[Url(as: 'pilot')]
    public ?int $urlIsPilot = null;

    #[Url(as: 'county')]
    public ?int $urlCountyId = null;

    #[Url(as: 'facility')]
    public ?int $urlFacilityId = null;

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public bool $classStarted = false;

    /**
     * Per-track/module EmONC start/end dates, keyed by program_module_id
     * (string keys, since these round-trip through Alpine/JSON): {id: {
     * start: 'Y-m-d', end: 'Y-m-d' }}. Collected via a modal right after a
     * row is checked — deliberately a plain page property, not a Filament
     * form field, since it's a side-channel to module_ids rather than a
     * value that itself needs form validation.
     */
    public array $moduleDates = [];

    /**
     * Livewire's own updated{Property}() hook — fires whenever moduleDates
     * changes (the modal's Save, or a track/parent toggle clearing an
     * entry). Persists it into the same durable draft as module_ids/
     * selected_users, so it survives Back/Next, a refresh, and resuming
     * from a completely different session via the pending-setup banner.
     */
    public function updatedModuleDates(): void
    {
        $this->saveWizardDraft('moduleDates', $this->moduleDates);
    }

    public int $enrolledCount = 0;

    public int $invitedCount = 0;

    public function mount(): void
    {
        $fill = [
            'module_ids' => [],
            'selected_users' => [],
            // The wizard's fill() is called with an explicit array on every
            // mount(), which bypasses each field's own ->default() (Filament
            // only auto-applies defaults when fill() is passed null). This
            // field is disabled/locked to true, so it must be seeded here or
            // it renders unchecked despite ->default(true) on the component.
            'auto_create_sessions' => true,
        ];

        // Resuming on Run Type/Location before a Training record exists:
        // re-seed from the URL mirrors. Overwritten below if a Training was
        // already created (that's the authoritative source once it exists).
        if ($this->urlIsPilot !== null) {
            $fill['is_pilot'] = $this->urlIsPilot;
        }
        if ($this->urlCountyId) {
            $fill['county_id'] = $this->urlCountyId;
        }
        if ($this->urlFacilityId) {
            $fill['facility_id'] = $this->urlFacilityId;
        }

        // Resuming after a page refresh: the Training/Class records already
        // exist in the DB, but the Wizard's own form state (used for its
        // whole-form validation on final submit) resets on every fresh
        // mount(). Re-seed the earlier steps' fields from the persisted
        // records so a resumed session can still reach Send Invitations.
        if ($this->trainingId) {
            $this->training = Training::find($this->trainingId);

            if ($this->training) {
                $fill['is_pilot'] = (int) $this->training->is_pilot;
                $fill['county_id'] = $this->training->county_id;
                $fill['facility_id'] = $this->training->facility_id;
                $fill['program_id'] = $this->training->program_id;
                $fill['start_date'] = $this->training->start_date;
                $fill['end_date'] = $this->training->end_date;
                $fill['max_participants'] = $this->training->max_participants;

                // EmONC per-row dates, collected via the picker's modal —
                // same durable-draft story as module_ids/selected_users
                // below, just restored here since it's a plain property
                // rather than a form field.
                $this->moduleDates = $this->training->guided_setup_draft['moduleDates'] ?? [];
            }
        }

        if ($this->classId) {
            $this->class = MentorshipClass::find($this->classId);

            if ($this->class) {
                $fill['class_name'] = $this->class->name;
                $fill['class_start_date'] = $this->class->start_date;
                $fill['class_end_date'] = $this->class->end_date;
                $fill['class_description'] = $this->class->description;

                // module_ids/selected_users represent the full desired
                // state (already-assigned modules/mentees are pre-checked,
                // and can be unchecked to remove them) — so the draft, once
                // the user has touched this step, is authoritative even if
                // it lists FEWER ids than what's really assigned (that's a
                // legitimate "I want to remove this" signal, not staleness
                // to correct). Only fall back to "= what's really there"
                // when the step has never been visited (no draft key yet).
                $draft = $this->training->guided_setup_draft ?? [];

                $fill['module_ids'] = array_key_exists('module_ids', $draft)
                    ? $draft['module_ids']
                    : $this->class->classModules()->pluck('program_module_id')->toArray();

                $fill['selected_users'] = array_key_exists('selected_users', $draft)
                    ? $draft['selected_users']
                    : $this->class->participants()->pluck('user_id')->toArray();
            }
        }

        $this->form->fill($fill);
    }

    public function getTitle(): string
    {
        return 'Guided Mentorship Setup';
    }

    public function getSubheading(): ?string
    {
        return "We'll walk through this one step at a time — from creating the mentorship to inviting your mentees.";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Run Type')
                        ->description('Is this a real live mentorship or a pilot/test run?')
                        ->icon('heroicon-o-beaker')
                        ->schema([
                            Forms\Components\Radio::make('is_pilot')
                                ->label('')
                                ->options([
                                    0 => 'Live Mentorship',
                                    1 => 'Pilot Run',
                                ])
                                ->descriptions([
                                    0 => 'Counts in dashboards, KPI badges, and analytics reports.',
                                    1 => 'Excluded from all counts, badges, and analytics. Use for testing.',
                                ])
                                ->default(0)
                                ->required()
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->urlIsPilot = $state === null ? null : (int) $state),
                        ]),
                    Forms\Components\Wizard\Step::make('Location')
                        ->description('Where is this mentorship being conducted?')
                        ->icon('heroicon-o-map-pin')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->options(fn () => \App\Models\County::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('facility_id', null);
                                    $this->urlCountyId = $state === null ? null : (int) $state;
                                    $this->urlFacilityId = null;
                                })
                                ->prefixIcon('heroicon-o-map')
                                ->helperText('Select the county first'),
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
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->urlFacilityId = $state === null ? null : (int) $state)
                                ->prefixIcon('heroicon-o-building-office-2')
                                ->helperText('Facilities load after selecting a county'),
                        ]),
                    Forms\Components\Wizard\Step::make('Program & Schedule')
                        ->description('What program is being mentored, and when?')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            ProgramPicker::make('program_id')
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
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-play'),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->native(false)
                                    ->minDate(fn (Get $get) => $get('start_date') ?? now())
                                    ->afterOrEqual('start_date')
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-stop'),
                                Forms\Components\TextInput::make('max_participants')
                                    ->label('Number of Mentees')
                                    ->numeric()
                                    ->default(10)
                                    ->minValue(2)
                                    ->maxValue(10)
                                    ->suffix('mentees')
                                    ->prefixIcon('heroicon-o-users')
                                    ->helperText('Must be between 2 and 10 mentees.'),
                            ]),
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->createTraining([
                                    'is_pilot' => $get('is_pilot'),
                                    'county_id' => $get('county_id'),
                                    'facility_id' => $get('facility_id'),
                                    'program_id' => $get('program_id'),
                                    'start_date' => $get('start_date'),
                                    'end_date' => $get('end_date'),
                                    'max_participants' => $get('max_participants'),
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
                    Forms\Components\Wizard\Step::make('First Class')
                        ->description("Let's create your first class or cohort.")
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Forms\Components\Placeholder::make('first_class_program_intro')
                                ->label('')
                                ->content(fn () => new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">'.
                                    '<span class="font-semibold text-gray-950 dark:text-white">Program: '.
                                    ($this->training?->program?->name ?? 'this program').
                                    '</span></p>'
                                )),
                            Forms\Components\TextInput::make('class_name')
                                ->label('Class/Cohort Name')
                                ->required()
                                ->placeholder('e.g., January 2027 Cohort')
                                ->maxLength(255),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('class_start_date')
                                    ->label('Start Date')
                                    ->required(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                    ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                    ->native(false)
                                    ->minDate(fn () => $this->training?->start_date)
                                    ->maxDate(fn () => $this->training?->end_date)
                                    ->helperText(fn () => $this->training?->start_date && $this->training?->end_date
                                        ? 'Must be within the mentorship dates: '.$this->training->start_date->format('M j, Y').' – '.$this->training->end_date->format('M j, Y')
                                        : null),
                                Forms\Components\DatePicker::make('class_end_date')
                                    ->label('End Date')
                                    ->required(fn () => ! $this->isEmoncProgram($this->training?->program_id))
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
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->createFirstClass([
                                    'name' => $get('class_name'),
                                    'start_date' => $get('class_start_date'),
                                    'end_date' => $get('class_end_date'),
                                    'description' => $get('class_description'),
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
                    Forms\Components\Wizard\Step::make('Modules')
                        ->description("Now let's add modules to this class. You can skip this and add them later.")
                        ->icon('heroicon-o-book-open')
                        ->schema(function () {
                            if (! $this->training || ! $this->class) {
                                return [
                                    Forms\Components\Placeholder::make('modules_placeholder')
                                        ->label('')
                                        ->content('Modules will be available once the class above is created.'),
                                ];
                            }

                            $programName = $this->training->program?->name ?? 'this program';

                            $intro = Forms\Components\Placeholder::make('modules_intro')
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">'.
                                    "<span class=\"font-semibold text-gray-950 dark:text-white\">Program: {$programName}</span><br>".
                                    'Pick as many or as few modules as you like — one, several, or all of them. '.
                                    "You'll be able to add more later from the class's Modules page.".
                                    '</p>'
                                ));

                            if ($this->isEmoncProgram($this->training?->program_id)) {
                                // Re-seed dates for modules already committed to the
                                // DB. assignModules() clears $this->moduleDates once a
                                // module's dates land on its ClassModule row (see
                                // afterValidation() below) — so after Next then Back,
                                // this property is empty even though the dates are
                                // safely persisted. Without this, the row's bracket
                                // display would go blank despite the data being fine.
                                foreach ($this->class->classModules as $classModule) {
                                    $id = $classModule->program_module_id;
                                    if (! isset($this->moduleDates[$id]) && ($classModule->start_date || $classModule->end_date)) {
                                        $this->moduleDates[$id] = [
                                            'start' => optional($classModule->start_date)->toDateString(),
                                            'end' => optional($classModule->end_date)->toDateString(),
                                        ];
                                    }
                                }

                                $picker = EmoncModulePicker::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->training($this->training)
                                    ->class($this->class)
                                    ->includeAssigned()
                                    ->live()
                                    ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                    ->helperText('Already-added modules/tracks are pre-checked — uncheck one to remove it. Checking a new row opens a quick prompt for its start/end date.')
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

                                $picker = CardCheckboxList::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->options($allModules)
                                    ->default([])
                                    ->live()
                                    ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                    ->helperText('Already-added modules are pre-checked — uncheck one to remove it.');
                            }

                            return [
                                $intro,
                                $picker,
                                Forms\Components\Toggle::make('auto_create_sessions')
                                    ->label('Auto-populate sessions from program template')
                                    ->default(true)
                                    ->disabled()
                                    ->dehydrated(true),
                            ];
                        })
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->assignModules([
                                    'module_ids' => $get('module_ids') ?? [],
                                    'auto_create_sessions' => $get('auto_create_sessions') ?? true,
                                    'module_dates' => $this->moduleDates,
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
                    Forms\Components\Wizard\Step::make('Enroll Mentees')
                        ->description('Who will be mentored in this class? You can skip this and enroll mentees later.')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Forms\Components\Placeholder::make('enroll_mentees_program_intro')
                                ->label('')
                                ->content(fn () => new \Illuminate\Support\HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">'.
                                    '<span class="font-semibold text-gray-950 dark:text-white">Program: '.
                                    ($this->training?->program?->name ?? 'this program').
                                    '</span><br>'.
                                    'Select mentees to enroll in '.
                                    ($this->training?->program?->name ?? 'this program').
                                    '.</p>'
                                )),
                            Forms\Components\Hidden::make('mentee_page')->default(1),
                            Forms\Components\Hidden::make('show_new_mentee_form')->default(false),
                            Forms\Components\TextInput::make('mentee_search')
                                ->label('Search')
                                ->placeholder('Search by name, phone, email, or facility...')
                                ->live(debounce: 400)
                                ->afterStateUpdated(fn (Set $set) => $set('mentee_page', 1))
                                ->prefixIcon('heroicon-o-magnifying-glass'),
                            CardCheckboxList::make('selected_users')
                                ->label('Existing Users')
                                ->options(fn (Get $get) => $this->menteeOptions(
                                    $get('mentee_search'),
                                    (int) ($get('mentee_page') ?? 1),
                                    collect($get('selected_users') ?? [])->map(fn ($id) => (int) $id)->all()
                                ))
                                ->maxSelections(fn () => $this->training?->max_participants)
                                ->default([])
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('selected_users', $state))
                                ->columnSpanFull()
                                ->helperText('Search and check existing users to enroll. Already-enrolled mentees are pre-checked and pinned to the top — uncheck one to remove it.'),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('mentee_previous')
                                    ->label('Previous Page')
                                    ->color('gray')
                                    ->action(fn (Set $set, Get $get) => $set('mentee_page', max(1, (int) $get('mentee_page') - 1))),
                                Forms\Components\Actions\Action::make('mentee_next')
                                    ->label('Next Page')
                                    ->color('gray')
                                    ->action(fn (Set $set, Get $get) => $set('mentee_page', (int) $get('mentee_page') + 1)),
                            ])->columnSpanFull(),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('reveal_new_mentee_form')
                                    ->label(fn (Get $get) => trim((string) $get('mentee_search')) !== ''
                                        ? "Mentee \"{$get('mentee_search')}\" not found — click here to add"
                                        : '+ Add a new mentee')
                                    ->icon('heroicon-o-user-plus')
                                    ->color(fn (Get $get) => trim((string) $get('mentee_search')) !== '' ? 'warning' : 'gray')
                                    ->action(function (Set $set, Get $get) {
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
                                        ->email()
                                        ->placeholder('e.g. jane.wanjiku@moh.go.ke'),
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
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->enrollMentees([
                                    'selected_users' => $get('selected_users') ?? [],
                                    'new_mentee' => $get('new_mentee.email') ? $get('new_mentee') : null,
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
                    Forms\Components\Wizard\Step::make('Send Invitations')
                        ->description('Time to invite your mentees!')
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Forms\Components\Radio::make('recipients')
                                ->label('Who should receive the email?')
                                ->options([
                                    'all' => 'All mentees with email addresses',
                                    'not_sent' => 'Only those not yet invited',
                                ])
                                ->default('all')
                                ->required(),
                        ]),
                ])
                    ->persistStepInQueryString('step')
                    ->skippable(false)
                    ->submitAction(view('filament.pages.partials.guided-wizard-submit')),
            ])
            ->statePath('data');
    }

    /**
     * Creates the Training record. Mirrors
     * CreateMentorshipTraining::mutateFormDataBeforeCreate() exactly.
     */
    public function createTraining(array $data): Training
    {
        $this->training = app(MentorshipWizardService::class)->createTraining($data, $this->training);
        $this->trainingId = $this->training->id;

        return $this->training;
    }

    /**
     * Creates the first MentorshipClass. Mirrors
     * ManageMentorshipClasses::createClass() exactly.
     */
    public function createFirstClass(array $data): MentorshipClass
    {
        $this->class = app(MentorshipWizardService::class)->createFirstClass($data, $this->training, $this->class);
        $this->classId = $this->class->id;

        return $this->class;
    }

    /**
     * Syncs the class's modules to the checked set: adds newly-picked ones
     * (mirroring ManageClassModules's "Add Modules" persistence — dates/
     * notes fields intentionally omitted, see design spec) and removes any
     * that were unchecked, since module_ids is pre-filled with what's
     * already assigned so a user can change their mind.
     */
    public function assignModules(array $data): int
    {
        $created = app(MentorshipWizardService::class)->assignModules($data, $this->training, $this->class);

        // Applied (or the module was removed) — either way this side-channel
        // shouldn't carry over and get misapplied to a different module
        // picked in a later pass. Direct property assignment doesn't fire
        // updatedModuleDates(), so clear it explicitly (the service already
        // cleared the persisted draft copy).
        $this->moduleDates = [];

        return $created;
    }

    /**
     * Enrolls selected existing users and/or a newly-created mentee.
     * Mirrors ManageClassMentees's "Add from List" / "Add Mentee" logic.
     */
    public function enrollMentees(array $data): int
    {
        $count = app(MentorshipWizardService::class)->enrollMentees($data, $this->class);
        $this->enrolledCount = $count;

        return $count;
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        try {
            $this->sendInvitations([
                'recipients' => $data['recipients'] ?? 'all',
            ]);
        } catch (\Throwable $e) {
            // Note: unlike afterValidation() on a Wizard\Step (caught internally
            // by Wizard.php), this submit() method is invoked directly by
            // wire:submit — nothing upstream catches Halt here, so we handle
            // the failure inline instead and simply stay on this step.
            Notification::make()
                ->danger()
                ->title('Something Went Wrong')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Sends enrollment invitation emails. Mirrors ManageClassMentees's
     * "Send Invitations" bulk action exactly.
     */
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

    /**
     * Shows a danger notification and halts the wizard on the current
     * step. Filament's Wizard component catches Halt exceptions thrown
     * inside afterValidation() and keeps the user on the current step
     * instead of advancing or crashing — see
     * vendor/filament/forms/src/Components/Wizard.php:105-107.
     */
    private function stepFailed(\Throwable $e): never
    {
        Notification::make()
            ->danger()
            ->title('Something Went Wrong')
            ->body($e->getMessage())
            ->send();

        throw new Halt;
    }

    /**
     * Builds the Enroll Mentees id => label options, with already-selected
     * mentees pinned to the top (fetched separately so they stay visible
     * even if a later search/page filters them out of the main results).
     */
    private function menteeOptions(?string $search, int $page, array $selectedIds): array
    {
        return app(MentorshipWizardService::class)->menteeOptions($search, $page, $selectedIds);
    }

    /**
     * EmONC-only guard for the Modules step: every currently-checked
     * module/track must have a start and end date recorded in
     * $this->moduleDates (set via the picker's per-row modal) before the
     * step can advance. Returns the first validation error found, or null
     * if every selected id has a valid date range.
     */
    public function validateModuleDates(array $moduleIds): ?string
    {
        return app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates);
    }

    /**
     * Persists an in-progress Modules/Enroll Mentees checkbox selection to
     * the Training record so it survives a completely fresh session (e.g.
     * the pending-setup banner's Continue link), not just a same-tab
     * refresh. Safe to call repeatedly — merges into the existing draft.
     */
    private function saveWizardDraft(string $key, mixed $state): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->saveWizardDraft($this->training, $key, $state);
    }

    /**
     * Drops one key from the draft once its picks have become real records
     * (assignModules()/enrollMentees()), so it doesn't linger stale.
     */
    private function clearWizardDraft(string $key): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->clearWizardDraft($this->training, $key);
    }

    private function isEmoncProgram(?int $programId): bool
    {
        return app(MentorshipWizardService::class)->isEmoncProgram($programId);
    }
}
