<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
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
