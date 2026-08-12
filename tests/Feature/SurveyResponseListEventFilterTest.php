<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\ListSurveyResponses;
use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResponseListEventFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_survey::response', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_survey::response']);
        $this->actingAs($user);

        return $user;
    }

    public function test_event_filter_narrows_the_list_to_one_events_responses(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'LIST_EVENT_FILTER_TEST', 'name' => 'List Event Filter Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $baselineResponse = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $baseline->id, 'status' => 'draft']);
        SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'draft']);

        Livewire::test(ListSurveyResponses::class)
            ->filterTable('survey_event_id', $baseline->id)
            ->assertCanSeeTableRecords([$baselineResponse])
            ->assertCountTableRecords(1);
    }

    public function test_subject_filter_narrows_the_list_by_facility_name(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'LIST_SUBJECT_FILTER_TEST', 'name' => 'List Subject Filter Test', 'is_active' => true]);
        $target = Facility::factory()->create(['name' => 'Kitui District Hospital']);
        $other = Facility::factory()->create(['name' => 'Machakos Level 4']);
        $targetResponse = SurveyResponse::create(['survey_id' => $survey->id, 'subject_type' => Facility::class, 'subject_id' => $target->id, 'status' => 'draft']);
        SurveyResponse::create(['survey_id' => $survey->id, 'subject_type' => Facility::class, 'subject_id' => $other->id, 'status' => 'draft']);

        Livewire::test(ListSurveyResponses::class)
            ->filterTable('subject', ['name' => 'Kitui'])
            ->assertCanSeeTableRecords([$targetResponse])
            ->assertCountTableRecords(1);
    }
}
