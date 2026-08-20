<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentReportTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_assessment_report_titles_by_its_actual_round_not_baseline(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'TITLE1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('MNCH MIDLINE ASSESSMENT', $html);
        $this->assertStringNotContainsString('MNCH BASELINE ASSESSMENT', $html);
    }

    public function test_comparison_report_uses_a_neutral_title_not_one_arbitrary_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'TITLE2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($midline);

        $this->assertStringContainsString('MNCH ASSESSMENT<', $html);
        $this->assertStringNotContainsString('MNCH BASELINE ASSESSMENT', $html);
    }
}
