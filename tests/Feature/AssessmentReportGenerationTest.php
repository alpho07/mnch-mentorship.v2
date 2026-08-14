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
