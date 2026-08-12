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
        $responsesByCode = AssessmentQuestionResponse::query()
            ->where('assessment_id', $assessmentId)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();

        $questions = \App\Services\FormKernel\ScoringEngine::excludeConditionallyHiddenQuestions($questions, $responsesByCode);
        // ─────────────────────────────────────────────────────────────────────

        if ($questions->isEmpty()) {
            return;
        }

        $responses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
            ->whereIn('assessment_question_id', $questions->pluck('id'))
            ->get();

        $scoreData = \App\Services\FormKernel\ScoringEngine::calculateSectionScore($questions, $responses);

        AssessmentSectionScore::updateOrCreate(
            ['assessment_id' => $assessmentId, 'assessment_section_id' => $sectionId],
            $scoreData
        );

        self::recalculateOverallScore($assessmentId);
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

        $siblingIds = $questions->where('question_type', '!=', 'group_completeness')->pluck('id');

        $responsesByQuestionId = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
            ->whereIn('assessment_question_id', $siblingIds)
            ->get()
            ->keyBy('assessment_question_id');

        $updates = \App\Services\FormKernel\ScoringEngine::resolveGroupCompletenessResponses($questions, $responsesByQuestionId);

        foreach ($updates as $update) {
            AssessmentQuestionResponse::updateOrCreate(
                ['assessment_id' => $assessmentId, 'assessment_question_id' => $update['question_id']],
                ['response_value' => $update['response_value'], 'score' => $update['score']]
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

        $overall = \App\Services\FormKernel\ScoringEngine::calculateOverallScore($sectionScores);

        Assessment::where('id', $assessmentId)->update([
            'overall_score' => $overall['total_score'],
            'overall_percentage' => $overall['percentage'],
            'overall_grade' => $overall['grade'],
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
        return \App\Services\FormKernel\ScoringEngine::calculateGrade($percentage);
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
