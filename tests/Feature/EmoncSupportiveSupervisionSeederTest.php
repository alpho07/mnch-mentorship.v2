<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\Cadre;
use Database\Seeders\EmoncSupportiveSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmoncSupportiveSupervisionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_categories_and_the_assessment_type(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $this->assertTrue(AssessmentTypeCategory::where('name', 'EmONC')->exists());
        $this->assertTrue(AssessmentTypeCategory::where('name', 'Newborn, Infant & Child')->exists());

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $this->assertNotNull($type);
        $this->assertSame('EmONC', $type->category->name);
        $this->assertTrue($type->is_active);
    }

    public function test_section_a_is_seeded_with_25_unscored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();
        $section = $type->sections()->where('code', 'emonc_facility_context')->first();

        $this->assertNotNull($section);
        $this->assertFalse($section->is_scored);
        $this->assertSame(25, $section->questions()->count());
        $this->assertSame(0, $section->questions()->where('is_scored', true)->count());

        // Supervisors are NOT free-text fields here — they're the
        // assessment's real team_lead/member records (assessment_team
        // pivot), managed via CreateAssessment's team-invite step and
        // ListAssessments' "Manage Team" action, same as every other
        // assessment template.
        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_A_SUP%')->exists());

        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->exists());
        $facilityCategory = AssessmentQuestion::where('question_code', 'EMONC_A_FACILITY_CATEGORY')->first();
        $this->assertSame(['CEMONC', 'BEMONC'], $facilityCategory->options);

        // Person In Charge and Respondent render as small table-style
        // groups (see DynamicFormBuilderGroupingTest for the
        // <=4-field-groups-lay-out-side-by-side behavior itself).
        $this->assertSame(2, $section->questions()->where('group', 'Person In Charge of the Facility')->count());
        $this->assertSame(3, $section->questions()->where('group', 'Facility Supervision Respondent')->count());
        $this->assertSame('cadre_select', AssessmentQuestion::where('question_code', 'EMONC_A_RESPONDENT_CADRE')->value('question_type'));

        // Human Resources rows are NOT statically seeded questions — the
        // seeder seeds the 4 EmONC-category Cadre records and calls
        // CadreMatrixSyncService to materialize their questions (see
        // CadreMatrixSyncServiceTest for the sync mechanics). group encodes
        // DynamicFormBuilder's table-row convention
        // ("{title}|{rowLabelHeader}|{rowLabel}") so all 4 cadres render as
        // rows in one shared-header table instead of 4 separate boxes.
        $this->assertSame(4, Cadre::category('emonc')->active()->count());
        $this->assertSame(3, $section->questions()->where('group', 'Human Resources in Maternity Unit|Cadre|Nurses')->count());
        $this->assertSame(3, $section->questions()->where('group', 'Human Resources in Maternity Unit|Cadre|Obstetricians')->count());
        $this->assertSame(12, AssessmentQuestion::where('question_code', 'like', 'EMONC_A_HR_CADRE%')->count());
    }

    public function test_section_b_is_seeded_with_2_questions_one_scored(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_feedback')->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_scored);
        $this->assertSame(2, $section->questions()->count());
        $this->assertSame(1, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_B_FEEDBACK_MEETING_DONE')->where('is_scored', true)->exists());

        $actionPlans = AssessmentQuestion::where('question_code', 'EMONC_B_ACTION_PLANS')->first();
        $this->assertNotNull($actionPlans);
        $this->assertSame('repeater', $actionPlans->question_type);
        $this->assertSame(['plan', 'status', 'remarks'], array_column($actionPlans->options, 'key'));
        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_B_AP%')->exists());
    }

    public function test_section_c_is_seeded_with_2_scored_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_capacity_building')->first();

        $this->assertNotNull($section);
        $this->assertSame(2, $section->questions()->count());
        $this->assertSame(2, $section->questions()->where('is_scored', true)->count());
        $cmes = AssessmentQuestion::where('question_code', 'EMONC_C_CMES')->first();
        $this->assertSame('Confirm using the CME register/booklet', $cmes->help_text);
    }

    public function test_section_d_is_seeded_with_27_scored_commodity_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_key_commodities')->first();

        $this->assertNotNull($section);
        $this->assertSame(27, $section->questions()->count());
        $this->assertSame(27, $section->questions()->where('is_scored', true)->count());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_D_1')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_D_27')->exists());
    }

    public function test_section_e_kit_1_obstetric_hemorrhage_is_fully_seeded(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();
        $this->assertNotNull($section);

        $kit1Questions = $section->questions()->where('group', '1. Obstetric Hemorrhage Kit')->get();
        // 1 parent + 14 sub-items + 1 completeness = 16
        $this->assertCount(16, $kit1Questions);

        $completeness = AssessmentQuestion::where('question_code', 'EMONC_E_K1_COMPLETE')->first();
        $this->assertNotNull($completeness);
        $this->assertSame('group_completeness', $completeness->question_type);
        $this->assertSame('1. Obstetric Hemorrhage Kit', $completeness->group);

        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_K1_PARENT')->exists());
        $this->assertSame(14, AssessmentQuestion::where('question_code', 'like', 'EMONC_E_K1_%')->where('question_type', 'yes_no')->where('question_code', '!=', 'EMONC_E_K1_PARENT')->count());
    }

    public function test_section_e_kits_2_and_3_are_seeded(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();

        $this->assertCount(20, $section->questions()->where('group', '2. Neonatal Resuscitation Kit')->get()); // 1 + 18 + 1
        $this->assertCount(20, $section->questions()->where('group', '3. PET/Eclampsia Kit')->get()); // 1 + 18 + 1
    }

    public function test_section_e_is_fully_seeded_with_106_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_emergency_kits')->first();

        $this->assertSame(106, $section->questions()->count());
        $this->assertSame(6, $section->questions()->where('question_type', 'group_completeness')->count());

        $this->assertCount(16, $section->questions()->where('group', '4. Maternal Resuscitation Kit')->get()); // 1 + 14 + 1
        $this->assertCount(13, $section->questions()->where('group', '5. Delivery Kit')->get()); // 1 + 11 + 1
        $this->assertCount(9, $section->questions()->where('group', '6. Assisted Vacuum Delivery Kit (AVD/Kiwi kit)')->get()); // 1 + 7 + 1

        $sopQuestions = $section->questions()->where('group', 'SOPs / Job Aids')->get();
        $this->assertCount(12, $sopQuestions);
        $this->assertTrue($sopQuestions->every(fn ($q) => $q->question_type === 'yes_no'));
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_SOP_1')->exists());
        $this->assertTrue(AssessmentQuestion::where('question_code', 'EMONC_E_SOP_12')->exists());
    }

    public function test_section_f_is_seeded_with_5_questions_4_scored(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_referrals')->first();

        $this->assertNotNull($section);
        $this->assertSame(5, $section->questions()->count());
        $this->assertSame(4, $section->questions()->where('is_scored', true)->count());
        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_F_REF_%')->exists());

        $monthly = AssessmentQuestion::where('question_code', 'EMONC_F_MONTHLY_REFERRALS')->first();
        $this->assertNotNull($monthly);
        $this->assertSame('repeater', $monthly->question_type);
        $this->assertSame(['month', 'referrals_out'], array_column($monthly->options, 'key'));
        $this->assertContains('Jan 2025', $monthly->options[0]['options']);
    }

    public function test_section_g_is_seeded_with_6_scored_ipc_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_ipc')->first();

        $this->assertNotNull($section);
        $this->assertSame(6, $section->questions()->count());
        $this->assertSame(6, $section->questions()->where('is_scored', true)->count());
    }

    public function test_section_h_is_seeded_with_2_unscored_repeater_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_gaps_success')->first();

        $this->assertNotNull($section);
        $this->assertFalse($section->is_scored);
        $this->assertSame(2, $section->questions()->count());
        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_H_GAP1%')->exists());
        $this->assertFalse(AssessmentQuestion::where('question_code', 'like', 'EMONC_H_SUCCESS1%')->exists());

        $gaps = AssessmentQuestion::where('question_code', 'EMONC_H_GAPS')->first();
        $this->assertNotNull($gaps);
        $this->assertSame('repeater', $gaps->question_type);
        $this->assertSame(['gap', 'action', 'who', 'when'], array_column($gaps->options, 'key'));

        $success = AssessmentQuestion::where('question_code', 'EMONC_H_SUCCESS_STORIES')->first();
        $this->assertNotNull($success);
        $this->assertSame('repeater', $success->question_type);
        $this->assertSame(['what', 'how', 'impact'], array_column($success->options, 'key'));
    }

    public function test_section_j_is_seeded_with_1_optional_question(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $section = AssessmentSection::where('code', 'emonc_notes')->first();

        $this->assertNotNull($section);
        $this->assertSame(1, $section->questions()->count());
        $question = $section->questions()->first();
        $this->assertFalse($question->is_required);
    }

    public function test_full_seeder_produces_9_sections_and_176_questions(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);

        $type = AssessmentType::where('code', 'EMONC_SUPPORTIVE_SUPERVISION')->first();

        $this->assertSame(9, $type->sections()->count());
        $this->assertSame(176, AssessmentQuestion::whereIn('assessment_section_id', $type->sections()->pluck('id'))->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countBefore = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->seed(EmoncSupportiveSupervisionSeeder::class);
        $countAfter = AssessmentQuestion::where('question_code', 'like', 'EMONC_%')->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
