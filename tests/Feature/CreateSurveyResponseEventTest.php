<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\CreateSurveyResponse;
use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateSurveyResponseEventTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'create_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'create_survey::response']);
        $this->actingAs($user);

        return $user;
    }

    public function test_creating_a_response_for_a_repeatable_event_computes_instance_number(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_EVENT_TEST', 'name' => 'CSR Event Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm([
                'survey_id' => $survey->id,
                'survey_event_id' => $event->id,
                'subject_type' => Facility::class,
                'subject_id' => $facility->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 2,
        ]);
    }

    public function test_creating_a_response_for_a_fixed_event_leaves_instance_number_null(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_FIXED_TEST', 'name' => 'CSR Fixed Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1, 'repeatable' => false]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id, 'survey_event_id' => $event->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'survey_event_id' => $event->id, 'event_instance_number' => null,
        ]);
    }

    public function test_event_field_is_absent_from_the_form_schema_for_a_survey_with_no_events(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_NO_EVENTS_TEST', 'name' => 'CSR No Events Test', 'is_active' => true]);

        $component = Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id]);

        $component->assertFormFieldIsHidden('survey_event_id');
    }
}
