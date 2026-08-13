<?php

namespace Tests\Unit;

use App\Services\ConditionalLogicEvaluator;
use Tests\TestCase;

class ConditionalLogicEvaluatorTest extends TestCase
{
    public function test_single_condition_shows_when_parent_matches(): void
    {
        $conditions = ['question_code' => 'GATE', 'operator' => 'equals', 'value' => 'Yes'];

        $this->assertTrue(ConditionalLogicEvaluator::isVisible($conditions, fn () => 'Yes'));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => 'No'));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => null));
    }

    /**
     * Regression: a single-condition display_conditions value that's
     * missing its `question_code` key (e.g. corrupted by an admin-form
     * save that dropped the field) used to fall through every branch and
     * hit the final `return true` default — making the gated field/group
     * ALWAYS visible instead of hidden. Every real call site already
     * guards `empty($conditions)` before calling isVisible() at all, so
     * that fallback only ever fires for a malformed, non-empty shape like
     * this one — it must fail closed (hidden), not open (visible).
     */
    public function test_malformed_single_condition_missing_question_code_fails_closed(): void
    {
        $conditions = ['operator' => 'equals', 'value' => 'Yes'];

        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => 'Yes'));
    }

    public function test_and_conditions_require_every_match(): void
    {
        $conditions = [
            'operator' => 'and',
            'conditions' => [
                ['question_code' => 'A', 'operator' => 'equals', 'value' => 'Yes'],
                ['question_code' => 'B', 'operator' => 'equals', 'value' => 'Yes'],
            ],
        ];

        $resolver = fn (string $code) => ['A' => 'Yes', 'B' => 'No'][$code] ?? null;
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, $resolver));

        $resolver = fn (string $code) => ['A' => 'Yes', 'B' => 'Yes'][$code] ?? null;
        $this->assertTrue(ConditionalLogicEvaluator::isVisible($conditions, $resolver));
    }

    public function test_intersects_shows_when_a_multi_select_answer_overlaps_the_expected_list(): void
    {
        $conditions = ['question_code' => 'DOC_TYPE', 'operator' => 'intersects', 'value' => ['Paper based', 'Hybrid']];

        $this->assertTrue(ConditionalLogicEvaluator::isVisible($conditions, fn () => ['Paper based']));
        $this->assertTrue(ConditionalLogicEvaluator::isVisible($conditions, fn () => ['EMR', 'Hybrid']));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => ['EMR']));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => []));
    }

    /**
     * A multi_select answer resolved from a persisted response_value
     * (DynamicScoringService, HasSectionNavigation) comes back as the raw
     * JSON string saveResponses() stored, not a decoded array — must
     * evaluate identically to the live-form array case.
     */
    public function test_intersects_decodes_a_json_encoded_multi_select_value(): void
    {
        $conditions = ['question_code' => 'DOC_TYPE', 'operator' => 'intersects', 'value' => ['Paper based', 'Hybrid']];

        $this->assertTrue(ConditionalLogicEvaluator::isVisible($conditions, fn () => '["Paper based"]'));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => '["EMR"]'));
        $this->assertFalse(ConditionalLogicEvaluator::isVisible($conditions, fn () => '[]'));
    }
}
