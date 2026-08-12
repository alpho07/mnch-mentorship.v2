<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_score_is_calculated_from_scored_question_responses(): void
    {
        $survey = Survey::create(['code' => 'SCORE_TEST', 'name' => 'Score Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $q1 = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SCORE_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $q2 = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SCORE_Q2', 'question_text' => 'Q2',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $q1->id, 'response_value' => 'Yes', 'score' => 1]);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $q2->id, 'response_value' => 'No', 'score' => 0]);

        SurveyScoringService::recalculateSectionScore($response->id, $section->id);

        $sectionScore = SurveySectionScore::where('survey_response_id', $response->id)->where('survey_section_id', $section->id)->first();

        $this->assertNotNull($sectionScore);
        $this->assertEquals(1, $sectionScore->total_score);
        $this->assertEquals(2, $sectionScore->max_score);
        $this->assertEquals(50.0, (float) $sectionScore->percentage);
        $this->assertEquals(2, $sectionScore->answered_questions);
    }

    public function test_overall_score_averages_across_sections(): void
    {
        $survey = Survey::create(['code' => 'OVERALL_TEST', 'name' => 'Overall Test', 'is_active' => true]);
        $sectionA = SurveySection::create(['survey_id' => $survey->id, 'code' => 'a', 'name' => 'A', 'order' => 1, 'is_scored' => true]);
        $sectionB = SurveySection::create(['survey_id' => $survey->id, 'code' => 'b', 'name' => 'B', 'order' => 2, 'is_scored' => true]);
        $qa = SurveyQuestion::create(['survey_section_id' => $sectionA->id, 'question_code' => 'OA_Q1', 'question_text' => 'QA', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $qb = SurveyQuestion::create(['survey_section_id' => $sectionB->id, 'question_code' => 'OB_Q1', 'question_text' => 'QB', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $qa->id, 'response_value' => 'Yes', 'score' => 1]);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $qb->id, 'response_value' => 'No', 'score' => 0]);

        SurveyScoringService::recalculateSectionScore($response->id, $sectionA->id);
        SurveyScoringService::recalculateSectionScore($response->id, $sectionB->id);

        $this->assertEquals(50.0, (float) $response->fresh()->overall_percentage);
    }
}
