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

    public function test_seeds_66_questions(): void
    {
        $type = $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $section = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'information_systems')->first();
        // 3 (doc type, "how is data managed", paper-based availability)
        // + 48 (24 MoH forms, rows 241-264 of the source spreadsheet, x
        // Available/Completeness) + 3 (KHIS upload, KHIS responsible,
        // uses-EMR gate) + 7 (5 EMR reports + EMR access + EMR KHIS
        // upload) + 5 (attendance register, assessment records, feedback
        // mechanism, mentorship data entry, internet) = 66. Attendance
        // register and assessment records are seeded but deactivated.
        $this->assertSame(66, $section->questions()->count());
    }

    public function test_doc_type_is_a_multi_select(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_DOC_TYPE')->firstOrFail();
        $this->assertSame('multi_select', $q->question_type);
        $this->assertSame(['Paper based', 'EMR', 'Hybrid'], $q->options);
    }

    public function test_data_management_question_only_shows_when_emr_is_selected(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_DATA_MGMT')->firstOrFail();
        $this->assertSame('text', $q->question_type);
        $this->assertSame(
            ['question_code' => 'INFOSYS_DOC_TYPE', 'operator' => 'intersects', 'value' => ['EMR']],
            $q->display_conditions
        );
    }

    public function test_data_collection_tools_gate_uses_intersects_for_the_multi_select_answer(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $expected = ['question_code' => 'INFOSYS_DOC_TYPE', 'operator' => 'intersects', 'value' => ['Paper based', 'Hybrid']];

        $available = AssessmentQuestion::where('question_code', 'MOH_204A_AVAILABLE')->firstOrFail();
        $this->assertSame($expected, $available->display_conditions);
    }

    public function test_moh_form_pair_shares_a_table_group(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $available = AssessmentQuestion::where('question_code', 'MOH_204A_AVAILABLE')->firstOrFail();
        $complete = AssessmentQuestion::where('question_code', 'MOH_204A_COMPLETE')->firstOrFail();

        $this->assertSame($available->group, $complete->group);
        $this->assertCount(3, explode('|', $available->group));
        $this->assertSame('Completeness', $complete->question_text);
    }

    public function test_emr_report_questions_are_gated_on_doc_type_and_indented(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $expected = ['question_code' => 'INFOSYS_DOC_TYPE', 'operator' => 'intersects', 'value' => ['EMR']];

        $q = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_REPORT_711')->firstOrFail();
        $this->assertSame($expected, $q->display_conditions);
        $this->assertSame(1, $q->indent_level);

        $access = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_ACCESS')->firstOrFail();
        $this->assertSame($expected, $access->display_conditions);
        $this->assertSame(1, $access->indent_level);

        $khisUpload = AssessmentQuestion::where('question_code', 'INFOSYS_EMR_KHIS_UPLOAD')->firstOrFail();
        $this->assertSame($expected, $khisUpload->display_conditions);
    }

    public function test_attendance_register_assessment_records_paper_avail_complete_and_uses_emr_are_deactivated(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        foreach (['INFOSYS_ATTENDANCE_REGISTER', 'INFOSYS_ASSESSMENT_RECORDS', 'INFOSYS_PAPER_AVAIL_COMPLETE', 'INFOSYS_USES_EMR'] as $code) {
            $q = AssessmentQuestion::where('question_code', $code)->firstOrFail();
            $this->assertFalse($q->is_active);
        }
    }

    public function test_top_level_questions_are_numbered_but_moh_form_rows_are_not(): void
    {
        $this->makeType();
        $this->seed(InformationSystemsSeeder::class);

        $this->assertSame('1. What type of documentation is the facility using', AssessmentQuestion::where('question_code', 'INFOSYS_DOC_TYPE')->value('question_text'));
        $this->assertSame('2. How is data managed?', AssessmentQuestion::where('question_code', 'INFOSYS_EMR_DATA_MGMT')->value('question_text'));
        $this->assertSame('Available', AssessmentQuestion::where('question_code', 'MOH_204A_AVAILABLE')->value('question_text'));
    }
}
