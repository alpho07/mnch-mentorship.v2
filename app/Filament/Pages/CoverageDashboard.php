<?php

namespace App\Filament\Pages;

use App\Models\Training;
use App\Models\Program;
use App\Models\Division;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Cadre;
use App\Models\Department;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CoverageDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.coverage-dashboard';
    protected static string $routePath = '/coverage-dashboard';
    protected static ?string $title = 'Coverage Dashboard';
    protected static ?string $navigationLabel = 'Coverage Dashboard';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?int $navigationSort = 1;

     public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // Form data property
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'training_months' => $this->getDefaultMonths(),
            'training_status' => ['completed'],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filters')
                    ->description('Select filters to analyze training coverage data')
                    ->schema([
                        // Date Period Filter (Month-Year multi-select)
                        Select::make('training_months')
                            ->label('Training Months')
                            ->placeholder('Select training months...')
                            ->multiple()
                            ->searchable()
                            ->options($this->getTrainingMonthOptions())
                            ->columnSpan(2),

                        // Geographic Filters (Training-Data-Driven Cascading)
                        Select::make('divisions')
                            ->label('Divisions')
                            ->placeholder('All divisions')
                            ->multiple()
                            ->searchable()
                            ->options($this->getDivisionsWithTrainingData())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('counties', null);
                                $set('subcounties', null);
                                $set('facilities', null);
                            }),

                        Select::make('counties')
                            ->label('Counties')
                            ->placeholder('All counties')
                            ->multiple()
                            ->searchable()
                            ->options(function (callable $get) {
                                $divisions = $get('divisions');
                                return $this->getCountiesWithTrainingData($divisions);
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('subcounties', null);
                                $set('facilities', null);
                            }),

                        Select::make('subcounties')
                            ->label('Subcounties')
                            ->placeholder('All subcounties')
                            ->multiple()
                            ->searchable()
                            ->options(function (callable $get) {
                                $counties = $get('counties');
                                $divisions = $get('divisions');
                                return $this->getSubcountiesWithTrainingData($counties, $divisions);
                            })
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('facilities', null);
                            }),

                        // Facility Filters (Training-Data-Driven)
                        Select::make('facility_types')
                            ->label('Facility Types')
                            ->placeholder('All facility types')
                            ->multiple()
                            ->searchable()
                            ->options($this->getFacilityTypesWithTrainingData()),

                        Select::make('facilities')
                            ->label('Facilities')
                            ->placeholder('All facilities')
                            ->multiple()
                            ->searchable()
                            ->options(function (callable $get) {
                                $subcounties = $get('subcounties');
                                $facilityTypes = $get('facility_types');
                                $counties = $get('counties');
                                $divisions = $get('divisions');

                                return $this->getFacilitiesWithTrainingData([
                                    'subcounties' => $subcounties,
                                    'facility_types' => $facilityTypes,
                                    'counties' => $counties,
                                    'divisions' => $divisions,
                                ]);
                            }),

                        Select::make('hub_type')
                            ->label('Hub Type')
                            ->placeholder('All facilities')
                            ->options([
                                'hub' => 'Hub Facilities Only',
                                'spoke' => 'Spoke Facilities Only',
                                'standalone' => 'Standalone Facilities',
                            ]),

                        // Program & Training Filters (Training-Data-Driven)
                        Select::make('programs')
                            ->label('Programs')
                            ->placeholder('All programs')
                            ->multiple()
                            ->searchable()
                            ->options($this->getProgramsWithTrainingData()),

                        /*Select::make('training_status')
                            ->label('Training Status')
                            ->placeholder('All statuses')
                            ->multiple()
                            ->options([
                                'completed' => 'Completed',
                                'ongoing' => 'Ongoing',
                                'upcoming' => 'Upcoming',
                            ]),*/

                        // Professional Filters (Training-Data-Driven)
                        Select::make('cadres')
                            ->label('Cadres')
                            ->placeholder('All cadres')
                            ->multiple()
                            ->searchable()
                            ->options($this->getCadresWithTrainingData()),

                        Select::make('departments')
                            ->label('Departments')
                            ->placeholder('All departments')
                            ->multiple()
                            ->searchable()
                            ->options($this->getDepartmentsWithTrainingData()),

                        Select::make('participant_type')
                            ->label('Participant Type')
                            ->placeholder('All participants')
                            ->multiple()
                            ->options([
                                'tot' => 'Trainers of Trainers (TOT)',
                                'regular' => 'Regular Participants',
                            ]),
                    ])
                    ->columns(3)
            ])
            ->statePath('data');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Training Coverage Analytics';
    }

    public function getHeading(): string
    {
        return 'Training Coverage Analytics';
    }

    /**
     * Get current filters for widgets
     */
    public function getFilters(): array
    {
        return $this->form->getState();
    }

    /**
     * Get training month options from actual training data
     */
    protected function getTrainingMonthOptions(): array
    {
        return cache()->remember('training_months_options', 300, function () {
            $months = Training::selectRaw('DATE_FORMAT(start_date, "%Y-%m") as month_year, COUNT(*) as training_count')
                ->whereNotNull('start_date')
                ->where('start_date', '!=', '0000-00-00')
                ->groupBy('month_year')
                ->orderBy('month_year', 'desc')
                ->get()
                ->mapWithKeys(function ($item) {
                    try {
                        $date = Carbon::createFromFormat('Y-m', $item->month_year);
                        $label = $date->format('M-Y') . " ({$item->training_count} trainings)";
                        return [$item->month_year => $label];
                    } catch (\Exception $e) {
                        return [];
                    }
                })
                ->toArray();

            return $months;
        });
    }

    /**
     * Get default months (last 6 months)
     */
    protected function getDefaultMonths(): array
    {
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $date = Carbon::now()->subMonths($i);
            $months[] = $date->format('Y-m');
        }

        return $months;
    }

    /**
     * Get divisions that have training data
     */
    protected function getDivisionsWithTrainingData(): array
    {
        return cache()->remember('divisions_with_training_data', 300, function () {
            return Division::withCount(['counties as training_count' => function ($query) {
                    $query->whereHas('subcounties.facilities.trainings');
                }])
                ->having('training_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($division) {
                    return [$division->id => "{$division->name} ({$division->training_count} counties)"];
                })
                ->toArray();
        });
    }

    /**
     * Get counties with training data (optionally filtered by divisions)
     */
    protected function getCountiesWithTrainingData(?array $divisions = null): array
    {
        $cacheKey = 'counties_with_training_data_' . md5(serialize($divisions));

        return cache()->remember($cacheKey, 300, function () use ($divisions) {
            $query = County::select('counties.*')
                ->join('subcounties', 'counties.id', '=', 'subcounties.county_id')
                ->join('facilities', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->join('trainings', 'facilities.id', '=', 'trainings.facility_id')
                ->selectRaw('counties.*, COUNT(DISTINCT trainings.id) as training_count');

            if (!empty($divisions)) {
                $query->whereIn('counties.division_id', $divisions);
            }

            return $query->groupBy('counties.id', 'counties.name', 'counties.division_id', 'counties.uid', 'counties.created_at', 'counties.updated_at')
                ->having('training_count', '>', 0)
                ->orderBy('counties.name')
                ->get()
                ->mapWithKeys(function ($county) {
                    return [$county->id => "{$county->name} ({$county->training_count} trainings)"];
                })
                ->toArray();
        });
    }

    /**
     * Get subcounties with training data (optionally filtered by counties/divisions)
     */
    protected function getSubcountiesWithTrainingData(?array $counties = null, ?array $divisions = null): array
    {
        $cacheKey = 'subcounties_with_training_data_' . md5(serialize(compact('counties', 'divisions')));

        return cache()->remember($cacheKey, 300, function () use ($counties, $divisions) {
            $query = Subcounty::select('subcounties.*')
                ->join('facilities', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->join('trainings', 'facilities.id', '=', 'trainings.facility_id')
                ->selectRaw('subcounties.*, COUNT(DISTINCT trainings.id) as training_count');

            if (!empty($counties)) {
                $query->whereIn('subcounties.county_id', $counties);
            } elseif (!empty($divisions)) {
                $query->join('counties', 'subcounties.county_id', '=', 'counties.id')
                     ->whereIn('counties.division_id', $divisions);
            }

            return $query->groupBy('subcounties.id', 'subcounties.name', 'subcounties.county_id', 'subcounties.uid', 'subcounties.created_at', 'subcounties.updated_at')
                ->having('training_count', '>', 0)
                ->orderBy('subcounties.name')
                ->get()
                ->mapWithKeys(function ($subcounty) {
                    return [$subcounty->id => "{$subcounty->name} ({$subcounty->training_count} trainings)"];
                })
                ->toArray();
        });
    }

    /**
     * Get facility types with training data
     */
    protected function getFacilityTypesWithTrainingData(): array
    {
        return cache()->remember('facility_types_with_training_data', 300, function () {
            return FacilityType::withCount(['facilities as training_count' => function ($query) {
                    $query->whereHas('trainings');
                }])
                ->having('training_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($facilityType) {
                    return [$facilityType->id => "{$facilityType->name} ({$facilityType->training_count} facilities)"];
                })
                ->toArray();
        });
    }

    /**
     * Get facilities with training data (with cascading filters)
     */
    protected function getFacilitiesWithTrainingData(array $filters = []): array
    {
        $cacheKey = 'facilities_with_training_data_' . md5(serialize($filters));

        return cache()->remember($cacheKey, 180, function () use ($filters) {
            $query = Facility::select('facilities.*')
                ->join('trainings', 'facilities.id', '=', 'trainings.facility_id')
                ->selectRaw('facilities.*, COUNT(DISTINCT trainings.id) as training_count');

            // Apply cascading geographic filters
            if (!empty($filters['subcounties'])) {
                $query->whereIn('facilities.subcounty_id', $filters['subcounties']);
            } elseif (!empty($filters['counties'])) {
                $query->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
                     ->whereIn('subcounties.county_id', $filters['counties']);
            } elseif (!empty($filters['divisions'])) {
                $query->join('subcounties', 'facilities.subcounty_id', '=', 'subcounties.id')
                     ->join('counties', 'subcounties.county_id', '=', 'counties.id')
                     ->whereIn('counties.division_id', $filters['divisions']);
            }

            // Apply facility type filter
            if (!empty($filters['facility_types'])) {
                $query->whereIn('facilities.facility_type_id', $filters['facility_types']);
            }

            return $query->groupBy('facilities.id', 'facilities.name', 'facilities.subcounty_id', 'facilities.facility_type_id', 'facilities.is_hub', 'facilities.hub_id', 'facilities.mfl_code', 'facilities.lat', 'facilities.long', 'facilities.uid', 'facilities.created_at', 'facilities.updated_at')
                ->having('training_count', '>', 0)
                ->orderBy('facilities.name')
                ->limit(500)
                ->get()
                ->mapWithKeys(function ($facility) {
                    $hubIndicator = $facility->is_hub ? ' [HUB]' : ($facility->hub_id ? ' [SPOKE]' : '');
                    return [$facility->id => "{$facility->name}{$hubIndicator} ({$facility->training_count} trainings)"];
                })
                ->toArray();
        });
    }

    /**
     * Get programs that have training data
     */
    protected function getProgramsWithTrainingData(): array
    {
        return cache()->remember('programs_with_training_data', 300, function () {
            return Program::withCount(['trainings', 'trainings as participants_count' => function ($query) {
                    $query->join('training_participants', 'trainings.id', '=', 'training_participants.training_id');
                }])
                ->having('trainings_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($program) {
                    return [$program->id => "{$program->name} ({$program->trainings_count} trainings, {$program->participants_count} participants)"];
                })
                ->toArray();
        });
    }

    /**
     * Get cadres that have participated in trainings
     */
    protected function getCadresWithTrainingData(): array
    {
        return cache()->remember('cadres_with_training_data', 300, function () {
            return Cadre::withCount('trainingParticipants')
                ->having('training_participants_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($cadre) {
                    return [$cadre->id => "{$cadre->name} ({$cadre->training_participants_count} participants)"];
                })
                ->toArray();
        });
    }

    /**
     * Get departments that have participated in trainings
     */
    protected function getDepartmentsWithTrainingData(): array
    {
        return cache()->remember('departments_with_training_data', 300, function () {
            return Department::withCount('trainingParticipants')
                ->having('training_participants_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($department) {
                    return [$department->id => "{$department->name} ({$department->training_participants_count} participants)"];
                })
                ->toArray();
        });
    }

    /**
     * Get filtered training query based on current filters
     */
    public function getFilteredTrainingsQuery()
    {
        $filters = $this->getFilters();
        $query = Training::query()->with([
            'facility.subcounty.county.division',
            'facility.facilityType',
            'program',
            'participants.cadre',
            'participants.department'
        ]);

        // Apply month filter
        if (!empty($filters['training_months'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['training_months'] as $month) {
                    $q->orWhereRaw('DATE_FORMAT(start_date, "%Y-%m") = ?', [$month]);
                }
            });
        }

        // Apply geographic filters
        if (!empty($filters['divisions'])) {
            $query->whereHas('facility.subcounty.county', function ($q) use ($filters) {
                $q->whereIn('division_id', $filters['divisions']);
            });
        }

        if (!empty($filters['counties'])) {
            $query->whereHas('facility.subcounty', function ($q) use ($filters) {
                $q->whereIn('county_id', $filters['counties']);
            });
        }

        if (!empty($filters['subcounties'])) {
            $query->whereHas('facility', function ($q) use ($filters) {
                $q->whereIn('subcounty_id', $filters['subcounties']);
            });
        }

        if (!empty($filters['facility_types'])) {
            $query->whereHas('facility', function ($q) use ($filters) {
                $q->whereIn('facility_type_id', $filters['facility_types']);
            });
        }

        if (!empty($filters['facilities'])) {
            $query->whereIn('facility_id', $filters['facilities']);
        }

        // Apply hub type filter
        if (!empty($filters['hub_type'])) {
            $query->whereHas('facility', function ($q) use ($filters) {
                switch ($filters['hub_type']) {
                    case 'hub':
                        $q->where('is_hub', true);
                        break;
                    case 'spoke':
                        $q->where('is_hub', false)->whereNotNull('hub_id');
                        break;
                    case 'standalone':
                        $q->where('is_hub', false)->whereNull('hub_id');
                        break;
                }
            });
        }

        // Apply program filter
        if (!empty($filters['programs'])) {
            $query->whereIn('program_id', $filters['programs']);
        }

        // Apply status filter
        if (!empty($filters['training_status'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['training_status'] as $status) {
                    switch ($status) {
                        case 'completed':
                            $q->orWhere('end_date', '<', now());
                            break;
                        case 'ongoing':
                            $q->orWhere(function ($subQ) {
                                $subQ->where('start_date', '<=', now())
                                    ->where('end_date', '>=', now());
                            });
                            break;
                        case 'upcoming':
                            $q->orWhere('start_date', '>', now());
                            break;
                    }
                }
            });
        }

        // Apply professional filters
        if (!empty($filters['cadres']) || !empty($filters['departments']) || !empty($filters['participant_type'])) {
            $query->whereHas('participants', function ($q) use ($filters) {
                if (!empty($filters['cadres'])) {
                    $q->whereIn('cadre_id', $filters['cadres']);
                }

                if (!empty($filters['departments'])) {
                    $q->whereIn('department_id', $filters['departments']);
                }

                if (!empty($filters['participant_type'])) {
                    if (in_array('tot', $filters['participant_type']) && !in_array('regular', $filters['participant_type'])) {
                        $q->where('is_tot', true);
                    } elseif (in_array('regular', $filters['participant_type']) && !in_array('tot', $filters['participant_type'])) {
                        $q->where('is_tot', false);
                    }
                }
            });
        }

        return $query;
    }
}
