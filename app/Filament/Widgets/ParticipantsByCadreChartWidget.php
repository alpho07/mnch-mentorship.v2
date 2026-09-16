<?php

namespace App\Filament\Widgets;

use App\Models\Training;
use App\Models\TrainingParticipant;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ParticipantsByCadreChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Participants by Cadre';
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
        $data = $this->getParticipantsByCadre();

        return [
            'datasets' => [
                [
                    'label' => 'Participants',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => '#F59E0B',
                    'borderColor' => '#D97706',
                    'borderWidth' => 1,
                ]
            ],
            'labels' => $data->keys()->toArray(),
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
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 0,
                    ],
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

    private function getParticipantsByCadre()
    {
        try {
            return $this->getFilteredParticipants()
                ->with('cadre')
                ->get()
                ->filter(function ($participant) {
                    return $participant->cadre;
                })
                ->groupBy('cadre.name')
                ->map->count()
                ->sortDesc()
                ->take(10);
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
