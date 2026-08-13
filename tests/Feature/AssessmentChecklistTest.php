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

        // Regression: the hint action used to render as plain gray link
        // text squeezed between the label and the Yes/No radios — easy to
        // miss. It should render as a genuine outlined button instead.
        $action = $radio->getHintActions()['checklist_'.$question->id];
        $this->assertTrue($action->isButton());
        $this->assertTrue($action->isOutlined());
        $this->assertSame('info', $action->getColor());
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

    public function test_checklist_modal_renders_title_and_qty_column_headers(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test 3', 'code' => 'CHECKLIST_TEST_3', 'is_active' => true]);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $type->id, 'title' => 'ORT Corner checklist']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Clean spoons', 'qty' => 6, 'order' => 1]);

        $html = view('filament.assessment.checklist-modal', ['checklist' => $checklist->fresh('items')])->render();

        $this->assertStringContainsString('Title', $html);
        $this->assertStringContainsString('Qty', $html);
        $this->assertStringContainsString('Clean spoons', $html);
    }

    public function test_checklist_modal_omits_qty_header_when_no_item_has_a_qty(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test 4', 'code' => 'CHECKLIST_TEST_4', 'is_active' => true]);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $type->id, 'title' => 'Triage requirements']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'label' => 'Triage desk', 'qty' => null, 'order' => 1]);

        $html = view('filament.assessment.checklist-modal', ['checklist' => $checklist->fresh('items')])->render();

        $this->assertStringContainsString('Title', $html);
        $this->assertStringNotContainsString('Qty', $html);
    }

    public function test_checklist_modal_numbers_items_restarting_per_group(): void
    {
        $type = AssessmentType::create(['name' => 'Checklist Test 5', 'code' => 'CHECKLIST_TEST_5', 'is_active' => true]);
        $checklist = AssessmentChecklist::create(['assessment_type_id' => $type->id, 'title' => 'Skills lab checklist']);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'group_label' => 'Equipment', 'label' => 'Manikin', 'order' => 1]);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'group_label' => 'Equipment', 'label' => 'Stethoscope', 'order' => 2]);
        AssessmentChecklistItem::create(['assessment_checklist_id' => $checklist->id, 'group_label' => 'Stationery', 'label' => 'Notebook', 'order' => 3]);

        $html = view('filament.assessment.checklist-modal', ['checklist' => $checklist->fresh('items')])->render();

        // Each group's table restarts its own 1., 2., ... numbering rather
        // than continuing a running count across the whole checklist.
        $this->assertMatchesRegularExpression(
            '/1\.\s*<\/td>\s*<td[^>]*>\s*Manikin.*?2\.\s*<\/td>\s*<td[^>]*>\s*Stethoscope/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/1\.\s*<\/td>\s*<td[^>]*>\s*Notebook/s',
            $html
        );
    }
}
