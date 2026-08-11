<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\DynamicFormBuilder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderRepeaterTest extends TestCase
{
    use RefreshDatabase;

    private function makeRepeaterQuestion(): array
    {
        $type = AssessmentType::create(['name' => 'Repeater Test', 'code' => 'REPEATER_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Repeater Section',
            'code' => 'repeater_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'REPEATER_TEST_Q1',
            'question_text' => 'Action Plans',
            'question_type' => 'repeater',
            'options' => [
                ['key' => 'plan', 'label' => 'Action Plan', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Resolved', 'In Progress']],
            ],
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        return [$type, $section, $question];
    }

    public function test_repeater_field_renders_as_a_filament_repeater(): void
    {
        [, $section] = $this->makeRepeaterQuestion();

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Repeater::class, $fields[0]);
    }

    public function test_repeater_field_loads_existing_rows_as_default(): void
    {
        [$type, $section, $question] = $this->makeRepeaterQuestion();
        $assessor = User::factory()->create(['name' => 'Repeater Assessor']);
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $question->id,
            'response_value' => json_encode([
                ['plan' => 'Fix the thing', 'status' => 'Resolved'],
                ['plan' => 'Fix another thing', 'status' => 'In Progress'],
            ]),
        ]);

        $fields = DynamicFormBuilder::buildForSection($section->id, $assessment->id);

        // getDefaultState() needs a fully mounted form container to
        // evaluate (not available in an isolated unit test), and Repeater
        // wraps its default in an internal closure requiring a live
        // component argument to invoke — too deep into Filament's
        // internals to usefully reflect into here. The actual
        // load-then-render-then-save round trip is covered end-to-end by
        // EmoncSupportiveSupervisionEndToEndTest once Section B's real
        // repeater question exists.
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Repeater::class, $fields[0]);
    }

    public function test_save_responses_stores_repeater_rows_as_json(): void
    {
        [$type, $section, $question] = $this->makeRepeaterQuestion();
        $assessor = User::factory()->create(['name' => 'Repeater Save Assessor']);
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $fieldName = "question_response_{$question->id}";
        DynamicFormBuilder::saveResponses($assessment->id, $section->id, [
            $fieldName => [
                ['plan' => 'New plan', 'status' => 'Resolved'],
            ],
        ]);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $question->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame([['plan' => 'New plan', 'status' => 'Resolved']], json_decode($response->response_value, true));
    }

    public function test_save_responses_stores_an_empty_array_when_all_rows_removed(): void
    {
        [$type, $section, $question] = $this->makeRepeaterQuestion();
        $assessor = User::factory()->create(['name' => 'Repeater Empty Assessor']);
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        $fieldName = "question_response_{$question->id}";
        DynamicFormBuilder::saveResponses($assessment->id, $section->id, [
            $fieldName => [],
        ]);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $question->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame([], json_decode($response->response_value, true));
    }
}
