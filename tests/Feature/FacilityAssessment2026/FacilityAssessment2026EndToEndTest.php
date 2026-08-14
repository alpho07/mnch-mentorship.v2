<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityAssessment2026EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(): User
    {
        $user = User::factory()->create(['name' => 'E2E 2026 Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_full_seeder_run_produces_a_working_assessment_with_live_nicu_gating(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $assessor = $this->makeAssessor();
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $assessor->id,
        ]);

        // Infrastructure renders.
        $infraUrl = AssessmentResource::getUrl('edit-section', ['record' => $assessment->id, 'sectionCode' => 'infrastructure']);
        $this->get($infraUrl)->assertOk();

        $hasNicu = AssessmentQuestion::where('question_code', 'INFRA_HAS_NICU')->firstOrFail();
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasNicu->id, 'response_value' => 'No',
        ]);

        // Skills Lab: Newborn Anne Manikin question requires NICU=Yes AND
        // skills-lab=Yes — with NICU=No it must be excluded from scoring
        // regardless of the skills-lab answer.
        $hasLab = AssessmentQuestion::where('question_code', 'SKILLS_HAS_LAB')->firstOrFail();
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $hasLab->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
        \App\Services\DynamicScoringService::recalculateSectionScore($assessment->id, $hasLab->assessment_section_id);
        $manikinAnne = AssessmentQuestion::where('question_code', 'SKILLS_YES_MANIKIN_ANNE')->firstOrFail();
        $this->assertNull(AssessmentQuestionResponse::where('assessment_id', $assessment->id)->where('assessment_question_id', $manikinAnne->id)->first());

        // Health Products: 'surfactant' has two rows — one unconditional,
        // attached to the NBU and Maternity departments (2 tabs), and one
        // scoped to the Skills lab tab alone, gated on HAS_NICU. Every
        // department tab renders in the same page (Filament Tabs doesn't
        // lazy-load), so the unconditional row alone accounts for 2
        // occurrences regardless of the gate; a 3rd appears once the
        // Skills-lab-scoped row also becomes visible.
        $hpUrl = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $hpResponse = $this->get($hpUrl);
        $hpResponse->assertOk();
        $this->assertSame(2, substr_count($hpResponse->getContent(), 'surfactant'));

        // Flip HAS_NICU to Yes — the Skills-lab-scoped row must now also render.
        AssessmentQuestionResponse::where('assessment_id', $assessment->id)->where('assessment_question_id', $hasNicu->id)->update(['response_value' => 'Yes']);
        $hpResponseAfter = $this->get($hpUrl);
        $hpResponseAfter->assertOk();
        $this->assertSame(3, substr_count($hpResponseAfter->getContent(), 'surfactant'));
    }
}
