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

    public function mount(): void
    {
        $this->form->fill([]);
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
            ])
            ->statePath('data');
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
