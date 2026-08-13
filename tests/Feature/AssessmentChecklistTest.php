<?php

namespace Tests\Feature;

use App\Models\AssessmentChecklist;
use App\Models\AssessmentChecklistItem;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_with_a_checklist_gets_a_hint_action(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test', 'code' => 'CHECKLIST_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_cl',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $type->id, 'title' => 'ORT Corner checklist']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Clean spoons', 'qty' => 6, 'order' => 1]);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Plastic buckets', 'qty' => 3, 'order' => 2]);

        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'ORT_OUTPATIENT',
            'question_text' => 'Is there a functional ORT corner in the outpatient department?',
            'question_type' => 'yes_no',
            'checklist_id' => $checklist->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $field = QuestionFieldBuilder::buildField($question->fresh(), null);
        $radio = $field->getChildComponents()[0];

        $this->assertCount(1, $radio->getHintActions());
        $this->assertArrayHasKey('checklist_'.$question->id, $radio->getHintActions());
    }

    public function test_a_question_without_a_checklist_has_no_hint_action(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test 2', 'code' => 'CHECKLIST_TEST_2', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_cl2',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id,
            'question_code' => 'NO_CHECKLIST_Q',
            'question_text' => 'Plain question',
            'question_type' => 'yes_no',
            'order' => 1,
            'is_active' => true,
        ]);

        $field = QuestionFieldBuilder::buildField($question->fresh(), null);
        $radio = $field->getChildComponents()[0];

        $this->assertCount(0, $radio->getHintActions());
    }
}
