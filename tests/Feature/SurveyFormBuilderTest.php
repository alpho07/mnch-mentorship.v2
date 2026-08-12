<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyFormBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_for_section_renders_a_field_per_active_question(): void
    {
        $survey = Survey::create(['code' => 'BUILD_TEST', 'name' => 'Build Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'BT_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'BT_Q2', 'question_text' => 'Q2', 'question_type' => 'text', 'order' => 2, 'is_active' => false]);

        $fields = SurveyFormBuilder::buildForSection($section->id);

        $this->assertNotEmpty($fields);
    }

    public function test_build_for_survey_wraps_each_active_section_in_its_own_form_section(): void
    {
        $survey = Survey::create(['code' => 'WRAP_TEST', 'name' => 'Wrap Test', 'is_active' => true]);
        $sectionA = SurveySection::create(['survey_id' => $survey->id, 'code' => 'a', 'name' => 'Section A', 'order' => 1]);
        $sectionB = SurveySection::create(['survey_id' => $survey->id, 'code' => 'b', 'name' => 'Section B', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $sectionA->id, 'question_code' => 'WT_QA', 'question_text' => 'QA', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $sectionB->id, 'question_code' => 'WT_QB', 'question_text' => 'QB', 'question_type' => 'yes_no']);

        $sections = SurveyFormBuilder::buildForSurvey($survey);

        $this->assertCount(2, $sections);
        $this->assertTrue(collect($sections)->every(fn ($s) => $s instanceof \Filament\Forms\Components\Section));
    }

    public function test_save_responses_persists_scored_answer_and_triggers_section_scoring(): void
    {
        $survey = Survey::create(['code' => 'SAVE_TEST', 'name' => 'Save Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'ST_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        SurveyFormBuilder::saveResponses($response->id, $section->id, [
            "question_response_{$question->id}" => 'Yes',
        ]);

        $saved = SurveyQuestionResponse::where('survey_response_id', $response->id)->where('survey_question_id', $question->id)->first();
        $this->assertSame('Yes', $saved->response_value);
        $this->assertEquals(1, $saved->score);
        $this->assertNotNull(SurveySectionScore::where('survey_response_id', $response->id)->where('survey_section_id', $section->id)->first());
    }

    public function test_save_responses_json_encodes_checkbox_and_matrix_answers(): void
    {
        $survey = Survey::create(['code' => 'JSON_SAVE_TEST', 'name' => 'JSON Save Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $checkbox = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'JS_CB', 'question_text' => 'Pick colors',
            'question_type' => 'checkbox', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $matrix = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'JS_MX', 'question_text' => 'Rate it',
            'question_type' => 'matrix', 'options' => ['columns' => ['Agree', 'Disagree'], 'rows' => [['key' => 'r1', 'label' => 'Row 1']]],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        SurveyFormBuilder::saveResponses($response->id, $section->id, [
            "question_response_{$checkbox->id}" => ['Red', 'Blue'],
            "question_response_{$matrix->id}_r1" => 'Agree',
        ]);

        $cbSaved = SurveyQuestionResponse::where('survey_question_id', $checkbox->id)->first();
        $mxSaved = SurveyQuestionResponse::where('survey_question_id', $matrix->id)->first();

        $this->assertSame(['Red', 'Blue'], json_decode($cbSaved->response_value, true));
        $this->assertSame(['r1' => 'Agree'], json_decode($mxSaved->response_value, true));
    }
}
