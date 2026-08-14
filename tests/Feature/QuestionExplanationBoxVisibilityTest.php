<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Selecting "No" on a yes_no question must never surface the comments box,
 * regardless of what requires_explanation_on is configured to on the
 * question — only other triggering values (e.g. "Partially") still show it.
 */
class QuestionExplanationBoxVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Explanation Box Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    private function placeholderText(): string
    {
        return 'Please provide details, recommendations, or action plans...';
    }

    public function test_comments_box_does_not_render_when_no_is_selected_even_with_default_gate(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Explanation Box Test', 'code' => 'EXPLANATION_BOX_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'EXPLAIN_Q', 'question_text' => 'Is the item present?',
            'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
            // No requires_explanation_on override — falls back to the
            // kernel default of ['No', 'Partially'].
        ]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'No',
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDontSee($this->placeholderText());
    }

    public function test_comments_box_does_not_render_when_no_is_selected_with_explicit_no_only_gate(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Explanation Box No Gate Test', 'code' => 'EXPLANATION_BOX_NO_GATE_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Information Systems', 'code' => 'information_systems',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'EXPLAIN_NO_GATE_Q', 'question_text' => 'Is data uploaded to KHIS?',
            'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
            'requires_explanation_on' => ['No'],
        ]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'No',
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDontSee($this->placeholderText());
    }

    public function test_comments_box_still_renders_when_partially_is_selected(): void
    {
        $assessor = $this->makeAssessor();
        $type = AssessmentType::create(['name' => 'Explanation Box Partial Test', 'code' => 'EXPLANATION_BOX_PARTIAL_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'EXPLAIN_PARTIAL_Q', 'question_text' => 'Is the item present?',
            'question_type' => 'yes_no_partial', 'order' => 1, 'is_active' => true,
        ]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'Partially',
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee($this->placeholderText());
    }
}
