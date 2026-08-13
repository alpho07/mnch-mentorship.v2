<?php

namespace App\Services;

/**
 * Pure evaluation of an AssessmentQuestion's display_conditions tree —
 * shared between DynamicFormBuilder (deciding whether to show a field while
 * a form is being filled in, resolving values from Filament's live Get
 * closure) and DynamicScoringService (deciding whether a scored question
 * should count toward a section's score, resolving values from the
 * assessment's already-persisted responses). The only thing that differs
 * between those two call sites is how a parent question's current value is
 * looked up — that's the $valueResolver.
 *
 * Supports the same shapes DynamicFormBuilder has always supported:
 *  - {operator: 'or'|'and', conditions: [{question_code, operator, value}, ...]}
 *  - {question_code, operator, value} (single condition at the root)
 *  - {show_if: {question_code, value}} (legacy)
 */
class ConditionalLogicEvaluator
{
    /**
     * @param  callable(string $questionCode): mixed  $valueResolver
     */
    public static function isVisible(array $conditions, callable $valueResolver): bool
    {
        // OR: show if ANY condition is true. Matches DynamicFormBuilder's
        // original behavior exactly — no explicit "unanswered parent" guard
        // here, evaluateCondition's own semantics already handle it
        // correctly for 'equals' etc, and changing that for 'not_equals'
        // would be a real (if narrow) scoring/visibility behavior change.
        if (($conditions['operator'] ?? null) === 'or') {
            foreach ($conditions['conditions'] ?? [] as $condition) {
                $questionCode = $condition['question_code'] ?? null;

                if (! $questionCode) {
                    continue;
                }

                $actualValue = $valueResolver($questionCode);

                if (static::evaluateCondition($actualValue, $condition['value'] ?? null, $condition['operator'] ?? 'equals')) {
                    return true;
                }
            }

            return false;
        }

        // AND: show only if ALL conditions are true.
        if (($conditions['operator'] ?? null) === 'and') {
            foreach ($conditions['conditions'] ?? [] as $condition) {
                $questionCode = $condition['question_code'] ?? null;

                if (! $questionCode) {
                    return false;
                }

                $actualValue = $valueResolver($questionCode);

                if (! static::evaluateCondition($actualValue, $condition['value'] ?? null, $condition['operator'] ?? 'equals')) {
                    return false;
                }
            }

            return true;
        }

        // Single condition (legacy format with question_code at root).
        if (isset($conditions['question_code'])) {
            $questionCode = $conditions['question_code'];

            if (! $questionCode) {
                return true;
            }

            $actualValue = $valueResolver($questionCode);

            // CRITICAL: no value yet means hidden/excluded by default.
            if ($actualValue === null || $actualValue === '') {
                return false;
            }

            return static::evaluateCondition($actualValue, $conditions['value'] ?? null, $conditions['operator'] ?? 'equals');
        }

        // Legacy show_if format.
        if (isset($conditions['show_if'])) {
            $showIf = $conditions['show_if'];
            $questionCode = $showIf['question_code'] ?? null;

            if (! $questionCode) {
                return true;
            }

            $actualValue = $valueResolver($questionCode);

            if ($actualValue === null || $actualValue === '') {
                return false;
            }

            return $actualValue === ($showIf['value'] ?? null);
        }

        // Every real call site already guards `empty($conditions)` before
        // calling isVisible() at all, so a genuinely-absent condition never
        // reaches here — only a non-empty but unrecognized/malformed shape
        // does (e.g. a single condition missing its question_code key).
        // Fail closed like everywhere else in this method, not open.
        return false;
    }

    private static function evaluateCondition($actualValue, $expectedValue, string $operator): bool
    {
        return match ($operator) {
            'equals' => $actualValue === $expectedValue,
            'not_equals' => $actualValue !== $expectedValue,
            'in' => is_array($expectedValue) && in_array($actualValue, $expectedValue),
            'not_in' => is_array($expectedValue) && ! in_array($actualValue, $expectedValue),
            'greater_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue > $expectedValue,
            'less_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue < $expectedValue,
            default => false, // CRITICAL: default to false (hide) for safety
        };
    }
}
