<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\EmoncModulePicker;
use App\Filament\Forms\Components\ProgramPicker;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
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
use Illuminate\Support\Str;

class GuidedMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.guided-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public int $enrolledCount = 0;

    public int $invitedCount = 0;

    public function mount(): void
    {
        $this->form->fill();
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
                                ->inline(false),
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
                                ->afterStateUpdated(fn (Set $set) => $set('facility_id', null))
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
                                    ->after('start_date')
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-stop'),
                                Forms\Components\TextInput::make('max_participants')
                                    ->label('Number of Mentees')
                                    ->numeric()
                                    ->default(20)
                                    ->suffix('mentees')
                                    ->prefixIcon('heroicon-o-users'),
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
                                    ->maxDate(fn () => $this->training?->end_date),
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

                                $picker = Forms\Components\CheckboxList::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->options($available)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->helperText('Optional — you can add modules later from the class Modules page.');
                            }

                            return [
                                $picker,
                                Forms\Components\Toggle::make('auto_create_sessions')
                                    ->label('Auto-populate sessions from program template')
                                    ->default(true),
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
                ])
                    ->persistStepInQueryString(null)
                    ->skippable(false),
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
        $data['identifier'] = 'MT-'.strtoupper(Str::random(6));

        $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
        $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
        $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

        $data['title'] = trim(implode(' - ', array_filter([
            $program?->name ?? 'MNCH Mentorship',
            $facility?->name,
            $date,
        ])));

        $this->training = Training::create($data);

        return $this->training;
    }

    /**
     * Creates the first MentorshipClass. Mirrors
     * ManageMentorshipClasses::createClass() exactly.
     */
    public function createFirstClass(array $data): MentorshipClass
    {
        $this->class = MentorshipClass::create([
            'training_id' => $this->training->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

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
