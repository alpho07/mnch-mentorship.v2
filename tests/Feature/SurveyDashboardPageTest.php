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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyDashboardPageTest extends TestCase
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

    public function test_the_page_mounts_and_loads_dashboard_data_for_the_survey(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'DASH_PAGE_TEST', 'name' => 'Dash Page Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DP_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->assertOk()
            ->assertSet('dashboardData.response_count', 0);
    }

    public function test_changing_the_event_dropdown_reloads_dashboard_data_scoped_to_that_event(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'DASH_PAGE_EVENT_TEST', 'name' => 'Dash Page Event Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $question = SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'DPE_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        $baselineResponse = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $baseline->id, 'status' => 'submitted']);
        SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'submitted']);
        SurveyQuestionResponse::create(['survey_response_id' => $baselineResponse->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes']);

        Livewire::test(SurveyDashboard::class, ['record' => $survey])
            ->assertSet('dashboardData.response_count', 2)
            ->set('eventId', $baseline->id)
            ->assertSet('dashboardData.response_count', 1);
    }
}
