<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_returns_response_count_and_empty_events_for_a_non_longitudinal_survey(): void
    {
        $survey = Survey::create(['code' => 'DASH_BASE_TEST', 'name' => 'Dash Base Test', 'is_active' => true]);
        SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(1, $data['response_count']);
        $this->assertSame([], $data['events']);
    }

    public function test_a_section_with_no_active_questions_is_omitted(): void
    {
        $survey = Survey::create(['code' => 'DASH_EMPTY_SECTION_TEST', 'name' => 'Dash Empty Section Test', 'is_active' => true]);
        SurveySection::create(['survey_id' => $survey->id, 'code' => 'empty', 'name' => 'Empty', 'order' => 1]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame([], $data['sections']);
    }

    public function test_overall_completion_sums_answered_and_total_across_scored_sections(): void
    {
        $survey = Survey::create(['code' => 'DASH_COMPLETION_TEST', 'name' => 'Dash Completion Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DC_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveySectionScore::create([
            'survey_response_id' => $response->id, 'survey_section_id' => $section->id,
            'total_score' => 1, 'max_score' => 1, 'percentage' => 100, 'grade' => 'green',
            'total_questions' => 1, 'answered_questions' => 1, 'skipped_questions' => 0,
        ]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(1, $data['overall_completion']['answered']);
        $this->assertSame(1, $data['overall_completion']['total']);
        $this->assertSame(100.0, (float) $data['overall_completion']['percentage']);
        $this->assertSame('green', $data['overall_completion']['grade']);
    }

    public function test_a_scored_section_gets_a_completion_meter_averaging_its_section_scores(): void
    {
        $survey = Survey::create(['code' => 'DASH_SECTION_METER_TEST', 'name' => 'Dash Section Meter Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SM_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveySectionScore::create([
            'survey_response_id' => $response->id, 'survey_section_id' => $section->id,
            'total_score' => 0, 'max_score' => 1, 'percentage' => 40, 'grade' => 'red',
            'total_questions' => 1, 'answered_questions' => 0, 'skipped_questions' => 1,
        ]);

        $data = SurveyDashboardService::build($survey);

        $this->assertSame(40.0, (float) $data['sections'][0]['completion']['percentage']);
        $this->assertSame('red', $data['sections'][0]['completion']['grade']);
    }

    public function test_an_unscored_section_has_a_null_completion_meter(): void
    {
        $survey = Survey::create(['code' => 'DASH_UNSCORED_TEST', 'name' => 'Dash Unscored Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => false]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'UN_Q1', 'question_text' => 'Q1', 'question_type' => 'text']);

        $data = SurveyDashboardService::build($survey);

        $this->assertNull($data['sections'][0]['completion']);
    }

    public function test_select_question_bar_data_is_counted_in_the_questions_own_option_order(): void
    {
        $survey = Survey::create(['code' => 'DASH_BAR_TEST', 'name' => 'Dash Bar Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'BAR_Q1', 'question_text' => 'Favorite color',
            'question_type' => 'select', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $r1 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        $r2 = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $r1->id, 'survey_question_id' => $question->id, 'response_value' => 'Blue']);
        SurveyQuestionResponse::create(['survey_response_id' => $r2->id, 'survey_question_id' => $question->id, 'response_value' => 'Blue']);

        $data = SurveyDashboardService::build($survey);
        $questionData = $data['sections'][0]['questions'][0];

        $this->assertSame('bar', $questionData['chart']);
        $this->assertSame(
            [['label' => 'Red', 'count' => 0], ['label' => 'Green', 'count' => 0], ['label' => 'Blue', 'count' => 2]],
            $questionData['data']
        );
    }

    public function test_checkbox_question_counts_each_selected_option_independently(): void
    {
        $survey = Survey::create(['code' => 'DASH_CHECKBOX_TEST', 'name' => 'Dash Checkbox Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'CB_Q1', 'question_text' => 'Pick colors',
            'question_type' => 'checkbox', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => json_encode(['Red', 'Blue'])]);

        $data = SurveyDashboardService::build($survey);
        $counts = collect($data['sections'][0]['questions'][0]['data'])->pluck('count', 'label');

        $this->assertSame(1, $counts['Red']);
        $this->assertSame(0, $counts['Green']);
        $this->assertSame(1, $counts['Blue']);
    }

    public function test_draft_responses_are_excluded_from_bar_data(): void
    {
        $survey = Survey::create(['code' => 'DASH_DRAFT_TEST', 'name' => 'Dash Draft Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'DRAFT_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no',
        ]);
        $draft = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $draft->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        $data = SurveyDashboardService::build($survey);
        $counts = collect($data['sections'][0]['questions'][0]['data'])->pluck('count', 'label');

        $this->assertSame(0, $counts['Yes']);
    }
}
