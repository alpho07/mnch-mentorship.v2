<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression test for a bug found live on assessment 84's emonc_feedback/
 * emonc_referrals/emonc_gaps_success sections: loadSavedResponses() handed
 * a repeater question's raw JSON-string response_value straight to
 * $this->form->fill(), and Filament's Repeater crashed trying to foreach()
 * over a string instead of the decoded array. Pre-existing bug (unrelated
 * to the FormKernel extraction, which copied buildRepeaterField verbatim)
 * — it only surfaced once real repeater-type EmONC data existed to load.
 */
class EditSectionRepeaterLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_section_loads_without_error_when_a_repeater_question_has_saved_rows(): void
    {
        $user = User::factory()->create(['name' => 'Test Assessor']);
        foreach (['view_any_assessment', 'update_assessment'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_assessment', 'update_assessment']);
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'Repeater Load Test', 'code' => 'REPEATER_LOAD_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Gaps', 'code' => 'repeater_load_section',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => false, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'REPEATER_LOAD_Q1', 'question_text' => 'Gaps',
            'question_type' => 'repeater', 'is_scored' => false, 'order' => 1, 'is_active' => true,
            'options' => [['key' => 'gap', 'label' => 'Gap', 'type' => 'text']],
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id, 'assessment_date' => now(),
            'assessor_name' => $user->name, 'status' => 'in_progress',
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id,
            'assessment_question_id' => $question->id,
            'response_value' => json_encode([['gap' => 'Not enough beds']]),
        ]);

        $response = $this->get("/admin/assessments/{$assessment->id}/section/{$section->code}");

        $response->assertOk();
    }
}
