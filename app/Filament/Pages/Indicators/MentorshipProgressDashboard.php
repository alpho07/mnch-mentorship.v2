<?php

namespace App\Filament\Pages\Indicators;

use App\Services\MentorshipProgressService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class MentorshipProgressDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Progress Dashboard';

    protected static ?string $navigationGroup = 'Indicator Reporting';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'indicators/progress-dashboard';

    protected static string $view = 'filament.pages.indicators.progress-dashboard';

    // ── Filters ──────────────────────────────────────────────────────────────
    public ?int $filterReportTypeId = null;

    public ?string $filterYear = null;

    public ?int $filterCountyId = null;

    public ?int $filterFacilityId = null;

    public string $activeTab = 'overview';

    // ── Cached data ──────────────────────────────────────────────────────────
    public array $stats = [];

    public array $trendData = [];

    public array $performers = [];

    public array $filterOptions = [];

    public array $facilityTrends = [];

    public array $indicatorBreakdown = [];

    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (! $user->can('page_MentorshipProgressDashboard')) {
            return false;
        }

        // Admin-level bypass facility requirement
        if ($user->can('view_any_indicator')) {
            return true;
        }

        return $user->facility_id !== null;
    }

    public function mount(): void
    {
        // Default year to current
        $this->filterYear = (string) now()->year;
        $this->loadData();
    }

    public function loadData(): void
    {
        $service = app(MentorshipProgressService::class);
        $filters = $this->buildFilters();

        $this->filterOptions = $service->getFilterOptions();
        $this->stats = $service->getHeadlineStats($filters);
        $this->trendData = $service->getAggregateTrend($filters);
        $this->performers = $service->getPerformerRankings($filters, 8);
        $this->facilityTrends = $service->getFacilityTrends($filters)->toArray();
        $this->indicatorBreakdown = $service->getIndicatorBreakdown($filters)->toArray();
    }

    public function updatedFilterReportTypeId(): void
    {
        $this->loadData();
    }

    public function updatedFilterYear(): void
    {
        $this->loadData();
    }

    public function updatedFilterCountyId(): void
    {
        $this->filterFacilityId = null;
        $this->loadData();
    }

    public function updatedFilterFacilityId(): void
    {
        $this->loadData();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    private function buildFilters(): array
    {
        return array_filter([
            'report_type_id' => $this->filterReportTypeId,
            'year' => $this->filterYear,
            'county_id' => $this->filterCountyId,
            'facility_id' => $this->filterFacilityId,
        ]);
    }
}
