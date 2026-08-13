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

class AssessmentGroupVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Group Visibility Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    private function makeGatedGroup(): array
    {
        $type = AssessmentType::create(['name' => 'Group Visibility Test', 'code' => 'GROUP_VISIBILITY_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure_gv',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $gate = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'GATE_NBU',
            'question_text' => 'Do you have a newborn unit', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        $condition = ['question_code' => 'GATE_NBU', 'operator' => 'equals', 'value' => 'Yes'];
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'NBU_FUNCTIONAL',
            'question_text' => 'No. Functional', 'question_type' => 'number',
            'group' => 'General NBU beds', 'display_conditions' => $condition, 'order' => 2, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'NBU_NONFUNCTIONAL',
            'question_text' => 'No. Non-Functional', 'question_type' => 'number',
            'group' => 'General NBU beds', 'display_conditions' => $condition, 'order' => 3, 'is_active' => true,
        ]);

        return [$type, $section, $gate];
    }

    public function test_group_legend_is_hidden_until_the_shared_gating_question_is_answered_yes(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section] = $this->makeGatedGroup();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDontSee('General NBU beds');
        $response->assertDontSee('No. Functional');
        $response->assertDontSee('No. Non-Functional');
    }

    public function test_group_legend_appears_once_the_shared_gating_question_is_yes(): void
    {
        $assessor = $this->makeAssessor();
        [$type, $section, $gate] = $this->makeGatedGroup();
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $gate->id, 'response_value' => 'Yes',
        ]);

        $url = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => $section->code]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('General NBU beds');
        $response->assertSee('No. Functional');
        $response->assertSee('No. Non-Functional');
    }
}
