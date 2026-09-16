<?php

namespace App\Services;

use App\Models\Indicators\IndicatorFrequency;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\Indicators\IndicatorValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndicatorReportingService {
    // ──────────────────────────────────────────────────────────────────────────
    // Period management
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Find existing period or create a new draft for the given params.
     */
    public function findOrCreatePeriod(
            int $facilityId,
            int $reportTypeId,
            int $frequencyId,
            int $year,
            ?int $month = null,
            ?int $quarter = null
    ): IndicatorReportPeriod {
        $frequency = IndicatorFrequency::findOrFail($frequencyId);

        return IndicatorReportPeriod::firstOrCreate(
                        [
                            'facility_id' => $facilityId,
                            'report_type_id' => $reportTypeId,
                            'frequency_id' => $frequencyId,
                            'period_year' => $year,
                            'period_month' => $month,
                            'period_quarter' => $quarter,
                        ],
                        [
                            'status' => IndicatorReportPeriod::STATUS_DRAFT,
                            'dhis2_period' => $frequency->buildDhis2Period($year, $month, $quarter),
                        ]
                );
    }

    /**
     * Check existing status for a facility/type/frequency/period combination.
     * Returns null if no report exists yet.
     */
    public function getPeriodStatus(
            int $facilityId,
            int $reportTypeId,
            int $frequencyId,
            int $year,
            ?int $month,
            ?int $quarter
    ): ?string {
        return IndicatorReportPeriod::where([
                    'facility_id' => $facilityId,
                    'report_type_id' => $reportTypeId,
                    'frequency_id' => $frequencyId,
                    'period_year' => $year,
                    'period_month' => $month,
                    'period_quarter' => $quarter,
                ])->value('status');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Data saving
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Save/upsert indicator values for a period.
     *
     * $values format:
     * [
     *   indicator_id => [
     *     'numerator_value'   => int|null,
     *     'denominator_value' => int|null,
     *     'count_value'       => int|null,
     *     'yes_no_value'      => bool|null,
     *     'comment'           => string|null,
     *   ],
     *   ...
     * ]
     */
    public function saveValues(IndicatorReportPeriod $period, array $values): void {
        if (!$period->isEditable()) {
            throw new \RuntimeException("Period {$period->id} is not editable (status: {$period->status}).");
        }

        DB::transaction(function () use ($period, $values) {
            foreach ($values as $indicatorId => $data) {
                $existing = IndicatorValue::where([
                            'period_id' => $period->id,
                            'indicator_id' => $indicatorId,
                        ])->first();

                $payload = array_filter([
                    'numerator_value' => $data['numerator_value'] ?? null,
                    'denominator_value' => $data['denominator_value'] ?? null,
                    'count_value' => $data['count_value'] ?? null,
                    'yes_no_value' => $data['yes_no_value'] ?? null,
                    'comment' => $data['comment'] ?? null,
                        ], fn($v) => $v !== null);

                if ($existing) {
                    $existing->fill($payload)->save(); // boot() auto-recomputes percentage
                } else {
                    IndicatorValue::create(array_merge($payload, [
                        'period_id' => $period->id,
                        'indicator_id' => $indicatorId,
                        'created_by' => auth()->id(),
                    ]));
                }
            }
        });
    }


 

  
    // ──────────────────────────────────────────────────────────────────────────
    // Completion stats
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return per-group completion stats for a period.
     * Used on the review/summary page.
     *
     * Returns:
     * [
     *   group_id => [
     *     'group'       => IndicatorGroup,
     *     'total'       => int,
     *     'filled'      => int,
     *     'percentage'  => float,
     *     'complete'    => bool,
     *   ]
     * ]
     */
    public function getGroupCompletionStats(IndicatorReportPeriod $period): Collection {
        $reportType = $period->reportType()->with(['groups.allIndicators'])->first();

        $filledIndicatorIds = $period->values()->pluck('indicator_id')->toArray();

        return $reportType->groups->map(function ($group) use ($filledIndicatorIds) {
                    $activeIndicators = $group->allIndicators->where('is_active', true);
                    $total = $activeIndicators->count();
                    $filled = $activeIndicators->filter(fn($i) => in_array($i->id, $filledIndicatorIds))->count();

                    return [
                        'group' => $group,
                        'total' => $total,
                        'filled' => $filled,
                        'percentage' => $total > 0 ? round(($filled / $total) * 100, 1) : 100.0,
                        'complete' => $total === 0 || $total === $filled,
                    ];
                })->keyBy(fn($item) => $item['group']->id);
    }

    /**
     * Overall completion percentage for a period.
     */
    public function getOverallCompletion(IndicatorReportPeriod $period): array {
        $stats = $this->getGroupCompletionStats($period);
        $total = $stats->sum('total');
        $filled = $stats->sum('filled');

        return [
            'total' => $total,
            'filled' => $filled,
            'percentage' => $total > 0 ? round(($filled / $total) * 100, 1) : 100.0,
            'complete' => $total === $filled,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Data loading for form pre-fill
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Load existing values for a period keyed by indicator_id.
     * Used to pre-populate the data entry form.
     */
    public function getValuesForPeriod(IndicatorReportPeriod $period): Collection {
        return $period->values()
                        ->with('indicator')
                        ->get()
                        ->keyBy('indicator_id');
    }

    /**
     * Get all indicators for a report type, grouped by group, with existing values
     * for the given period merged in. Ready for the form to consume.
     */
    public function getFormData(IndicatorReportPeriod $period): Collection {
        $existingValues = $this->getValuesForPeriod($period);

        $reportType = $period->reportType()->with([
                    'groups' => fn($q) => $q->active(),
                    'groups.indicators' => fn($q) => $q->active()->topLevel()->with('children'),
                ])->first();

        return $reportType->groups->map(function ($group) use ($existingValues) {
                    $group->indicators->each(function ($indicator) use ($existingValues) {
                        $indicator->existing_value = $existingValues->get($indicator->id);
                        $indicator->children->each(function ($child) use ($existingValues) {
                            $child->existing_value = $existingValues->get($child->id);
                        });
                    });
                    return $group;
                });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Validation helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Validate that no numerator exceeds its denominator.
     * Returns array of violation messages.
     */
    public function validateValues(IndicatorReportPeriod $period): array {
        $errors = [];

        $period->values()->with('indicator')->get()->each(function (IndicatorValue $value) use (&$errors) {
            if ($value->isNumeratorExceedsDenominator()) {
                $errors[] = "Indicator [{$value->indicator->code}]: Numerator ({$value->numerator_value}) exceeds denominator ({$value->denominator_value}).";
            }
        });

        return $errors;
    }

// ── REPLACE submit() ──────────────────────────────────────────────────────────

    public function submit(IndicatorReportPeriod $period, int $userId): void {
        if (!$period->canBeSubmitted()) {
            throw new \RuntimeException("Period cannot be submitted from status: {$period->status}");
        }

        $period->update([
            'status' => IndicatorReportPeriod::STATUS_SUBMITTED,
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        // Notify validators
        try {
            $submittedBy = \App\Models\User::find($userId);
            app(IndicatorNotificationService::class)->notifySubmitted(
                    $period->fresh(['reportType', 'facility', 'frequency']),
                    $submittedBy
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send submission notifications', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
            // Never fail the submission itself due to email errors
        }
    }

// ── REPLACE validate() ────────────────────────────────────────────────────────

    public function validate(IndicatorReportPeriod $period, int $userId): void {
        if (!$period->canBeValidated()) {
            throw new \RuntimeException("Period cannot be validated from status: {$period->status}");
        }

        $period->update([
            'status' => IndicatorReportPeriod::STATUS_VALIDATED,
            'validated_by' => $userId,
            'validated_at' => now(),
            'rejection_reason' => null,
        ]);

        // Notify facility users
        try {
            app(IndicatorNotificationService::class)->notifyValidated(
                    $period->fresh(['reportType', 'facility', 'frequency', 'validatedByUser'])
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send validation notifications', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

// ── REPLACE reject() ─────────────────────────────────────────────────────────

    public function reject(IndicatorReportPeriod $period, int $userId, string $reason): void {
        if (!$period->canBeValidated()) {
            throw new \RuntimeException("Period cannot be rejected from status: {$period->status}");
        }

        $period->update([
            'status' => IndicatorReportPeriod::STATUS_REJECTED,
            'validated_by' => $userId,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Notify facility users
        try {
            app(IndicatorNotificationService::class)->notifyRejected(
                    $period->fresh(['reportType', 'facility', 'frequency', 'validatedByUser'])
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send rejection notifications', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
