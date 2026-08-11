<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentSectionScore;

class DynamicScoringService
{
    /**
     * Recalculate score for a specific section.
     */
    public static function recalculateSectionScore(int $assessmentId, int $sectionId): void
    {
        $section = AssessmentSection::findOrFail($sectionId);

        if (! $section->is_scored) {
            return;
        }

        // Get all active, scored questions — mortality_three_month is
        // always excluded regardless of its is_scored flag: a 3-count
        // value is data for the PDF report only, never a scoreable answer.
        $questions = $section->questions()
            ->active()
            ->scored()
            ->where('question_type', '!=', 'mortality_three_month')
            ->get();

        // Resolve any group_completeness questions' responses from their
        // sibling groups before scoring sums them like any other question.
        // No-op for sections without one — i.e. every section that existed
        // before this feature.
        self::resolveGroupCompletenessResponses($assessmentId, $questions);

        // ── Conditional exclusion ───────────────────────────────────────────
        // A scored question whose display_conditions resolve to "hidden"
        // (given the assessment's actual answers so far) doesn't count
        // toward the section's max_score/total_score — general rule, not
        // special-cased per section. Conditions can reference a question in
        // a different section, so the resolver looks across the whole
        // assessment, not just this section's responses.
        $questions = self::excludeConditionallyHiddenQuestions($assessmentId, $questions);
        // ─────────────────────────────────────────────────────────────────────

        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            return;
        }

        $responses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
            ->whereIn('assessment_question_id', $questions->pluck('id'))
            ->get();

        $totalScore = $responses->whereNotNull('score')->sum('score');
        $maxScore = $totalQuestions;
        $answeredQuestions = $responses->whereNotNull('response_value')->count();
        $skippedQuestions = $totalQuestions - $answeredQuestions;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        AssessmentSectionScore::updateOrCreate(
            ['assessment_id' => $assessmentId, 'assessment_section_id' => $sectionId],
            [
                'total_score' => $totalScore,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'grade' => self::calculateGrade($percentage),
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'skipped_questions' => $skippedQuestions,
            ]
        );

