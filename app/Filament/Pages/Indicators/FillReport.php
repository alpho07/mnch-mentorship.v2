<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\Indicator;
use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Services\IndicatorReportingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FillReport extends Page {

    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.indicators.fill-report';

    // ──────────────────────────────────────────────────────────────────────────
    // Route
    // ──────────────────────────────────────────────────────────────────────────

    public static function getRouteKeyName(): string {
        return 'period';
    }

    public static function getRouteName(?string $panel = null): string {
        return 'indicators.fill-report';
    }

    public static function getRoute(): string {
        return static::getSlug() . '/{period}';
    }

    public static function getSlug(): string {
        return 'indicators/fill-report';
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string {
        return route('filament.admin.pages.indicators.fill-report', $parameters, $isAbsolute);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    public IndicatorReportPeriod $period;

    /** Active module tab index */
    public int $activeGroupIndex = 0;

    /**
     * Flat value store keyed by indicator_id.
     * Structure per key: ['numerator' => int|null, 'denominator' => int|null, 'count' => int|null, 'yes_no' => bool|null, 'comment' => '']
     */
    public array $values = [];

    /** Groups with indicators, loaded once in mount */
    public array $groups = [];

    /** Completion stats per group_id */
    public array $groupStats = [];

    /** Validation errors keyed by indicator_id */
    public array $valueErrors = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────────────────────────────────

    public function mount(): void {
        $periodId = request()->query('period');

        if (!$periodId) {
            abort(404, 'No period specified.');
        }

        $periodModel = IndicatorReportPeriod::find($periodId);

        if (!$periodModel) {
            abort(404, 'Report period not found.');
        }

        $user = auth()->user();

        if (
                $periodModel->facility_id !== $user->facility_id && !$user->hasRole(['super_admin', 'admin'])
        ) {
            abort(403);
        }

        $this->period = $periodModel->load(['reportType', 'frequency', 'facility']);

        $this->loadGroupStructure();
        $this->loadExistingValues();
        $this->refreshGroupStats();
    }

    private function loadGroupStructure(): void {
        $groups = IndicatorGroup::where('report_type_id', $this->period->report_type_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with([
                    'indicators' => fn($q) => $q->active()->topLevel()->orderBy('sort_order'),
                    'indicators.children' => fn($q) => $q->active()->orderBy('sort_order'),
                ])
                ->get();

        $this->groups = $groups->map(fn($g) => [
                    'id' => $g->id,
                    'code' => $g->code,
                    'name' => $g->name,
                    'description' => $g->description,
                    'indicators' => $g->indicators->map(fn($ind) => $this->serializeIndicator($ind))->toArray(),
                        ])->toArray();
    }

    private function serializeIndicator(Indicator $ind): array {
        return [
            'id' => $ind->id,
            'code' => $ind->code,
            'name' => $ind->name,
            'short_name' => $ind->short_name,
            'indicator_type' => $ind->indicator_type,
            'category' => $ind->category,
            'has_numerator' => $ind->has_numerator,
            'has_denominator' => $ind->has_denominator,
            'numerator_label' => $ind->numerator_label,
            'denominator_label' => $ind->denominator_label,
            'source_document' => $ind->source_document,
            'display_hint' => $ind->display_hint,
            'children' => $ind->children->map(fn($c) => $this->serializeIndicator($c))->toArray(),
        ];
    }

    private function loadExistingValues(): void {
        $existingValues = $this->period->values()->get();

        foreach ($existingValues as $value) {
            $this->values[$value->indicator_id] = [
                'numerator' => $value->numerator_value,
                'denominator' => $value->denominator_value,
                'count' => $value->count_value,
                'yes_no' => $value->yes_no_value,
                'comment' => $value->comment ?? '',
            ];
        }
    }

    private function refreshGroupStats(): void {
        foreach ($this->groups as $group) {
            $allIndicatorIds = $this->getAllIndicatorIdsInGroup($group);
            $total = count($allIndicatorIds);
            $filled = count(array_filter($allIndicatorIds, fn($id) => $this->isIndicatorFilled($id)));

            $this->groupStats[$group['id']] = [
                'total' => $total,
                'filled' => $filled,
                'percentage' => $total > 0 ? round(($filled / $total) * 100) : 100,
                'complete' => $total === 0 || $filled === $total,
            ];
        }
    }

    private function getAllIndicatorIdsInGroup(array $group): array {
        $ids = [];
        foreach ($group['indicators'] as $ind) {
            if (empty($ind['children'])) {
                $ids[] = $ind['id'];
            } else {
                // For banded indicators, collect children only
                foreach ($ind['children'] as $child) {
                    $ids[] = $child['id'];
                }
            }
        }
        return $ids;
    }

    private function isIndicatorFilled(int $indicatorId): bool {
        $v = $this->values[$indicatorId] ?? null;
        if (!$v || !is_array($v))
            return false;

        return !is_null($v['numerator'] ?? null) || !is_null($v['denominator'] ?? null) || !is_null($v['count'] ?? null) || !is_null($v['yes_no'] ?? null);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Livewire hooks — called when inputs change in the view
    // ──────────────────────────────────────────────────────────────────────────

    public function updatedValues($value, $key): void {
        $parts = explode('.', $key, 2);

        if (count($parts) < 2) {
            $this->refreshGroupStats();
            return;
        }

        [$indicatorId, $field] = $parts;
        $indicatorId = (int) $indicatorId;

        unset($this->valueErrors[$indicatorId]);

        if (in_array($field, ['numerator', 'denominator'])) {
            $v = $this->values[$indicatorId] ?? [];
            $n = (int) ($v['numerator'] ?? 0);
            $d = (int) ($v['denominator'] ?? 0);

            if ($d > 0 && $n > $d) {
                $this->valueErrors[$indicatorId] = 'Numerator cannot exceed denominator.';
            }
        }

        $this->refreshGroupStats();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tab navigation
    // ──────────────────────────────────────────────────────────────────────────

    public function setActiveGroup(int $index): void {
        $this->activeGroupIndex = $index;
    }

    public function goToNextGroup(): void {
        $this->saveDraft(notify: false);
        $max = count($this->groups) - 1;
        $this->activeGroupIndex = min($this->activeGroupIndex + 1, $max);
    }

    public function goToPreviousGroup(): void {
        $this->activeGroupIndex = max($this->activeGroupIndex - 1, 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Save
    // ──────────────────────────────────────────────────────────────────────────

    public function saveDraft(bool $notify = true): void {
        if (!$this->period->isEditable()) {
            Notification::make()->title('Report is not editable.')->warning()->send();
            return;
        }

        $payload = $this->buildSavePayload();

        try {
            app(IndicatorReportingService::class)->saveValues($this->period, $payload);
            $this->refreshGroupStats();

            if ($notify) {
                Notification::make()
                        ->title('Draft saved')
                        ->body('Your progress has been saved. You can continue later.')
                        ->success()
                        ->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Save failed')->body($e->getMessage())->danger()->send();
        }
    }

    private function buildSavePayload(): array {
        $payload = [];

        foreach ($this->values as $indicatorId => $v) {
            $payload[(int) $indicatorId] = [
                'numerator_value' => isset($v['numerator']) && $v['numerator'] !== '' ? (int) $v['numerator'] : null,
                'denominator_value' => isset($v['denominator']) && $v['denominator'] !== '' ? (int) $v['denominator'] : null,
                'count_value' => isset($v['count']) && $v['count'] !== '' ? (int) $v['count'] : null,
                'yes_no_value' => isset($v['yes_no']) && $v['yes_no'] !== '' ? (bool) $v['yes_no'] : null,
                'comment' => $v['comment'] ?? null,
            ];
        }

        return $payload;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Submit
    // ──────────────────────────────────────────────────────────────────────────

    public function submitForValidation(): void {
        if (!$this->period->canBeSubmitted()) {
            Notification::make()->title('Cannot submit this report.')->warning()->send();
            return;
        }

        // Run N > D validation across all values
        $errors = [];
        foreach ($this->values as $indicatorId => $v) {
            $n = $v['numerator'] ?? null;
            $d = $v['denominator'] ?? null;
            if (is_numeric($n) && is_numeric($d) && $d > 0 && (int) $n > (int) $d) {
                $errors[$indicatorId] = 'Numerator cannot exceed denominator.';
            }
        }

        if (!empty($errors)) {
            $this->valueErrors = $errors;
            Notification::make()
                    ->title('Validation errors')
                    ->body(count($errors) . ' indicator(s) have errors. Please correct them before submitting.')
                    ->danger()
                    ->send();
            return;
        }

        // Save current values first
        $payload = $this->buildSavePayload();
        app(IndicatorReportingService::class)->saveValues($this->period, $payload);

        // Redirect to review page
        $this->redirect(ReviewReport::getUrl(['period' => $this->period->id]));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array {
        $actions = [];

        if ($this->period->isEditable()) {
            $actions[] = Action::make('save_draft')
                    ->label('Save Draft')
                    ->icon('heroicon-o-document-check')
                    ->color('gray')
                    ->action('saveDraft');

            $actions[] = Action::make('submit')
                    ->label('Review & Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->action('submitForValidation');
        }

        $actions[] = Action::make('back')
                ->label('Back to Period Selection')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(IndicatorReporting::getUrl());

        return $actions;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Page title / subheading
    // ──────────────────────────────────────────────────────────────────────────

    public function getTitle(): string {
        return $this->period->reportType?->name ?? 'Fill Report';
    }

    public function getSubheading(): ?string {
        return ($this->period->facility?->name ?? 'Unknown Facility')
                . ' · ' . ($this->period->period_label ?? '')
                . ' · ' . ucfirst($this->period->status ?? '');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // View data
    // ──────────────────────────────────────────────────────────────────────────

    public function getViewData(): array {
        $overallCompletion = [
            'total' => array_sum(array_column($this->groupStats, 'total')),
            'filled' => array_sum(array_column($this->groupStats, 'filled')),
        ];
        $overallCompletion['percentage'] = $overallCompletion['total'] > 0 ? round(($overallCompletion['filled'] / $overallCompletion['total']) * 100) : 100;

        return [
            'period' => $this->period,
            'groups' => $this->groups,
            'activeGroupIndex' => $this->activeGroupIndex,
            'activeGroup' => $this->groups[$this->activeGroupIndex] ?? null,
            'groupStats' => $this->groupStats,
            'valueErrors' => $this->valueErrors,
            'overallCompletion' => $overallCompletion,
            'isEditable' => $this->period->isEditable(),
            'isLastGroup' => $this->activeGroupIndex >= count($this->groups) - 1,
            'isFirstGroup' => $this->activeGroupIndex === 0,
        ];
    }
}
