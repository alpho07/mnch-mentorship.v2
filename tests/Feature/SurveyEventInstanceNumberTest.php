<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyEventInstanceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_instance_for_a_subject_is_one(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_FIRST_TEST', 'name' => 'Instance First Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();

        $this->assertSame(1, $event->nextInstanceNumberFor(Facility::class, $facility->id));
    }

    public function test_next_instance_increments_from_the_subjects_existing_max(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_INCREMENT_TEST', 'name' => 'Instance Increment Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 2, 'status' => 'submitted',
        ]);

        $this->assertSame(3, $event->nextInstanceNumberFor(Facility::class, $facility->id));
    }

    public function test_different_subjects_number_independently(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_INDEPENDENT_TEST', 'name' => 'Instance Independent Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facilityA->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        $this->assertSame(1, $event->nextInstanceNumberFor(Facility::class, $facilityB->id));
    }

    public function test_null_subject_shares_one_bucket(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_NULL_TEST', 'name' => 'Instance Null Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        $this->assertSame(2, $event->nextInstanceNumberFor(null, null));
    }
}
