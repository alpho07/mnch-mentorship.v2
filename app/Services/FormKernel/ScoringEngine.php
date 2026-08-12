<?php

namespace App\Services\FormKernel;

use App\Services\ConditionalLogicEvaluator;
use Illuminate\Support\Collection;

/**
 * Pure scoring algorithm — section/overall scoring, group-completeness
 * resolution, and conditional exclusion — with zero database access and no
 * dependency on which concrete Assessment or Survey models the caller uses.
 * Every method takes already-fetched Collections and returns plain arrays;
 * the caller (DynamicScoringService for assessments, SurveyScoringService
 * for surveys) is responsible for building those inputs from its own tables
 * and persisting the returned data into its own score table. This split is
 * what lets both engines share the identical scoring rules without sharing
 * a database schema.
 */
class ScoringEngine
{
    /**
     * A `group_completeness` question's value is derived from its scored
     * sibling questions (same `group`, same section, excluding other
     * group_completeness questions): complete (1/"Yes") iff every sibling
     * currently has a response scored at that sibling's own maximum
     * possible score; incomplete (0/"No") otherwise, including when a
     * sibling is unanswered. $responsesByQuestionId must already be keyed
     * by question id and cover every non-group_completeness question in
     * $questions.
     *
     * @return array<int, array{question_id: int, response_value: string, score: int}>
     */
    public static function resolveGroupCompletenessResponses(Collection $questions, Collection $responsesByQuestionId): array
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return [];
        }

        $updates = [];

        foreach ($completenessQuestions as $completenessQuestion) {
            $siblings = $questions->filter(fn ($q) => $q->group === $completenessQuestion->group
                && $q->id !== $completenessQuestion->id
                && $q->question_type !== 'group_completeness');

            if ($siblings->isEmpty()) {
                continue;
            }

            $allComplete = $siblings->every(function ($sibling) use ($responsesByQuestionId) {
                $response = $responsesByQuestionId->get($sibling->id);

                if (! $response || $response->score === null) {
                    return false;
                }

                $maxForSibling = ! empty($sibling->scoring_map) ? max($sibling->scoring_map) : 1;

                return (float) $response->score >= (float) $maxForSibling;
            });

            $updates[] = [
                'question_id' => $completenessQuestion->id,
                'response_value' => $allComplete ? 'Yes' : 'No',
                'score' => $allComplete ? 1 : 0,
            ];
        }

        return $updates;
    }

    /**
     * Excludes any scored question whose display_conditions evaluate to
     * "hidden" given the already-submitted responses — a question that
     * wouldn't have been shown on the form shouldn't count toward the
     * section's score. $responseValuesByQuestionCode maps question_code =>
     * response_value across the WHOLE survey/assessment (not just the
     * current section), since a condition can reference a question
     * elsewhere.
     */
    public static function excludeConditionallyHiddenQuestions(Collection $questions, array $responseValuesByQuestionCode): Collection
    {
        $conditional = $questions->filter(fn ($q) => ! empty($q->display_conditions));

        if ($conditional->isEmpty()) {
            return $questions;
        }

        $valueResolver = fn (string $questionCode) => $responseValuesByQuestionCode[$questionCode] ?? null;

        return $questions->filter(function ($question) use ($valueResolver) {
            if (empty($question->display_conditions)) {
                return true;
            }

            return ConditionalLogicEvaluator::isVisible($question->display_conditions, $valueResolver);
        });
    }

    /**
     * @return array{total_score: float, max_score: int, percentage: float, grade: string, total_questions: int, answered_questions: int, skipped_questions: int}
     */
    public static function calculateSectionScore(Collection $questions, Collection $responses): array
    {
        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            return [
                'total_score' => 0, 'max_score' => 0, 'percentage' => 0,
                'grade' => static::calculateGrade(0), 'total_questions' => 0,
                'answered_questions' => 0, 'skipped_questions' => 0,
            ];
        }

        $totalScore = $responses->whereNotNull('score')->sum('score');
        $maxScore = $totalQuestions;
        $answeredQuestions = $responses->whereNotNull('response_value')->count();
        $skippedQuestions = $totalQuestions - $answeredQuestions;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        return [
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'grade' => static::calculateGrade($percentage),
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'skipped_questions' => $skippedQuestions,
        ];
    }

    /**
     * @return array{total_score: float, percentage: float, grade: ?string}
     */
    public static function calculateOverallScore(Collection $sectionScores): array
    {
        if ($sectionScores->isEmpty()) {
            return ['total_score' => 0, 'percentage' => 0, 'grade' => null];
        }

        $totalScore = $sectionScores->sum('total_score');
        $overallPercentage = round($sectionScores->avg('percentage'), 2);

        return [
            'total_score' => $totalScore,
            'percentage' => $overallPercentage,
            'grade' => static::calculateGrade($overallPercentage),
        ];
    }

    /**
     * Grade thresholds: ≥80% = green, ≥50% = yellow, <50% = red.
     */
    public static function calculateGrade(float $percentage): string
    {
        if ($percentage >= 80) {
            return 'green';
        }
        if ($percentage >= 50) {
            return 'yellow';
        }

        return 'red';
    }
}
