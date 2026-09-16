<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\IndicatorReportPeriod;
use App\Services\IndicatorReportingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReviewReport extends Page {

    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.indicators.review-report';

    public static function getRouteKeyName(): string {
        return 'period';
    }

    public static function getSlug(): string {
        return 'indicators/review-report';
    }

    public static function getRouteName(?string $panel = null): string {
        return 'indicators.review-report';
    }

    public static function getRoute(): string {
        return static::getSlug() . '/{period}';
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string {
        return route('filament.admin.pages.indicators.review-report', $parameters, $isAbsolute);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    public IndicatorReportPeriod $period;
    public string $notes = '';
    public array $groupStats = [];
    public array $overallCompletion = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    public function mount(): void {
        $periodId = request()->query('period');

        if (!$periodId) {
            abort(404, 'No period specified.');
        }

        $period = IndicatorReportPeriod::find($periodId);

        if (!$period) {
            abort(404, 'Report period not found.');
        }

        $user = auth()->user();

        if (
                $period->facility_id !== $user->facility_id && !$user->hasRole(['super_admin', 'admin'])
        ) {
            abort(403);
        }

        $this->period = $period->load(['reportType', 'frequency', 'facility', 'submittedByUser']);
        $this->notes = $period->notes ?? '';

        $service = app(IndicatorReportingService::class);

        $stats = $service->getGroupCompletionStats($period);

        $this->groupStats = $stats->map(fn($s) => [
                    'group_id' => $s['group']->id,
                    'group_name' => $s['group']->name,
                    'total' => $s['total'],
                    'filled' => $s['filled'],
                    'percentage' => $s['percentage'],
                    'complete' => $s['complete'],
                        ])->values()->toArray();

        $this->overallCompletion = $service->getOverallCompletion($period);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────────────────

    public function confirmSubmit(): void {
        if (!$this->period->canBeSubmitted()) {
            Notification::make()->title('Report cannot be submitted.')->warning()->send();
            return;
        }

        // Save notes
        $this->period->update(['notes' => $this->notes]);

        try {
            app(IndicatorReportingService::class)->submit($this->period, auth()->id());

            Notification::make()
                    ->title('Report submitted successfully')
                    ->body('Your report has been submitted for validation. You will be notified once it is reviewed.')
                    ->success()
                    ->persistent()
                    ->send();

            $this->redirect(IndicatorReporting::getUrl());
        } catch (\Exception $e) {
            Notification::make()->title('Submission failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array {
        return [
                    Action::make('back_to_form')
                    ->label('Back to Form')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(FillReport::getUrl(['period' => $this->period->id])),
                    Action::make('submit')
                    ->label('Submit for Validation')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Report for Validation')
                    ->modalDescription('Once submitted, this report will be sent for review. You will not be able to edit it unless it is rejected. Proceed?')
                    ->modalSubmitActionLabel('Yes, Submit')
                    ->action('confirmSubmit')
                    ->visible(fn() => isset($this->period) && $this->period->canBeSubmitted()),
        ];
    }

    public function getTitle(): string {
        if (!isset($this->period)) {
            return 'Review Report';
        }
        return 'Review: ' . $this->period->reportType->name;
    }

    public function getSubheading(): ?string {
        return $this->period->facility->name . ' · ' . $this->period->period_label;
    }

    public function getViewData(): array {
        return [
            'period' => $this->period,
            'groupStats' => $this->groupStats,
            'overallCompletion' => $this->overallCompletion,
            'isSubmittable' => $this->period->canBeSubmitted(),
        ];
    }
}
