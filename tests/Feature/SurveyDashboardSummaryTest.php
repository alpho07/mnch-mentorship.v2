<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\Pages\SurveyDashboard;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_survey', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_generating_a_summary_sets_it_on_the_page(): void
    {
        $this->actingAdmin();
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'All sections are on track.']]]),
        ]);

        $survey = Survey::create(['code' => 'SUMMARY_TEST', 'name' => 'Summary Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SUM_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->assertSet('summary', null)
            ->call('generateSummary')
            ->assertSet('summary', 'All sections are on track.');
    }

    public function test_changing_the_event_dropdown_clears_an_existing_summary(): void
    {
        $this->actingAdmin();
        config(['services.anthropic.api_key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Summary text.']]]),
        ]);

        $survey = Survey::create(['code' => 'SUMMARY_CLEAR_TEST', 'name' => 'Summary Clear Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'SUM_CLEAR_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $event->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->call('generateSummary')
            ->assertSet('summary', 'Summary text.')
            ->set('eventId', $event->id)
            ->assertSet('summary', null);
    }
}
