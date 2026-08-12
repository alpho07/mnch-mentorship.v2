<?php

namespace Tests\Feature;

use App\Livewire\PublicSurveyForm;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicSurveyLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_an_unknown_token_shows_the_invalid_link_page(): void
    {
        $response = $this->get('/survey/not-a-real-token');

        $response->assertOk();
        $response->assertSee('not available');
    }

    public function test_visiting_a_valid_public_survey_token_renders_the_form(): void
    {
        Survey::create(['code' => 'PUB_LINK', 'name' => 'Public Link Survey', 'is_active' => true, 'is_public' => true, 'access_token' => 'testtoken123']);

        $response = $this->get('/survey/testtoken123');

        $response->assertOk();
        $response->assertSee('Public Link Survey');
    }

    public function test_submitting_the_public_form_creates_a_response_and_saves_answers(): void
    {
        $survey = Survey::create(['code' => 'PUB_SUBMIT', 'name' => 'Public Submit Survey', 'is_active' => true, 'is_public' => true, 'access_token' => 'submittoken']);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'PUB_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);

        Livewire::test(PublicSurveyForm::class, ['surveyId' => $survey->id])
            ->fillForm([
                'respondent_name' => 'Anon Respondent',
                "question_response_{$question->id}" => 'Yes',
            ])
            ->call('submit');

        $response = SurveyResponse::where('survey_id', $survey->id)->firstOrFail();
        $this->assertSame('submitted', $response->status);
        $this->assertSame('Anon Respondent', $response->respondent_name);
        $this->assertNull($response->subject_type);

        $this->assertDatabaseHas('survey_question_responses', [
            'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
    }
}
