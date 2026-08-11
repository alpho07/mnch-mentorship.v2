<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\DynamicFormBuilder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicFormBuilderShortTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_text_renders_as_a_single_line_text_input_not_a_textarea(): void
    {
        $type = AssessmentType::create(['name' => 'Short Text Test', 'code' => 'SHORT_TEXT_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Short Text Section',
            'code' => 'short_text_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'SHORT_TEXT_Q1',
            'question_text' => 'Name',
            'question_type' => 'short_text',
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        $fields = DynamicFormBuilder::buildForSection($section->id);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(TextInput::class, $fields[0]);
        $this->assertNotInstanceOf(Textarea::class, $fields[0]);
    }

    public function test_short_text_loads_and_saves_its_response_value_like_a_normal_text_field(): void
    {
        $type = AssessmentType::create(['name' => 'Short Text Save Test', 'code' => 'SHORT_TEXT_SAVE_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Short Text Section',
            'code' => 'short_text_save_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'SHORT_TEXT_SAVE_Q1',
            'question_text' => 'Contact',
            'question_type' => 'short_text',
            'is_scored' => false,
            'order' => 1,
            'is_active' => true,
        ]);

        $assessor = \App\Models\User::factory()->create(['name' => 'Short Text Assessor']);
        $this->actingAs($assessor);
        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $assessor->id,
        ]);

        DynamicFormBuilder::saveResponses($assessment->id, $section->id, [
            "question_response_{$question->id}" => '0712345678',
        ]);

        $response = AssessmentQuestionResponse::where('assessment_id', $assessment->id)
            ->where('assessment_question_id', $question->id)
            ->first();

        $this->assertSame('0712345678', $response->response_value);
    }
}