        self::recalculateOverallScore($assessmentId);
    }

    /**
     * Excludes any scored question whose display_conditions evaluate to
     * "hidden" given the assessment's actual submitted responses — a
     * question that wouldn't have been shown on the form shouldn't count
     * against the section's score. Questions with no display_conditions are
     * always included (unconditional).
     */
    private static function excludeConditionallyHiddenQuestions(int $assessmentId, $questions): mixed
    {
        $conditional = $questions->filter(fn ($q) => ! empty($q->display_conditions));

        if ($conditional->isEmpty()) {
            return $questions;
        }

        // question_code => response_value map, built once, spanning the
        // whole assessment (not just this section) since a condition can
        // reference a question in a different section.
        $responsesByCode = AssessmentQuestionResponse::query()
            ->where('assessment_id', $assessmentId)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code');

        $valueResolver = fn (string $questionCode) => $responsesByCode[$questionCode] ?? null;

        return $questions->filter(function ($question) use ($valueResolver) {
            if (empty($question->display_conditions)) {
                return true;
            }

            return ConditionalLogicEvaluator::isVisible($question->display_conditions, $valueResolver);
        });
    }

    /**
     * A `group_completeness` question's response is derived, not submitted:
     * 1 (Yes) iff every other active, scored sibling sharing its `group`
     * (within the same section's already-loaded $questions collection)
     * currently has a response scored at that sibling's own maximum
     * possible score; 0 (No) otherwise, including when a sibling is
     * unanswered. Upserts the response so the normal sum below picks it up
     * exactly like any other scored question.
     */
    private static function resolveGroupCompletenessResponses(int $assessmentId, $questions): void
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return;
        }

        foreach ($completenessQuestions as $completenessQuestion) {
            $siblings = $questions->filter(fn ($q) => $q->group === $completenessQuestion->group
                && $q->id !== $completenessQuestion->id
                && $q->question_type !== 'group_completeness');

            if ($siblings->isEmpty()) {
                continue;
            }

            $siblingResponses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
                ->whereIn('assessment_question_id', $siblings->pluck('id'))
                ->get()
                ->keyBy('assessment_question_id');

            $allComplete = $siblings->every(function ($sibling) use ($siblingResponses) {
                $response = $siblingResponses->get($sibling->id);

                if (! $response || $response->score === null) {
                    return false;
                }

                $maxForSibling = ! empty($sibling->scoring_map) ? max($sibling->scoring_map) : 1;

                return (float) $response->score >= (float) $maxForSibling;
            });

            AssessmentQuestionResponse::updateOrCreate(
                ['assessment_id' => $assessmentId, 'assessment_question_id' => $completenessQuestion->id],
                ['response_value' => $allComplete ? 'Yes' : 'No', 'score' => $allComplete ? 1 : 0]
            );
        }
    }

    /**
     * Recalculate overall assessment score from all section scores.
     */
    public static function recalculateOverallScore(int $assessmentId): void
    {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();

        if ($sectionScores->isEmpty()) {
            return;
        }

        $totalScore = $sectionScores->sum('total_score');
        // Average of section percentages — each section weighted equally regardless of question count
        $overallPercentage = round($sectionScores->avg('percentage'), 2);
        $overallGrade = self::calculateGrade($overallPercentage);

        Assessment::where('id', $assessmentId)->update([
            'overall_score' => $totalScore,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
        ]);
    }

    /**
     * Recalculate all scored sections for an assessment.
     * Called on final submission.
     */
    public function recalculateAllSections(int $assessmentId): void
    {
        $sections = AssessmentSection::active()->scored()->get();

        foreach ($sections as $section) {
            self::recalculateSectionScore($assessmentId, $section->id);
        }

        self::recalculateOverallScore($assessmentId);
    }

    /**
     * Grade thresholds: ≥80% = green, ≥50% = yellow, <50% = red.
     */
    protected static function calculateGrade(float $percentage): string
    {
        if ($percentage >= 80) {
            return 'green';
        }
        if ($percentage >= 50) {
            return 'yellow';
        }

        return 'red';
    }

    // ── Legacy / compatibility methods ────────────────────────────────────────

    public function isSectionComplete(int $assessmentId, int $sectionId): bool
    {
        $section = AssessmentSection::findOrFail($sectionId);
        $progress = $section->getProgressForAssessment($assessmentId);

        return ($progress['percentage'] ?? 0) === 100.0;
    }

    public function getSectionResponses(int $assessmentId, int $sectionId): array
    {
        $section = AssessmentSection::with('questions')->findOrFail($sectionId);
        $responses = [];
        foreach ($section->questions as $question) {
            $response = $question->getResponseForAssessment($assessmentId);
            $responses[$question->question_code] = [
                'question' => $question,
                'response' => $response,
                'answered' => $response && $response->response_value !== null,
            ];
        }

        return $responses;
    }

    public function getAssessmentSummary(int $assessmentId): array
    {
        $assessment = Assessment::findOrFail($assessmentId);
        $sections = AssessmentSection::active()->scored()->ordered()->get();
        $summary = [];

        foreach ($sections as $section) {
            $sectionScore = AssessmentSectionScore::where('assessment_id', $assessmentId)
                ->where('assessment_section_id', $section->id)
                ->first();
            $summary[$section->code] = [
                'section' => $section,
                'score' => $sectionScore,
            ];
        }

        return [
            'assessment' => $assessment,
            'sections' => $summary,
            'overall' => [
                'score' => $assessment->overall_score,
                'percentage' => $assessment->overall_percentage,
                'grade' => $assessment->overall_grade,
            ],
        ];
    }

    public function getAssessmentStats(int $assessmentId): array
    {
        $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();
        if ($sectionScores->isEmpty()) {
            return ['overall_percentage' => 0, 'overall_grade' => null, 'total_sections' => 0];
        }

        return [
            'overall_percentage' => $sectionScores->avg('percentage'),
            'overall_grade' => self::calculateGrade($sectionScores->avg('percentage')),
            'green_count' => $sectionScores->where('grade', 'green')->count(),
            'yellow_count' => $sectionScores->where('grade', 'yellow')->count(),
            'red_count' => $sectionScores->where('grade', 'red')->count(),
            'total_sections' => $sectionScores->count(),
            'total_questions' => $sectionScores->sum('total_questions'),
            'answered_questions' => $sectionScores->sum('answered_questions'),
            'skipped_questions' => $sectionScores->sum('skipped_questions'),
        ];
    }
}
