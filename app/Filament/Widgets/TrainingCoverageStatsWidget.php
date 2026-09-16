<?php

namespace App\Filament\Widgets;

use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Subcounty;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrainingCoverageStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.training-coverage-stats';

    protected int | string | array $columnSpan = 'full';

    // Filter properties that will be updated by events
    public ?array $filterData = [];

    // Listen to filter changes from the page
    protected $listeners = [
        'filtersUpdated' => 'updateFilters',
    ];

    public function mount()
    {
        // Initialize with empty filters
        $this->filterData = [
            'program_id' => [],
            'period' => [],
            'county_id' => [],
            'subcounty_id' => [],
            'facility_id' => [],
            'department_id' => null,
            'cadre_id' => null,
        ];
    }

    public function updateFilters($filterData)
    {
        // Update widget filter data
        $this->filterData = array_merge($this->filterData, $filterData);
    }

    // Get filtered training query
    private function getFilteredTrainings(): Builder
    {
        $query = Training::query();

        // Apply filters from filterData
        if (!empty($this->filterData['program_id'])) {
            $query->whereIn('program_id', $this->filterData['program_id']);
        }

        if (!empty($this->filterData['period'])) {
            $query->where(function ($q) {
                foreach ($this->filterData['period'] as $period) {
                    try {
                        $date = Carbon::createFromFormat('Y-m', $period);
                        $q->orWhereBetween('start_date', [
                            $date->startOfMonth(),
                            $date->endOfMonth()
                        ]);
                    } catch (\Exception $e) {
                        // Skip invalid period format
                        continue;
                    }
                }
            });
        }

        if (!empty($this->filterData['facility_id'])) {
            $query->whereIn('facility_id', $this->filterData['facility_id']);
        } elseif (!empty($this->filterData['subcounty_id'])) {
            $query->whereHas('facility', fn(Builder $q) => $q->whereIn('subcounty_id', $this->filterData['subcounty_id']));
        } elseif (!empty($this->filterData['county_id'])) {
            $query->whereHas('facility.subcounty', fn(Builder $q) => $q->whereIn('county_id', $this->filterData['county_id']));
        }

        if (!empty($this->filterData['department_id'])) {
            $query->whereHas('departments', fn(Builder $q) => $q->where('departments.id', $this->filterData['department_id']));
        }

        return $query;
    }

    // Get filtered participants query
    private function getFilteredParticipants(): Builder
    {
        $query = TrainingParticipant::query();

        $query->whereHas('training', function (Builder $trainingQuery) {
            // Apply same training filters
            if (!empty($this->filterData['program_id'])) {
                $trainingQuery->whereIn('program_id', $this->filterData['program_id']);
            }

            if (!empty($this->filterData['period'])) {
                $trainingQuery->where(function ($q) {
                    foreach ($this->filterData['period'] as $period) {
                        try {
                            $date = Carbon::createFromFormat('Y-m', $period);
                            $q->orWhereBetween('start_date', [
                                $date->startOfMonth(),
                                $date->endOfMonth()
                            ]);
                        } catch (\Exception $e) {
                            // Skip invalid period format
                            continue;
                        }
                    }
                });
            }

            if (!empty($this->filterData['facility_id'])) {
                $trainingQuery->whereIn('facility_id', $this->filterData['facility_id']);
            } elseif (!empty($this->filterData['subcounty_id'])) {
                $trainingQuery->whereHas('facility', fn(Builder $q) => $q->whereIn('subcounty_id', $this->filterData['subcounty_id']));
            } elseif (!empty($this->filterData['county_id'])) {
                $trainingQuery->whereHas('facility.subcounty', fn(Builder $q) => $q->whereIn('county_id', $this->filterData['county_id']));
            }

            if (!empty($this->filterData['department_id'])) {
                $trainingQuery->whereHas('departments', fn(Builder $q) => $q->where('departments.id', $this->filterData['department_id']));
            }
        });

        // Additional participant filters
        if (!empty($this->filterData['department_id'])) {
            $query->where('department_id', $this->filterData['department_id']);
        }

        if (!empty($this->filterData['cadre_id'])) {
            $query->where('cadre_id', $this->filterData['cadre_id']);
        }

        return $query;
    }

    // Stats calculation methods
    public function getTotalTrainings(): int
    {
        try {
            return $this->getFilteredTrainings()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getTotalParticipants(): int
    {
        try {
            return $this->getFilteredParticipants()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getFacilitiesTrained(): int
    {
        try {
            return $this->getFilteredTrainings()
                ->distinct('facility_id')
                ->count('facility_id');
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getCoveragePercentage(): float
    {
        try {
            $totalFacilities = Facility::count();
            $facilitiesTrained = $this->getFacilitiesTrained();

            return $totalFacilities > 0 ? ($facilitiesTrained / $totalFacilities) * 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getLastTrainingDate(): ?string
    {
        try {
            $lastTraining = $this->getFilteredTrainings()
                ->orderBy('start_date', 'desc')
                ->first();

            return $lastTraining ? Carbon::parse($lastTraining->start_date)->format('M-Y') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getActiveMonths(): int
    {
        try {
            return $this->getFilteredTrainings()
                ->selectRaw('YEAR(start_date) as year, MONTH(start_date) as month')
                ->groupByRaw('YEAR(start_date), MONTH(start_date)')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
