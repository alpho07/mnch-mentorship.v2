<?php

namespace App\Filament\Pages;

use App\Services\TrainingAnalyticsService;
use App\Traits\HasTrainingFilters;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class TrainingCoverageDashboard extends Page
{
    use HasTrainingFilters;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Training Dashboard';
    protected static ?string $navigationGroup = 'Analytics & Reports';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.training-coverage-dashboard';
    protected static ?string $title = 'Training Coverage Dashboard';
    protected static ?string $slug = 'training-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // Filter properties
    public array $years = [];
    public array $quarters = [];
    public array $months = [];
    public array $approaches = [];
    public array $counties = [];
    public array $subcounties = [];
    public array $facilities = [];
    public array $facility_types = [];
    public array $programs = [];
    public array $cadres = [];
    public array $departments = [];
    public array $organizers = [];

    protected TrainingAnalyticsService $analyticsService;

    public function boot(): void
    {
        $this->analyticsService = app(TrainingAnalyticsService::class);
    }

    public function mount(): void
    {
        $savedFilters = $this->getCurrentFilters();

        // Load saved filters into properties
        $this->years = $savedFilters['years'] ?? [];
        $this->quarters = $savedFilters['quarters'] ?? [];
        $this->months = $savedFilters['months'] ?? [];
        $this->approaches = $savedFilters['approaches'] ?? [];
        $this->counties = $savedFilters['counties'] ?? [];
        $this->subcounties = $savedFilters['subcounties'] ?? [];
        $this->facilities = $savedFilters['facilities'] ?? [];
        $this->facility_types = $savedFilters['facility_types'] ?? [];
        $this->programs = $savedFilters['programs'] ?? [];
        $this->cadres = $savedFilters['cadres'] ?? [];
        $this->departments = $savedFilters['departments'] ?? [];
        $this->organizers = $savedFilters['organizers'] ?? [];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Training Coverage Dashboard';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filters')
                    ->description('Select filters to analyze training coverage data')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                // Time Period Filters
                                Forms\Components\Select::make('years')
                                    ->label('Years')
                                    ->multiple()
                                    ->options($this->getAvailableYears())
                                    ->placeholder('Select years')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('quarters')
                                    ->label('Quarters')
                                    ->multiple()
                                    ->options($this->getAvailableQuarters())
                                    ->placeholder('Select quarters')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('months')
                                    ->label('Months')
                                    ->multiple()
                                    ->options($this->getAvailableMonths())
                                    ->placeholder('Select months')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('approaches')
                                    ->label('Training Approach')
                                    ->multiple()
                                    ->options([
                                        'onsite' => 'Onsite',
                                        'virtual' => 'Virtual',
                                        'hybrid' => 'Hybrid',
                                    ])
                                    ->placeholder('All approaches')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),
                            ]),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                // Geographic Filters (Cascading)
                                Forms\Components\Select::make('counties')
                                    ->label('Counties')
                                    ->multiple()
                                    ->searchable()
                                    ->options($this->getAvailableCounties($this->getFiltersArray()))
                                    ->placeholder('All counties')
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->counties = $state ?? [];
                                        $this->subcounties = []; // Reset dependent filters
                                        $this->facilities = [];
                                        $this->updateFilters();
                                    }),

                                Forms\Components\Select::make('subcounties')
                                    ->label('Subcounties')
                                    ->multiple()
                                    ->searchable()
                                    ->options($this->getAvailableSubcounties($this->getFiltersArray()))
                                    ->placeholder('All subcounties')
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->subcounties = $state ?? [];
                                        $this->facilities = []; // Reset dependent filter
                                        $this->updateFilters();
                                    }),

                                Forms\Components\Select::make('facilities')
                                    ->label('Facilities')
                                    ->multiple()
                                    ->searchable()
                                    ->options($this->getAvailableFacilities($this->getFiltersArray()))
                                    ->placeholder('All facilities')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('facility_types')
                                    ->label('Facility Types')
                                    ->multiple()
                                    ->options($this->getAvailableFacilityTypes($this->getFiltersArray()))
                                    ->placeholder('All facility types')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),
                            ]),

                        Forms\Components\Grid::make(4)
                            ->schema([
                                // Program and Participant Filters
                                Forms\Components\Select::make('programs')
                                    ->label('Programs')
                                    ->multiple()
                                    ->options($this->getAvailablePrograms($this->getFiltersArray()))
                                    ->placeholder('All programs')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('cadres')
                                    ->label('Cadres')
                                    ->multiple()
                                    ->options($this->getAvailableCadres($this->getFiltersArray()))
                                    ->placeholder('All cadres')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('departments')
                                    ->label('Departments')
                                    ->multiple()
                                    ->options($this->getAvailableDepartments($this->getFiltersArray()))
                                    ->placeholder('All departments')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),

                                Forms\Components\Select::make('organizers')
                                    ->label('Organizers')
                                    ->multiple()
                                    ->searchable()
                                    ->options($this->getAvailableOrganizers($this->getFiltersArray()))
                                    ->placeholder('All organizers')
                                    ->live()
                                    ->afterStateUpdated(fn() => $this->updateFilters()),
                            ]),
                    ])
                    ->collapsible()
                    ->persistCollapsed()
                    ->columns(1),
            ]);
    }

    // Helper method to convert properties to filters array
    protected function getFiltersArray(): array
    {
        return [
            'years' => $this->years,
            'quarters' => $this->quarters,
            'months' => $this->months,
            'approaches' => $this->approaches,
            'counties' => $this->counties,
            'subcounties' => $this->subcounties,
            'facilities' => $this->facilities,
            'facility_types' => $this->facility_types,
            'programs' => $this->programs,
            'cadres' => $this->cadres,
            'departments' => $this->departments,
            'organizers' => $this->organizers,
        ];
    }

    // Method called when filters change
    protected function updateFilters(): void
    {
        $this->saveFilters($this->getFiltersArray());

        // Dispatch event to all chart components
        $this->dispatch('filtersUpdated', filters: $this->getFiltersArray());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->updateFilters();
                }),

            Action::make('reset_filters')
                ->label('Reset Filters')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(function () {
                    $this->years = [];
                    $this->quarters = [];
                    $this->months = [];
                    $this->approaches = [];
                    $this->counties = [];
                    $this->subcounties = [];
                    $this->facilities = [];
                    $this->facility_types = [];
                    $this->programs = [];
                    $this->cadres = [];
                    $this->departments = [];
                    $this->organizers = [];

                    $this->saveFilters([]);
                    $this->dispatch('filtersUpdated', filters: []);
                }),

            Action::make('view_table')
                ->label('View Table')
                ->icon('heroicon-o-table-cells')
                ->color('info')
                ->url('/admin/training-coverages'),

            Action::make('export')
                ->label('Export Data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $this->notify('success', 'Export functionality coming soon!');
                }),
        ];
    }

    // Methods to provide data to the Blade view
    public function getCoreStats(): array
    {
        return $this->analyticsService->getCoreStats($this->getFiltersArray());
    }

    public function getMonthlyTrends(): array
    {
        return $this->analyticsService->getMonthlyTrends($this->getFiltersArray());
    }

    public function getCountyDistribution(): array
    {
        return $this->analyticsService->getCountyDistribution($this->getFiltersArray());
    }

    public function getCadreDistribution(): array
    {
        return $this->analyticsService->getCadreDistribution($this->getFiltersArray());
    }

    public function getFacilityTypeDistribution(): array
    {
        return $this->analyticsService->getFacilityTypeDistribution($this->getFiltersArray());
    }

    public function getApproachDistribution(): array
    {
        return $this->analyticsService->getApproachDistribution($this->getFiltersArray());
    }

    public function getRetentionAnalysis(): array
    {
        return $this->analyticsService->getRetentionAnalysis($this->getFiltersArray());
    }

    public function getTopOrganizers(): array
    {
        return $this->analyticsService->getTopOrganizers($this->getFiltersArray(), 10);
    }

    // Method to get current applied filters summary
    public function getAppliedFiltersSummary(): array
    {
        $summary = [];
        $filters = $this->getFiltersArray();

        if (!empty($filters['years'])) {
            $summary['Years'] = implode(', ', $filters['years']);
        }

        if (!empty($filters['quarters'])) {
            $quarters = array_map(function ($q) {
                return str_replace('-', ' ', $q);
            }, $filters['quarters']);
            $summary['Quarters'] = implode(', ', $quarters);
        }

        if (!empty($filters['months'])) {
            $months = array_map(function ($m) {
                [$year, $month] = explode('-', $m);
                return \Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y');
            }, $filters['months']);
            $summary['Months'] = implode(', ', $months);
        }

        if (!empty($filters['counties'])) {
            $counties = \App\Models\County::whereIn('id', $filters['counties'])->pluck('name')->toArray();
            $summary['Counties'] = implode(', ', $counties);
        }

        if (!empty($filters['programs'])) {
            $programs = \App\Models\Program::whereIn('id', $filters['programs'])->pluck('name')->toArray();
            $summary['Programs'] = implode(', ', $programs);
        }

        if (!empty($filters['approaches'])) {
            $summary['Approaches'] = implode(', ', array_map('ucfirst', $filters['approaches']));
        }

        return $summary;
    }

    // Data refresh method for real-time updates
    public function refreshData(): void
    {
        $this->dispatch('filtersUpdated', filters: $this->getFiltersArray());
    }
}
