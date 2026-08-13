<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\ChecklistsSeeder;
use Database\Seeders\FacilityAssessment2026\InfrastructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'Infra Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'is_scored' => true, 'order' => 2, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_23_questions(): void
    {
        $type = $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'infrastructure')->first();
        // 13 gating/plain yes_no (4 unit-gates + 9 plain) + 10 bed-capacity
        // number questions (NBU General x2 + NBU KMC x2 + Paed General x2 +
        // NICU x2 + PICU x2 — NBU is the only unit with two bed-count
        // groups, General and KMC).
        $this->assertSame(23, $section->questions()->count());
    }

    public function test_bed_capacity_questions_are_conditionally_gated_on_their_own_unit(): void
    {
        $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $nbuBeds = AssessmentQuestion::where('question_code', 'INFRA_NBU_GENERAL_FUNCTIONAL')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_NBU', 'operator' => 'equals', 'value' => 'Yes'], $nbuBeds->display_conditions);
        $this->assertSame(1, $nbuBeds->indent_level);
        $this->assertSame('number', $nbuBeds->question_type);

        $picuBeds = AssessmentQuestion::where('question_code', 'INFRA_PICU_FUNCTIONAL')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'], $picuBeds->display_conditions);
    }

    public function test_ort_questions_link_the_ort_corner_checklist(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);
        $this->seed(InfrastructureSeeder::class);

        $outpatient = AssessmentQuestion::where('question_code', 'INFRA_ORT_OUTPATIENT')->firstOrFail();
        $inpatient = AssessmentQuestion::where('question_code', 'INFRA_ORT_INPATIENT')->firstOrFail();

        $this->assertNotNull($outpatient->checklist_id);
        $this->assertSame($outpatient->checklist_id, $inpatient->checklist_id);
        $this->assertSame('ORT Corner checklist', $outpatient->checklist->title);
    }

    public function test_triage_question_links_the_triage_checklist(): void
    {
        $this->makeType();
        $this->seed(ChecklistsSeeder::class);
        $this->seed(InfrastructureSeeder::class);

        $triage = AssessmentQuestion::where('question_code', 'INFRA_TRIAGE')->firstOrFail();
        $this->assertSame('Triage requirements', $triage->checklist->title);
    }

    public function test_no_questions_require_explanation_except_on_no(): void
    {
        $this->makeType();
        $this->seed(InfrastructureSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFRA_SEPARATE_NBU_PAED')->firstOrFail();
        $this->assertSame(['No'], $q->requires_explanation_on);
    }
}
