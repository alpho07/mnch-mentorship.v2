<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TrainingsByMonthChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Trainings by Month';
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
        $data = $this->getTrainingsByMonth();

        return [
            'datasets' => [
                [
                    'label' => 'Trainings',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => '#3B82F6',
                    'borderColor' => '#2563EB',
                    'borderWidth' => 1,
                ]
            ],
            'labels' => $data->pluck('period')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }

    private function getFilteredTrainings(): Builder
    {
        $query = Training::query();

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

    private function getTrainingsByMonth()
    {
        try {
            return $this->getFilteredTrainings()
                ->selectRaw('YEAR(start_date) as year, MONTH(start_date) as month, COUNT(*) as count')
                ->groupByRaw('YEAR(start_date), MONTH(start_date)')
                ->orderByRaw('YEAR(start_date), MONTH(start_date)')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => Carbon::create($item->year, $item->month)->format('M-Y'),
                        'count' => $item->count
                    ];
                });
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
