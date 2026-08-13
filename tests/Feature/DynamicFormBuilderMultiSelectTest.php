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
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderMultiSelectTest extends TestCase
{
    use RefreshDatabase;

    private function makeMultiSelectQuestion(): array
    {
        $type = AssessmentType::create(['name' => 'Multi Select Test', 'code' => 'MULTI_SELECT_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Multi Select Section',
            'code' => 'multi_select_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'MULTI_SELECT_TEST_Q1',
            'question_text' => 'Is there a power back up system? If yes Specify',
            'question_type' => 'multi_select',
            'options' => ['Generator', 'Solar', 'Other'],
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        return [$type, $section, $question];
    }

    private function makeAssessment(AssessmentType $type): Assessment
    {
        $assessor = User::factory()->create(['name' => 'Multi Select Assessor']);
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);
    }

    public function test_multi_select_field_renders_as_a_multiple_filament_select(): void
    {
        [, $section] = $this->makeMultiSelectQuestion();

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Select::class, $fields[0]);
        $this->assertTrue($fields[0]->isMultiple());
        $this->assertSame(
            ['Generator' => 'Generator', 'Solar' => 'Solar', 'Other' => 'Other'],
            $fields[0]->getOptions()
        );
    }

    public function test_save_responses_stores_selected_options_as_json(): void
    {
        [$type, $section, $question] = $this->makeMultiSelectQuestion();
        $assessment = $this->makeAssessment($type);

        $fieldName = "question_response_{$question->id}";
        DynamicFormBuilder::saveResponses($assessment->id, $section->id, [
            $fieldName => ['Generator', 'Solar'],
        ]);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $question->id)
            ->first();

        $this->assertNotNull($response);
        $this->assertSame(['Generator', 'Solar'], json_decode($response->response_value, true));
        $this->assertNull($response->score);
    }

    public function test_save_responses_stores_an_empty_array_when_nothing_selected(): void
    {
        [$type, $section, $question] = $this->makeMultiSelectQuestion();
        $assessment = $this->makeAssessment($type);

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

    public function test_previously_saved_selection_loads_back_as_the_field_default(): void
    {
        [$type, $section, $question] = $this->makeMultiSelectQuestion();
        $assessment = $this->makeAssessment($type);

        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $question->id,
            'response_value' => json_encode(['Generator', 'Other']),
        ]);

        $fields = DynamicFormBuilder::buildForSection($section->id, $assessment->id);

        $this->assertSame(['Generator', 'Other'], $fields[0]->getDefaultState());
    }
}
