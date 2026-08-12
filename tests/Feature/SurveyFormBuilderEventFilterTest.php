<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\SurveyFormBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFormBuilderEventFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_section_with_no_event_mapping_appears_for_every_event(): void
    {
        $survey = Survey::create(['code' => 'FILTER_ALL_TEST', 'name' => 'Filter All Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'general', 'name' => 'General', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'FA_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        $baselineSections = SurveyFormBuilder::buildForSurvey($survey, null, $baseline);
        $followupSections = SurveyFormBuilder::buildForSurvey($survey, null, $followup);

        $this->assertCount(1, $baselineSections);
        $this->assertCount(1, $followupSections);
    }

    public function test_a_section_mapped_to_one_event_is_excluded_from_another(): void
    {
        $survey = Survey::create(['code' => 'FILTER_ONE_TEST', 'name' => 'Filter One Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $demographics = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);
        $vitals = SurveySection::create(['survey_id' => $survey->id, 'code' => 'vitals', 'name' => 'Vitals', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $demographics->id, 'question_code' => 'FO_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $vitals->id, 'question_code' => 'FO_Q2', 'question_text' => 'Q2', 'question_type' => 'yes_no']);
        $demographics->events()->attach($baseline->id);

        $baselineSections = SurveyFormBuilder::buildForSurvey($survey, null, $baseline);
        $followupSections = SurveyFormBuilder::buildForSurvey($survey, null, $followup);

        $this->assertCount(2, $baselineSections);
        $this->assertCount(1, $followupSections);
        $this->assertSame('Vitals', $followupSections[0]->getHeading());
    }

    public function test_passing_no_event_renders_every_active_section_unchanged(): void
    {
        $survey = Survey::create(['code' => 'FILTER_NONE_TEST', 'name' => 'Filter None Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'FN_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        $sections = SurveyFormBuilder::buildForSurvey($survey);

        $this->assertCount(1, $sections);
    }
}
