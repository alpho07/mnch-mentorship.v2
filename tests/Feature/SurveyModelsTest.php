<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_hierarchy_can_be_created_and_traversed(): void
    {
        $survey = Survey::create(['code' => 'TEST_SURVEY', 'name' => 'Test Survey', 'is_active' => true]);
        $section = SurveySection::create([
            'survey_id' => $survey->id, 'code' => 'general', 'name' => 'General', 'order' => 1,
        ]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'Q1', 'question_text' => 'Do you like it?',
            'question_type' => 'yes_no', 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);

        $this->assertTrue($survey->fresh()->sections->first()->is($section));
        $this->assertTrue($section->fresh()->questions->first()->is($question));
        $this->assertTrue($question->fresh()->section->is($section));
    }

    public function test_survey_response_links_to_a_polymorphic_subject_or_none(): void
    {
        $survey = Survey::create(['code' => 'ANON_SURVEY', 'name' => 'Anonymous', 'is_active' => true]);

        $anonymous = SurveyResponse::create([
            'survey_id' => $survey->id, 'respondent_name' => 'Jane Doe', 'status' => 'draft',
        ]);

        $this->assertNull($anonymous->subject_type);
        $this->assertNull($anonymous->subject);

        $facility = Facility::factory()->create();
        $targeted = SurveyResponse::create([
            'survey_id' => $survey->id, 'subject_type' => Facility::class,
            'subject_id' => $facility->id, 'status' => 'draft',
        ]);

        $this->assertTrue($targeted->fresh()->subject->is($facility));
    }

    public function test_marking_a_response_submitted_stamps_timestamp(): void
    {
        $survey = Survey::create(['code' => 'SUBMIT_TEST', 'name' => 'Submit Test', 'is_active' => true]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        $response->markSubmitted();

        $this->assertSame('submitted', $response->fresh()->status);
        $this->assertNotNull($response->fresh()->submitted_at);
    }
}
