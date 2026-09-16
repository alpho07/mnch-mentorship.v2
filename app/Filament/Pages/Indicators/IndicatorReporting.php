<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\IndicatorFrequency;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\Indicators\IndicatorReportType;
use App\Services\IndicatorReportingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class IndicatorReporting extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Submit Report';

    protected static ?string $navigationGroup = 'Indicator Reporting';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'indicators/reporting';

    protected static string $view = 'filament.pages.indicators.reporting';

    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (! $user->can('page_IndicatorReporting')) {
            return false;
        }

        // Admin-level permissions bypass the facility_id requirement
        if ($user->can('view_any_indicator')) {
            return true;
        }

        // Facility-level roles need a facility assignment to report against
        return $user->facility_id !== null;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    public ?array $data = [];

    /** Resolved after period is selected — drives the status card */
    public ?array $periodStatus = null;

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->form->fill([
            'period_year' => (int) now()->format('Y'),
            'period_month' => (int) now()->format('n'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form — period selector
    // ──────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        $user = auth()->user();
        $facilityId = $user->facility_id;

        // Report types enabled for this facility
        $assignment = FacilityIndicatorAssignment::where('facility_id', $facilityId)->first();
        $enabledTypeIds = $assignment ? ($assignment->enabled_report_types ?? []) : [];

        $reportTypes = IndicatorReportType::active()
            ->when(! empty($enabledTypeIds), fn ($q) => $q->whereIn('id', $enabledTypeIds))
            ->pluck('name', 'id');

        $frequencies = IndicatorFrequency::orderBy('sort_order')->pluck('name', 'id');

        $years = collect(range(now()->year - 2, now()->year + 1))
            ->mapWithKeys(fn ($y) => [$y => (string) $y]);

        $months = collect([
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ]);

        $quarters = collect([1 => 'Q1 (Jan–Mar)', 2 => 'Q2 (Apr–Jun)', 3 => 'Q3 (Jul–Sep)', 4 => 'Q4 (Oct–Dec)']);

        return $form
            ->schema([
                Section::make('Select Reporting Period')
                    ->description('Choose the report type, frequency, and period you want to submit data for.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('report_type_id')
                                ->label('Report Type')
                                ->options($reportTypes)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->checkPeriodStatus()),
                            Select::make('frequency_id')
                                ->label('Reporting Frequency')
                                ->options($frequencies)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->checkPeriodStatus()),
                            Select::make('period_year')
                                ->label('Year')
                                ->options($years)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->checkPeriodStatus()),
                            Select::make('period_month')
                                ->label('Month')
                                ->options($months)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->checkPeriodStatus())
                                ->visible(fn (Get $get) => $this->isMonthly($get('frequency_id'))),
                            Select::make('period_quarter')
                                ->label('Quarter')
                                ->options($quarters)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn () => $this->checkPeriodStatus())
                                ->visible(fn (Get $get) => $this->isQuarterly($get('frequency_id'))),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Live period status check
    // ──────────────────────────────────────────────────────────────────────────

    public function checkPeriodStatus(): void
    {
        $d = $this->data;

        if (! ($d['report_type_id'] ?? null) || ! ($d['frequency_id'] ?? null) || ! ($d['period_year'] ?? null)) {
            $this->periodStatus = null;

            return;
        }

        $freq = IndicatorFrequency::find($d['frequency_id']);
        if (! $freq) {
            $this->periodStatus = null;

            return;
        }

        $month = $freq->requiresMonth() ? ($d['period_month'] ?? null) : null;
        $quarter = $freq->requiresQuarter() ? ($d['period_quarter'] ?? null) : null;

        if ($freq->requiresMonth() && ! $month) {
            $this->periodStatus = null;

            return;
        }
        if ($freq->requiresQuarter() && ! $quarter) {
            $this->periodStatus = null;

            return;
        }

        $period = IndicatorReportPeriod::where([
            'facility_id' => auth()->user()->facility_id,
            'report_type_id' => $d['report_type_id'],
            'frequency_id' => $d['frequency_id'],
            'period_year' => $d['period_year'],
            'period_month' => $month,
            'period_quarter' => $quarter,
        ])->first();

        $reportType = IndicatorReportType::find($d['report_type_id']);
        $periodLabel = $freq->formatPeriodLabel($d['period_year'], $month, $quarter);

        $this->periodStatus = [
            'exists' => $period !== null,
            'status' => $period?->status ?? 'not_started',
            'period_label' => $periodLabel,
            'report_type' => $reportType?->name,
            'period_id' => $period?->id,
            'completion' => $period ? app(IndicatorReportingService::class)->getOverallCompletion($period) : null,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Primary CTA action — routes to FillReport
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function proceed(): void
    {
        $user = auth()->user();

        // Resolve facility — check direct assignment first, then pivot
        $facilityId = $user->facility_id;

        if (! $facilityId) {
            Notification::make()
                ->title('No facility assigned')
                ->body('Your account does not have a facility assigned.')
                ->danger()
                ->send();

            return;
        }

        $d = $this->form->getState();

        $freq = IndicatorFrequency::findOrFail($d['frequency_id']);
        $month = $freq->requiresMonth() ? ($d['period_month'] ?? null) : null;
        $quarter = $freq->requiresQuarter() ? ($d['period_quarter'] ?? null) : null;

        $period = app(IndicatorReportingService::class)->findOrCreatePeriod(
            facilityId: $facilityId,
            reportTypeId: (int) $d['report_type_id'],
            frequencyId: (int) $d['frequency_id'],
            year: (int) $d['period_year'],
            month: $month ? (int) $month : null,
            quarter: $quarter ? (int) $quarter : null,
        );

        $this->redirect(FillReport::getUrl(['period' => $period->id]));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Recent submissions list (for the view)
    // ──────────────────────────────────────────────────────────────────────────

    public function getRecentPeriods(): \Illuminate\Support\Collection
    {
        $facilityId = auth()->user()->facility_id;
        if (! $facilityId) {
            return collect();
        }

        return IndicatorReportPeriod::where('facility_id', $facilityId)
            ->with(['reportType', 'frequency'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'report_type' => $p->reportType->name,
                'period_label' => $p->period_label,
                'status' => $p->status,
                'status_color' => $p->getStatusColor(),
                'status_icon' => $p->getStatusIcon(),
                'updated_at' => $p->updated_at->diffForHumans(),
                'url' => FillReport::getUrl(['period' => $p->id]),
            ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function isMonthly(?string $frequencyId): bool
    {
        if (! $frequencyId) {
            return true;
        } // default show month
        $freq = IndicatorFrequency::find($frequencyId);

        return $freq ? $freq->requiresMonth() : true;
    }

    private function isQuarterly(?string $frequencyId): bool
    {
        if (! $frequencyId) {
            return false;
        }
        $freq = IndicatorFrequency::find($frequencyId);

        return $freq ? $freq->requiresQuarter() : false;
    }

    public function getViewData(): array
    {
        return [
            'periodStatus' => $this->periodStatus,
            'recentPeriods' => $this->getRecentPeriods(),
            'facilityName' => auth()->user()->facility?->name ?? 'Your Facility',
        ];
    }
}
