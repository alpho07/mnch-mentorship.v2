<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentReportHeaderAndDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_name_appears_once_not_duplicated_in_the_header(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HDR1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create(['name' => 'Unique Header Test Clinic']);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertSame(1, substr_count($html, 'Unique Header Test Clinic'));
    }

    public function test_single_assessment_shows_one_assessment_date_row_under_facility_information(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HDR2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => \Carbon\Carbon::parse('2026-03-15'), 'assessor_name' => 'Test Assessor',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Assessment Date:', $html);
        $this->assertStringContainsString('March 15, 2026', $html);
    }

    public function test_comparison_lists_one_dated_row_per_round_under_facility_information(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HDR3', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => \Carbon\Carbon::parse('2026-01-10'), 'assessor_name' => 'Test Assessor',
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => \Carbon\Carbon::parse('2026-06-20'), 'assessor_name' => 'Test Assessor',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertStringContainsString('Baseline Assessment:', $html);
        $this->assertStringContainsString('January 10, 2026', $html);
        $this->assertStringContainsString('Midline Assessment:', $html);
        $this->assertStringContainsString('June 20, 2026', $html);
    }
}
