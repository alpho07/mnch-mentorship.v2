<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Services\Dhis2SyncService;
use App\Services\IndicatorReportingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ViewSubmission extends Page {

    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.indicators.view-submission';

    public static function getSlug(): string {
        return 'indicators/view-submission';
    }

    public static function getRouteName(?string $panel = null): string {
        return 'indicators.view-submission';
    }

    public static function getRoute(): string {
        return static::getSlug() . '/{period}';
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string {
        return route('filament.admin.pages.indicators.view-submission', $parameters, $isAbsolute);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    public IndicatorReportPeriod $period;
    public array $groups = [];
    public array $existingValues = [];
    public array $groupStats = [];
    public array $overallCompletion = [];
    public bool $isValidator = false;

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
        $this->isValidator = $user->can('page_ValidationQueue');

        if (!$this->isValidator && $period->facility_id !== $user->facility_id) {
            abort(403);
        }

        $this->period = $period->load(['reportType', 'frequency', 'facility', 'submittedByUser', 'validatedByUser']);

        $service = app(IndicatorReportingService::class);

        $groups = IndicatorGroup::where('report_type_id', $period->report_type_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with([
                    'indicators' => fn($q) => $q->active()->topLevel()->orderBy('sort_order'),
                    'indicators.children' => fn($q) => $q->active()->orderBy('sort_order'),
                ])
                ->get();

        $values = $period->values()->with('indicator')->get()->keyBy('indicator_id');

        $this->existingValues = $values->map(fn($v) => [
                    'numerator' => $v->numerator_value,
                    'denominator' => $v->denominator_value,
                    'count' => $v->count_value,
                    'yes_no' => $v->yes_no_value,
                    'computed_percentage' => $v->computed_percentage,
                    'comment' => $v->comment,
                        ])->toArray();

        $this->groups = $groups->map(fn($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'indicators' => $g->indicators->map(fn($ind) => [
                        'id' => $ind->id,
                        'code' => $ind->code,
                        'name' => $ind->name,
                        'indicator_type' => $ind->indicator_type,
                        'category' => $ind->category,
                        'source_document' => $ind->source_document,
                        'numerator_label' => $ind->numerator_label,
                        'denominator_label' => $ind->denominator_label,
                        'children' => $ind->children->map(fn($c) => [
                            'id' => $c->id,
                            'code' => $c->code,
                            'name' => $c->name,
                            'short_name' => $c->short_name,
                                ])->toArray(),
                            ])->toArray(),
                        ])->toArray();

        $this->groupStats = $service->getGroupCompletionStats($period)
                        ->map(fn($s) => [
                            'group_name' => $s['group']->name,
                            'total' => $s['total'],
                            'filled' => $s['filled'],
                            'percentage' => $s['percentage'],
                            'complete' => $s['complete'],
                                ])->values()->toArray();

        $this->overallCompletion = $service->getOverallCompletion($period);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions (validator only)
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        $actions = [
                    Action::make('back')
                    ->label('Back to Queue')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(ValidationQueue::getUrl()),
        ];

        if ($this->isValidator && $this->period->canBeValidated()) {
            $actions[] = Action::make('validate')
                    ->label('Validate Report')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Validate This Report')
                    ->modalDescription('Mark this report as validated. It will become eligible for DHIS2 sync.')
                    ->action(function () {
                        app(IndicatorReportingService::class)->validate($this->period, auth()->id());
                        $this->period->refresh();
                        Notification::make()->title('Report validated')->success()->send();
                    });

            $actions[] = Action::make('reject')
                    ->label('Reject Report')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('rejection_reason')
                        ->label('Reason for rejection')
                        ->required()
                        ->rows(3),
                    ])
                    ->action(function (array $data) {
                        app(IndicatorReportingService::class)->reject($this->period, auth()->id(), $data['rejection_reason']);
                        $this->period->refresh();
                        Notification::make()->title('Report rejected')->warning()->send();
                    });
        }

        // DHIS2 push (super_admin on validated reports)
        if (auth()->user()->hasRole('super_admin') && $this->period->isValidated()) {
            $dhis2Service = app(Dhis2SyncService::class);
            $blockers = $dhis2Service->getBlockers($this->period);

            $actions[] = Action::make('push_dhis2')
                    ->label('Push to DHIS2')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('info')
                    ->disabled(!empty($blockers))
                    ->tooltip(!empty($blockers) ? implode(' | ', $blockers) : 'Push validated data to DHIS2')
                    ->requiresConfirmation()
                    ->modalHeading('Push to DHIS2')
                    ->modalDescription('This will send all indicator values to DHIS2. Proceed?')
                    ->action(function () use ($dhis2Service) {
                        try {
                            $result = $dhis2Service->push($this->period);
                            $this->period->refresh();
                            if ($result['success']) {
                                Notification::make()->title('Pushed to DHIS2 successfully')->success()->send();
                            } else {
                                Notification::make()->title('DHIS2 push failed')->body('Check import summary for details.')->danger()->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    });
        }

        return $actions;
    }

    public function getTitle(): string {
        if (!isset($this->period)) {
            return 'View Submission';
        }
        return $this->period->reportType->name . ' — ' . $this->period->period_label;
    }

    public function getSubheading(): ?string {
        return $this->period->facility->name;
    }

    public function getViewData(): array {
        return [
            'period' => $this->period,
            'groups' => $this->groups,
            'existingValues' => $this->existingValues,
            'groupStats' => $this->groupStats,
            'overallCompletion' => $this->overallCompletion,
            'isValidator' => $this->isValidator,
        ];
    }
}
