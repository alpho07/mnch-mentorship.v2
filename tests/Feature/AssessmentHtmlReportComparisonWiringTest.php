<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AssessmentHtmlReportComparisonWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_report_passes_null_comparison_for_a_single_assessment(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'WIRE1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $shared = null;
        View::composer('reports.assessment-html-report', function ($view) use (&$shared) {
            $shared = $view->getData();
        });

        app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertArrayHasKey('comparison', $shared);
        $this->assertNull($shared['comparison']);
    }

    public function test_html_report_passes_comparison_array_for_two_assessments(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'WIRE2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $shared = null;
        View::composer('reports.assessment-html-report', function ($view) use (&$shared) {
            $shared = $view->getData();
        });

        app(AssessmentPdfReportService::class)->generateHtmlReport($baseline);

        $this->assertArrayHasKey('comparison', $shared);
        $this->assertIsArray($shared['comparison']);
        $this->assertCount(2, $shared['comparison']['rounds']);
    }
}
