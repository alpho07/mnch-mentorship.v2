<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\CreateSurveyResponse;
use App\Filament\Resources\SurveyResponseResource\Pages\EditSurveyResponse;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResponseResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'create_survey::response', 'update_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'create_survey::response', 'update_survey::response']);

        return $user;
    }

    public function test_a_draft_response_can_be_created_for_a_survey(): void
    {
        $user = $this->actingAdmin();
        $this->actingAs($user);
        $survey = Survey::create(['code' => 'SRR_TEST', 'name' => 'SRR Test', 'is_active' => true]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id, 'respondent_name' => 'Jane Doe'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'respondent_name' => 'Jane Doe', 'status' => 'draft', 'created_by' => $user->id,
        ]);
    }

    public function test_submitting_the_edit_page_saves_answers_and_marks_the_response_submitted(): void
    {
        $this->actingAs($this->actingAdmin());
        $survey = Survey::create(['code' => 'SRR_SUBMIT', 'name' => 'SRR Submit', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SRR_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        Livewire::test(EditSurveyResponse::class, ['record' => $response->getRouteKey()])
            ->fillForm(["question_response_{$question->id}" => 'Yes'])
            ->callAction('submit');

        $response->refresh();
        $this->assertSame('submitted', $response->status);
        $this->assertNotNull($response->submitted_at);
        $this->assertDatabaseHas('survey_question_responses', [
            'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
    }
}
