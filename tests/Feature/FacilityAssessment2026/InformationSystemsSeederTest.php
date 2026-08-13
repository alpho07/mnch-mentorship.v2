<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\InformationSystemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationSystemsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'InfoSys Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_seeds_65_questions(): void
    {
        $type = $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'information_systems')->first();
        // 2 (doc type + paper-based availability) + 48 (24 MoH forms,
        // rows 241-264 of the source spreadsheet, x Available/Complete)
        // + 3 (KHIS upload, KHIS responsible, uses-EMR gate) + 7 (5 EMR
        // reports + EMR access + EMR KHIS upload) + 5 (attendance register,
        // assessment records, feedback mechanism, mentorship data entry,
        // internet) = 65.
        $this->assertSame(65, $section->questions()->count());
    }

    public function test_moh_form_pair_shares_a_table_group(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $available = AssessmentQuestion::where('question_code', 'MOH_204A_AVAILABLE')->firstOrFail();
        $complete = AssessmentQuestion::where('question_code', 'MOH_204A_COMPLETE')->firstOrFail();

        $this->assertSame($available->group, $complete->group);
        $this->assertCount(3, explode('|', $available->group));
    }

    public function test_emr_report_questions_are_gated_on_uses_emr(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_REPORT_711')->firstOrFail();
        $this->assertSame(['question_code' => 'INFOSYS_USES_EMR', 'operator' => 'equals', 'value' => 'Yes'], $q->display_conditions);
    }

    public function test_attendance_register_help_text_notes_it_is_new_for_2026(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_ATTENDANCE_REGISTER')->firstOrFail();
        $this->assertStringContainsString('Does Not appear in baseline', $q->help_text);
    }
}
