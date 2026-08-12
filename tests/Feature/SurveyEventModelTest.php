<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyEventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_has_events_ordered_by_order_column(): void
    {
        $survey = Survey::create(['code' => 'EVENT_MODEL_TEST', 'name' => 'Event Model Test', 'is_active' => true]);
        $second = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $first = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);

        $ordered = $survey->events()->ordered()->get();

        $this->assertTrue($ordered->first()->is($first));
        $this->assertTrue($ordered->last()->is($second));
    }

    public function test_event_can_be_attached_to_sections_and_back(): void
    {
        $survey = Survey::create(['code' => 'EVENT_SECTION_TEST', 'name' => 'Event Section Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);

        $event->sections()->attach($section->id);

        $this->assertTrue($event->fresh()->sections->first()->is($section));
        $this->assertTrue($section->fresh()->events->first()->is($event));
    }

    public function test_response_belongs_to_an_event(): void
    {
        $survey = Survey::create(['code' => 'RESPONSE_EVENT_TEST', 'name' => 'Response Event Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $event->id, 'status' => 'draft']);

        $this->assertTrue($response->fresh()->event->is($event));
        $this->assertTrue($event->fresh()->responses->first()->is($response));
    }
}
