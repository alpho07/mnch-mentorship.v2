<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportHumanResourcesComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_resources_section_renders_one_column_group_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HREND1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $cadre = Cadre::create(['name' => 'Nurse', 'code' => 'NURSE']);

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        HumanResourceResponse::create(['assessment_id' => $baseline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 5]);
        HumanResourceResponse::create(['assessment_id' => $midline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 8]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($midline);

        $this->assertStringContainsString('Nurse', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
