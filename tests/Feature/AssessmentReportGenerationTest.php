<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use App\Models\User;
use App\Services\AssessmentExportService;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessment(User $assessor, Facility $facility): Assessment
    {
        $this->actingAs($assessor);
        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard Facility Assessment', 'version' => '1.0', 'is_active' => true]
        );

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
            'overall_percentage' => 82.5,
            'overall_grade' => 'green',
        ]);
    }

    public function test_html_report_contains_the_facility_name_and_assessor_name(): void
    {
        $facility = Facility::factory()->create(['name' => 'Kericho District Hospital']);
        $assessor = User::factory()->create(['name' => 'Jane Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Kericho District Hospital', $html);
        $this->assertStringContainsString('Jane Assessor', $html);
    }

    public function test_pdf_report_generates_a_valid_pdf_stream_without_throwing(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'PDF Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment);
        $bytes = $pdf->output();

        $this->assertStringStartsWith(
            '%PDF',
            $bytes,
            'DomPDF output should start with the standard PDF file signature — if this fails, PDF '
            . 'generation for facility assessments is broken.'
        );
    }

    /**
     * Regression: a cadre with any na_training_columns makes hrCell()
     * return the string 'N/A' for that column — both report templates
     * summed these cells directly ($etat + $compNB + ...), which throws
     * "Unsupported operand types: int + string" the moment any cadre has
     * an N/A column. Reproduced live on assessment 82 (Midwives etc. have
     * ETAT+/IMNCI/Type 1 Diabetes marked N/A) via the summary page.
     */
    public function test_html_and_pdf_reports_do_not_throw_when_a_cadre_has_na_training_columns(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'NA Cadre Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $cadre = MainCadre::create([
            'assessment_type_id' => $assessment->assessment_type_id,
            'name' => 'Midwives',
            'code' => 'midwives_report_test',
            'is_active' => true,
            'order' => 1,
            'na_training_columns' => ['etat_plus', 'imnci', 'type_1_diabetes'],
        ]);
        HumanResourceResponse::create([
            'assessment_id' => $assessment->id,
            'cadre_id' => $cadre->id,
            'total_in_facility' => 5,
            'etat_plus' => null,
            'comprehensive_newborn_care' => 3,
            'imnci' => null,
            'type_1_diabetes' => null,
            'essential_newborn_care' => 2,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);
        $this->assertStringContainsString('N/A', $html);
        $this->assertStringContainsString('Midwives', $html);

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment);
        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    /**
     * Regression: getInfrastructureDetails()/getSkillsLabDetails()/
     * getInformationSystemsDetails()/getQualityOfCareDetails() looked up
     * their section by code alone (AssessmentSection::where('code', ...)
     * ->value('id')) with no assessment_type_id scoping. Section codes are
     * deliberately reused across templates (2025 vs 2026 both have an
     * 'infrastructure' section, by design — see AssessmentTypeScopingTest)
     * so this silently grabbed whichever template's section id sorts
     * first, not the assessment's own — Infrastructure and Skills Lab
     * both rendered as empty on assessment 82's real summary page even
     * though 23 and 22 responses respectively were actually saved.
     */
    public function test_infrastructure_details_use_the_assessments_own_template_section_not_a_same_coded_one_from_another_template(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'Scoped Section Assessor']);

        $otherType = AssessmentType::create(['name' => 'Other Template', 'code' => 'OTHER_TEMPLATE_REPORT_TEST', 'is_active' => true]);
        $otherSection = AssessmentSection::create([
            'assessment_type_id' => $otherType->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $otherSection->id, 'question_code' => 'OTHER_TEMPLATE_INFRA_Q',
            'question_text' => 'Question from the other template', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $type = AssessmentType::create(['name' => 'Real Template', 'code' => 'REAL_TEMPLATE_REPORT_TEST', 'is_active' => true]);
        $section = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'REAL_TEMPLATE_INFRA_Q',
            'question_text' => 'Do you have a newborn unit?', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($assessor);
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'status' => 'completed',
        ]);
        AssessmentQuestionResponse::create([
            'assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => 'Yes',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Do you have a newborn unit?', $html);
        $this->assertStringNotContainsString('Question from the other template', $html);
    }

    public function test_indicators_section_renders_split_by_newborn_and_paediatric_group(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'Indicators Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $section = AssessmentSection::create([
            'assessment_type_id' => $assessment->assessment_type_id, 'name' => 'Newborn & Paediatric Indicators', 'code' => 'newborn_paediatric_indicators',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $newbornQ = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_TEST', 'question_text' => 'Total newborn admissions',
            'question_type' => 'number', 'group' => 'Newborn Indicators', 'order' => 1, 'is_active' => true,
        ]);
        $paedQ = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_PAED_TEST', 'question_text' => 'Total paediatric admissions',
            'question_type' => 'number', 'group' => 'Paediatric Indicators', 'order' => 2, 'is_active' => true,
        ]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $newbornQ->id, 'response_value' => '12']);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $paedQ->id, 'response_value' => '7']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Newborn & Paediatric Indicators', $html);
        $this->assertStringContainsString('Total newborn admissions', $html);
        $this->assertStringContainsString('Total paediatric admissions', $html);

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment);
        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    /**
     * The source spreadsheet's "REPORTING PROPORTIONAL NEWBORN INDICATORS"
     * table computes rates from the raw counts already captured, rather
     * than asking a separate question — verifies both a computable pair
     * (both counts answered) and an uncomputable one (denominator missing)
     * resolve correctly, using the real IND_NEWBORN_* question codes
     * AssessmentPdfReportService::NEWBORN_PROPORTIONS references.
     */
    public function test_newborn_proportions_are_computed_from_the_raw_indicator_counts(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'Proportions Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $section = AssessmentSection::create([
            'assessment_type_id' => $assessment->assessment_type_id, 'name' => 'Newborn & Paediatric Indicators', 'code' => 'newborn_paediatric_indicators',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $admissions = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_ADMISSIONS', 'question_text' => 'Total newborn admissions',
            'question_type' => 'number', 'group' => 'Newborn Indicators', 'order' => 1, 'is_active' => true,
        ]);
        $birthAsphyxia = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_BIRTH_ASPHYXIA', 'question_text' => 'Newborns diagnosed with birth asphyxia',
            'question_type' => 'number', 'group' => 'Newborn Indicators', 'order' => 2, 'is_active' => true,
        ]);
        // IND_NEWBORN_HEADTOTOE (the numerator for the "temperature
        // taken" proportion) is deliberately left unanswered here.
        AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_HEADTOTOE', 'question_text' => 'Newborns with head to toe exam',
            'question_type' => 'number', 'group' => 'Newborn Indicators', 'order' => 3, 'is_active' => true,
        ]);
        $hypothermia = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'IND_NEWBORN_HYPOTHERMIA', 'question_text' => 'Newborns with hypothermia',
            'question_type' => 'number', 'group' => 'Newborn Indicators', 'order' => 4, 'is_active' => true,
        ]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $admissions->id, 'response_value' => '200']);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $birthAsphyxia->id, 'response_value' => '50']);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $hypothermia->id, 'response_value' => '30']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Newborn Proportions', $html);
        // Admissions itself leads the table as a plain count, not a percentage.
        $this->assertMatchesRegularExpression(
            '/Total number of newborn admissions for the last complete month<\/td>\s*<td[^>]*>\s*200\s*<\/td>/',
            $html
        );
        // 50/200 = 25.0%
        $this->assertMatchesRegularExpression(
            '/diagnosis of birth asphyxia<\/td>\s*<td[^>]*>\s*25\.0% \(50\/200\)\s*<\/td>/',
            $html
        );
        // Hypothermia's denominator is admissions (30/200 = 15.0%), not the
        // head-to-toe exam count.
        $this->assertMatchesRegularExpression(
            '/hypothermia at admission \(temp &lt;36\.5\)<\/td>\s*<td[^>]*>\s*15\.0% \(30\/200\)\s*<\/td>/',
            $html
        );
        // Temperature-taken's numerator (head-to-toe exam) was never answered.
        $this->assertMatchesRegularExpression(
            '/temperature taken at admission<\/td>\s*<td[^>]*>\s*N\/A\s*<\/td>/',
            $html
        );

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment);
        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    /**
     * Regression: a bed-count response (question_type 'number', e.g. "No.
     * Functional" = "2") went through the same green/red Yes/No badge
     * logic every other Infrastructure response does, showing a plain
     * positive count in a misleading red "bad answer" badge. Also, every
     * bed-pair sub-question shares the exact same text ("No. Functional")
     * across every unit (NBU, KMC, NICU, PICU), so the report needs the
     * question's group prefixed to tell them apart outside the live
     * form's table structure.
     */
    public function test_bed_count_responses_show_the_plain_number_with_unit_context_not_a_yes_no_badge(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'Bed Count Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $section = AssessmentSection::create([
            'assessment_type_id' => $assessment->assessment_type_id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 1, 'is_active' => true,
        ]);
        $question = AssessmentQuestion::create([
            'assessment_section_id' => $section->id, 'question_code' => 'INFRA_NBU_GENERAL_FUNCTIONAL_TEST',
            'question_text' => 'No. Functional', 'question_type' => 'number', 'group' => 'General NBU beds',
            'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestionResponse::create(['assessment_id' => $assessment->id, 'assessment_question_id' => $question->id, 'response_value' => '2']);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('General NBU beds — No. Functional', $html);
        // The whole row — label cell through to the plain "2", with no
        // badge span in between (unlike every Yes/No row in the same table).
        $this->assertMatchesRegularExpression(
            '/General NBU beds — No\. Functional<\/td>\s*<td[^>]*>\s*2\s*<\/td>/',
            $html
        );
    }

    public function test_csv_export_contains_the_expected_section_headers(): void
    {
        $facility = Facility::factory()->create(['name' => 'Nakuru Level 4 Hospital']);
        $assessor = User::factory()->create(['name' => 'CSV Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $csv = app(AssessmentExportService::class)->exportAssessmentToCSV($assessment);

        $this->assertStringContainsString('Nakuru Level 4 Hospital', $csv);
        $this->assertStringContainsString('INFRASTRUCTURE SECTION', $csv);
        $this->assertStringContainsString('SKILLS LAB SECTION', $csv);
        $this->assertStringContainsString('HUMAN RESOURCES SECTION', $csv);
        $this->assertStringContainsString('HEALTH PRODUCTS SECTION', $csv);
    }
}
