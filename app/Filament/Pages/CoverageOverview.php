<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\KenyaTrainingHeatmapWidget;
use App\Filament\Widgets\ParticipantsByCadreChartWidget;
use App\Filament\Widgets\ParticipantsByDepartmentChartWidget;
use App\Filament\Widgets\TrainingsByCountyChartWidget;
use App\Filament\Widgets\TrainingsByMonthChartWidget;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CoverageOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.coverage-overview';
    protected static ?string $navigationGroup = 'Dashboards';
    protected static ?string $title = 'Training Overview';
    protected static ?string $navigationLabel = 'Training Coverage';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'coverage-overview';
      public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    

    // Filter Properties
    public ?array $program_id = [];
    public ?array $period = [];
    public ?array $county_id = [];
    public ?array $subcounty_id = [];
    public ?array $facility_id = [];
    public ?string $department_id = null;
    public ?string $cadre_id = null;

    public function mount(): void
    {
        // Initialize with default values if needed
    }

    // Use Filament's built-in widget system
    protected function getHeaderWidgets(): array
    {
        return [
            //  \App\Filament\Widgets\TrainingCoverageStatsWidget::class, 
        ];
    }


    protected function test(){

    }

    protected function getFooterWidgets(): array
    {
        return [
            //KenyaTrainingHeatmapWidget::class,
            //TrainingsByMonthChartWidget::class,
            //TrainingsByCountyChartWidget::class,
            //ParticipantsByDepartmentChartWidget::class,
            //ParticipantsByCadreChartWidget::class,
        ];
    }

    // Form for Filters
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('program_id')
                    ->label('Program')
                    ->multiple()
                    ->options(fn() => $this->getProgramOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->emitFiltersUpdated())
                    ->columnSpan(2),

                Select::make('period')
                    ->label('Period (Month-Year)')
                    ->multiple()
                    ->options(fn() => $this->getAvailableMonths())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->emitFiltersUpdated())
                    ->columnSpan(2),

                Select::make('county_id')
                    ->label('County')
                    ->multiple()
                    ->options(fn() => $this->getCountyOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->resetCascadingFilters(['subcounty_id', 'facility_id']);
                        $this->emitFiltersUpdated();
                    })
                    ->columnSpan(2),

                Select::make('subcounty_id')
                    ->label('Subcounty')
                    ->multiple()
                    ->options(fn() => $this->getSubcountyOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->resetCascadingFilters(['facility_id']);
                        $this->emitFiltersUpdated();
                    })
                    ->columnSpan(2),

                Select::make('facility_id')
                    ->label('Facility')
                    ->multiple()
                    ->options(fn() => $this->getFacilityOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->emitFiltersUpdated())
                    ->columnSpan(2),

                Select::make('department_id')
                    ->label('Department')
                    ->options(fn() => $this->getDepartmentOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->emitFiltersUpdated())
                    ->placeholder('All Departments'),

                Select::make('cadre_id')
                    ->label('Cadre')
                    ->options(fn() => $this->getCadreOptions())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->emitFiltersUpdated())
                    ->placeholder('All Cadres'),
            ])
            ->columns(6);
    }

    public function getFilterData(): array
    {
        return [
            'program_id' => $this->program_id ?? [],
            'period' => $this->period ?? [],
            'county_id' => $this->county_id ?? [],
            'subcounty_id' => $this->subcounty_id ?? [],
            'facility_id' => $this->facility_id ?? [],
            'department_id' => $this->department_id,
            'cadre_id' => $this->cadre_id,
        ];
    }

    // Helper Methods for Cascading Filters based on actual training data
    private function getCountyOptions(): array
    {
        return County::whereHas('subcounties.facilities.trainings')
            ->pluck('name', 'id')
            ->toArray();
    }

    private function getSubcountyOptions(): array
    {
        $query = Subcounty::whereHas('facilities.trainings');

        if ($this->county_id && count($this->county_id) > 0) {
            $query->whereIn('county_id', $this->county_id);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    private function getFacilityOptions(): array
    {
        $query = Facility::whereHas('trainings');

        if ($this->subcounty_id && count($this->subcounty_id) > 0) {
            $query->whereIn('subcounty_id', $this->subcounty_id);
        } elseif ($this->county_id && count($this->county_id) > 0) {
            $query->whereHas('subcounty', fn(Builder $q) => $q->whereIn('county_id', $this->county_id));
        }

        return $query->pluck('name', 'id')->toArray();
    }

    private function getProgramOptions(): array
    {
        return Program::whereHas('trainings')->pluck('name', 'id')->toArray();
    }

    private function getDepartmentOptions(): array
    {
        return Department::whereHas('trainingParticipants')->pluck('name', 'id')->toArray();
    }

    private function getCadreOptions(): array
    {
        return \App\Models\Cadre::whereHas('trainingParticipants')->pluck('name', 'id')->toArray();
    }

    private function getAvailableMonths(): array
    {
        $months = Training::selectRaw('DISTINCT YEAR(start_date) as year, MONTH(start_date) as month, start_date')
            ->orderBy('start_date')
            ->get()
            ->map(function ($training) {
                $date = Carbon::parse($training->start_date);
                return [
                    'value' => $date->format('Y-m'),
                    'label' => $date->format('M-Y')
                ];
            })
            ->unique('value')
            ->pluck('label', 'value')
            ->toArray();

        return $months;
    }

    private function resetCascadingFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->$filter = [];
        }
    }

    // Emit event to all widgets when filters change
    private function emitFiltersUpdated(): void
    {
        $this->dispatch('filtersUpdated', $this->getFilterData());
    }

    // Clear all filters
    public function clearFilters(): void
    {
        $this->reset([
            'program_id',
            'period',
            'county_id',
            'subcounty_id',
            'facility_id',
            'department_id',
            'cadre_id'
        ]);

        $this->emitFiltersUpdated();
    }
}
