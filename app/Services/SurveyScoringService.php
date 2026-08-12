<?php

namespace App\Services;

use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\FormKernel\ScoringEngine;

class SurveyScoringService
{
    public static function recalculateSectionScore(int $surveyResponseId, int $surveySectionId): void
    {
        $section = SurveySection::findOrFail($surveySectionId);

        if (! $section->is_scored) {
            return;
        }

        $questions = $section->questions()->active()->scored()->get();

        self::resolveGroupCompletenessResponses($surveyResponseId, $questions);

        $responsesByCode = SurveyQuestionResponse::query()
            ->where('survey_response_id', $surveyResponseId)
            ->join('survey_questions', 'survey_questions.id', '=', 'survey_question_responses.survey_question_id')
            ->pluck('survey_question_responses.response_value', 'survey_questions.question_code')
            ->all();

        $questions = ScoringEngine::excludeConditionallyHiddenQuestions($questions, $responsesByCode);

        if ($questions->isEmpty()) {
            return;
        }

        $responses = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
            ->whereIn('survey_question_id', $questions->pluck('id'))
            ->get();

        $scoreData = ScoringEngine::calculateSectionScore($questions, $responses);

        SurveySectionScore::updateOrCreate(
            ['survey_response_id' => $surveyResponseId, 'survey_section_id' => $surveySectionId],
            $scoreData
        );

        self::recalculateOverallScore($surveyResponseId);
    }

    private static function resolveGroupCompletenessResponses(int $surveyResponseId, $questions): void
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return;
        }

        $siblingIds = $questions->where('question_type', '!=', 'group_completeness')->pluck('id');

        $responsesByQuestionId = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
            ->whereIn('survey_question_id', $siblingIds)
            ->get()
            ->keyBy('survey_question_id');

        $updates = ScoringEngine::resolveGroupCompletenessResponses($questions, $responsesByQuestionId);

        foreach ($updates as $update) {
            SurveyQuestionResponse::updateOrCreate(
                ['survey_response_id' => $surveyResponseId, 'survey_question_id' => $update['question_id']],
                ['response_value' => $update['response_value'], 'score' => $update['score']]
            );
        }
    }

    public static function recalculateOverallScore(int $surveyResponseId): void
    {
        $sectionScores = SurveySectionScore::where('survey_response_id', $surveyResponseId)->get();

        if ($sectionScores->isEmpty()) {
            return;
        }

        $overall = ScoringEngine::calculateOverallScore($sectionScores);

        SurveyResponse::where('id', $surveyResponseId)->update([
            'overall_score' => $overall['total_score'],
            'overall_percentage' => $overall['percentage'],
        ]);
    }
}
