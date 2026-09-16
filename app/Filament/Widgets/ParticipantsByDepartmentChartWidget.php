<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ParticipantsByDepartmentChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Participants by Department';
    protected static ?string $maxHeight = '300px';

    // Filter properties
    public ?array $filterData = [];

    // Listen to filter changes
    protected $listeners = [
        'filtersUpdated' => 'updateFilters',
    ];

    public function updateFilters($filterData)
    {
        $this->filterData = array_merge($this->filterData ?: [], $filterData);
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $data = $this->getParticipantsByDepartment();

        return [
            'datasets' => [
                [
                    'label' => 'Participants',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => [
                        '#8B5CF6', '#A855F7', '#C084FC', '#DDD6FE',
                        '#EDE9FE', '#F3E8FF', '#FAF5FF', '#6366F1',
                        '#8B5A2B', '#D97706'
                    ],
                    'borderColor' => '#7C3AED',
                    'borderWidth' => 1,
                ]
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
        ];
    }

    private function getFilteredParticipants(): Builder
    {
        $query = TrainingParticipant::query();

        $query->whereHas('training', function (Builder $trainingQuery) {
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

    private function getParticipantsByDepartment()
    {
        try {
            return $this->getFilteredParticipants()
                ->with('department')
                ->get()
                ->filter(function ($participant) {
                    return $participant->department;
                })
                ->groupBy('department.name')
                ->map->count()
                ->sortDesc()
                ->take(8); // Limit for better visualization
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
