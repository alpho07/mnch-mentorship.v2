<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\CardCheckboxList;
use App\Filament\Forms\Components\EmoncModulePicker;
use App\Filament\Forms\Components\ProgramPicker;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Mail\MenteeEnrollmentInvitationMail;
use App\Models\Cadre;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\ModuleUsageService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class GuidedMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.guided-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

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
            }
        }

        if ($this->classId) {
            $this->class = MentorshipClass::find($this->classId);

            if ($this->class) {
                $fill['class_name'] = $this->class->name;
                $fill['class_start_date'] = $this->class->start_date;
                $fill['class_end_date'] = $this->class->end_date;
                $fill['class_description'] = $this->class->description;
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
                                $picker = EmoncModulePicker::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->training($this->training)
                                    ->class($this->class)
                                    ->helperText('Click a module to select all its tracks, or pick tracks individually.');
                            } else {
                                $available = app(ModuleUsageService::class)
                                    ->getAvailableModules($this->training, $this->class)
                                    ->mapWithKeys(fn ($module) => [$module->id => $module->name])
                                    ->toArray();

                                $picker = CardCheckboxList::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->options($available)
                                    ->default([])
                                    ->helperText('Optional — you can add modules later from the class Modules page.');
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
                                ->options(fn (Get $get) => $this->searchMenteeUsers(
                                    $get('mentee_search'),
                                    (int) ($get('mentee_page') ?? 1)
                                )->mapWithKeys(fn ($u) => [
                                    $u->id => implode(' · ', array_filter([
                                        $u->name,
                                        $u->phone,
                                        $u->email,
                                        $u->facility ? "{$u->facility->name}".
                                            ($u->facility->mfl_code ? " (MFL {$u->facility->mfl_code})" : '') : null,
                                    ])),
                                ])->toArray())
                                ->default([])
                                ->columnSpanFull()
                                ->helperText('Search and check existing users to enroll.'),
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
        $data['type'] = 'facility_mentorship';
        $data['mentor_id'] = auth()->id();

        $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
        $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
        $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

        $data['title'] = trim(implode(' - ', array_filter([
            $program?->name ?? 'MNCH Mentorship',
            $facility?->name,
            $date,
        ])));

        if ($this->training) {
            // Resuming an earlier step (e.g. after a page refresh, or clicking
            // Back then Next again) — update the existing record instead of
            // creating a duplicate.
            $this->training->update($data);
        } else {
            $data['identifier'] = 'MT-'.strtoupper(Str::random(6));
            $this->training = Training::create($data);
        }

        $this->trainingId = $this->training->id;

        return $this->training;
    }

    /**
     * Creates the first MentorshipClass. Mirrors
     * ManageMentorshipClasses::createClass() exactly.
     */
    public function createFirstClass(array $data): MentorshipClass
    {
        $payload = [
            'training_id' => $this->training->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];

        if ($this->class) {
            // Resuming an earlier step — update instead of duplicating.
            $this->class->update($payload);
        } else {
            $this->class = MentorshipClass::create($payload + [
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
        }

        $this->classId = $this->class->id;

        return $this->class;
    }

    /**
     * Assigns modules to the class. Mirrors the persistence portion of
     * ManageClassModules's "Add Modules" action exactly (dates/notes
     * fields are intentionally omitted — see design spec).
     */
    public function assignModules(array $data): int
    {
        $moduleIds = $data['module_ids'] ?? [];

        if (empty($moduleIds)) {
            return 0;
        }

        $service = app(ModuleUsageService::class);
        $createdModuleIds = [];

        $created = $service->assignModulesToClass(
            $this->training,
            $this->class,
            $moduleIds,
            null,
            function (ClassModule $classModule) use (&$createdModuleIds) {
                $createdModuleIds[] = $classModule->id;
            }
        );

        if (($data['auto_create_sessions'] ?? true) && $created > 0) {
            $this->class->load('classModules');
            foreach ($this->class->classModules as $classModule) {
                if (method_exists($classModule, 'autoCreateSessions')) {
                    $classModule->autoCreateSessions();
                }
            }
        }

        return $created;
    }

    /**
     * Enrolls selected existing users and/or a newly-created mentee.
     * Mirrors ManageClassMentees's "Add from List" / "Add Mentee" logic.
     */
    public function enrollMentees(array $data): int
    {
        $service = app(EnrollmentService::class);
        $count = 0;

        foreach ($data['selected_users'] ?? [] as $userId) {
            $user = User::find($userId);
            if ($user && ! $service->isEnrolled($user->id, $this->class->id)) {
                $service->enrollInClass($user, $this->class, 'manual');
                $count++;
            }
        }

        $newMentee = $data['new_mentee'] ?? null;
        if (! empty($newMentee['email'])) {
            $existing = User::where('email', $newMentee['email'])->first();

            if ($existing) {
                if (! $service->isEnrolled($existing->id, $this->class->id)) {
                    $service->enrollInClass($existing, $this->class, 'manual');
                    $count++;
                }
            } else {
                $displayName = trim(implode(' ', array_filter([
                    $newMentee['first_name'] ?? null,
                    $newMentee['last_name'] ?? null,
                ])));

                $user = User::create([
                    'first_name' => $newMentee['first_name'] ?? null,
                    'last_name' => $newMentee['last_name'] ?? null,
                    'name' => $displayName,
                    'email' => $newMentee['email'],
                    'phone' => $newMentee['phone'] ?? null,
                    'cadre_id' => $newMentee['cadre_id'] ?? null,
                    'department_id' => $newMentee['department_id'] ?? null,
                    'facility_id' => $newMentee['facility_id'] ?? null,
                    'password' => Hash::make('123456'),
                    'status' => 'active',
                    'role' => 'mentee',
                ]);

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('mentee');
                    } catch (\Exception) {
                    }
                }

                $service->enrollInClass($user, $this->class, 'manual');
                $count++;
            }
        }

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
        if (! $this->class->enrollment_token) {
            $this->class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        } else {
            $this->class->update(['enrollment_link_active' => true]);
        }
        $this->class->refresh();

        $query = ClassParticipant::where('mentorship_class_id', $this->class->id)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->with('user');

        if (($data['recipients'] ?? 'all') === 'not_sent') {
            $query->whereNull('invitation_sent_at');
        }

        $participants = $query->get();
        $sent = 0;
        $resent = 0;

        foreach ($participants as $record) {
            $isResend = (bool) $record->invitation_sent_at;

            Mail::to($record->user->email)->send(new MenteeEnrollmentInvitationMail(
                $record->user,
                $this->class,
                $record,
                $isResend
            ));

            $record->update(['invitation_sent_at' => now()]);
            $isResend ? $resent++ : $sent++;
        }

        $this->invitedCount = $sent + $resent;
        $this->completed = true;

        return ['sent' => $sent, 'resent' => $resent];
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
     * Searches active users for the Enroll Mentees step. Mirrors
     * ManageClassMentees's "Add from List" query exactly (search across
     * name/phone/email/facility, paginated 25 at a time).
     */
    private function searchMenteeUsers(?string $search, int $page, int $perPage = 25): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->with(['facility'])
            ->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('mfl_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
    }

    private function isEmoncProgram(?int $programId): bool
    {
        if (! $programId) {
            return false;
        }

        $program = Program::find($programId);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
}
