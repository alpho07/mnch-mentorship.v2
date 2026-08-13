<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\EditSection;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: loadSavedResponses() decoded a repeater/checkbox
 * question's JSON-array response_value back into a PHP array before
 * handing it to $this->form->fill(), but never did the same for
 * multi_select — added alongside repeater/checkbox in the same
 * in_array() check by the same fix. Without it, a saved multi_select
 * answer (e.g. Skills Lab's "Specify the power back up type") reloaded
 * as the raw JSON string, which Filament's Select::multiple() can't
 * match against its options — so the dropdown silently showed nothing
 * selected even though the response was saved correctly in the DB.
 */
class EditSectionMultiSelectLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_multi_select_response_loads_back_as_a_decoded_array(): void
    {
        $type = AssessmentType::create(['name' => 'Multi Select Load Test', 'code' => 'MULTI_SELECT_LOAD_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Power', 'code' => 'multi_select_load_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'MULTI_SELECT_LOAD_Q1',
            'question_text' => 'Specify the power back up type', 'question_type' => 'multi_select',
            'options' => ['Generator', 'Solar', 'Other'], 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id, 'assessment_date' => now(),
            'assessor_name' => 'Test Assessor', 'status' => 'in_progress',
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $question->id,
            'response_value' => json_encode(['Generator', 'Solar']),
        ]);

        $page = new EditSection;
        $page->record = $assessment;
        $page->section = $section;

        $method = new \ReflectionMethod($page, 'loadSavedResponses');
        $method->setAccessible(true);
        $data = $method->invoke($page);

        $this->assertSame(['Generator', 'Solar'], $data["question_response_{$question->id}"]);
    }
}
