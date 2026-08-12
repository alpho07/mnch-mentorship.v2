<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\EditSurveyResponse;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EditSurveyResponseEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_event_scoped_response_only_shows_that_events_sections(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'update_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'update_survey::response']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'ESR_EVENT_TEST', 'name' => 'ESR Event Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $demographics = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);
        $vitals = SurveySection::create(['survey_id' => $survey->id, 'code' => 'vitals', 'name' => 'Vitals', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $demographics->id, 'question_code' => 'ESR_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $vitals->id, 'question_code' => 'ESR_Q2', 'question_text' => 'Q2', 'question_type' => 'yes_no']);
        $demographics->events()->attach($baseline->id);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'draft']);

        $component = Livewire::test(EditSurveyResponse::class, ['record' => $response->getRouteKey()]);

        $demographicsQuestionId = $demographics->questions->first()->id ?? 'x';
        $schema = $component->instance()->form->getFlatFields();
        $this->assertArrayNotHasKey("question_response_{$demographicsQuestionId}", $schema);
    }
}
