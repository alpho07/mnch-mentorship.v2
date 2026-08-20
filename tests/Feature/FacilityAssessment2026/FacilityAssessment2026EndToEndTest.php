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
        // attached to the NBU and Maternity departments, and one scoped to
        // the Skills lab department alone, gated on HAS_NICU. Departments
        // are one-per-page-load now (switched via ?dept=<slug>), not all
        // rendered together in one page, so each department is checked via
        // its own request instead of counting occurrences on one page.
        $hpUrl = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);

        $nbuResponse = $this->get($hpUrl.'?dept=nbu');
        $nbuResponse->assertOk();
        $this->assertSame(1, substr_count($nbuResponse->getContent(), 'surfactant'));

        $maternityResponse = $this->get($hpUrl.'?dept=maternity');
        $maternityResponse->assertOk();
        $this->assertSame(1, substr_count($maternityResponse->getContent(), 'surfactant'));

        // Skills lab: the gated row must not render while HAS_NICU is No.
        $skillsLabResponse = $this->get($hpUrl.'?dept=skills-lab');
        $skillsLabResponse->assertOk();
        $this->assertSame(0, substr_count($skillsLabResponse->getContent(), 'surfactant'));

        // Flip HAS_NICU to Yes — the Skills-lab-scoped row must now render.
        AssessmentQuestionResponse::where('assessment_id', $assessment->id)->where('assessment_question_id', $hasNicu->id)->update(['response_value' => 'Yes']);
        $skillsLabResponseAfter = $this->get($hpUrl.'?dept=skills-lab');
        $skillsLabResponseAfter->assertOk();
        $this->assertSame(1, substr_count($skillsLabResponseAfter->getContent(), 'surfactant'));
    }
}
