<?php

namespace App\Livewire\Charts;

use App\Services\TrainingAnalyticsService;
use App\Traits\HasTrainingFilters;
use Livewire\Component;
use Livewire\Attributes\On;

class CadreDistributionChart extends Component
{
    use HasTrainingFilters;

    public array $filters = [];
    public array $chartData = [];

    protected TrainingAnalyticsService $analyticsService;

    public function boot(): void
    {
        $this->analyticsService = app(TrainingAnalyticsService::class);
    }

    public function mount(array $filters = []): void
    {
        $this->filters = $filters;
        $this->loadChartData();
    }

    #[On('filtersUpdated')]
    public function updateFilters(array $filters): void
    {
        $this->filters = $filters;
        $this->loadChartData();
    }

    protected function loadChartData(): void
    {
        $this->chartData = $this->analyticsService->getCadreDistribution($this->filters);
    }

    public function render()
    {
        return view('filament.livewire.charts.cadre-distribution-chart');
    }
}