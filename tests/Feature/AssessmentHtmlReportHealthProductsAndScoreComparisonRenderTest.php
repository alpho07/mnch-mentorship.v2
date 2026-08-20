<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentHtmlReportHealthProductsAndScoreComparisonRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_products_section_renders_one_column_per_round(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HPREND1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Antibiotics']);
        $commodity = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Amoxicillin']);

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonth(), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $baseline->id, 'commodity_id' => $commodity->id, 'assessment_department_id' => $department->id,
            'available' => false, 'not_applicable' => false,
        ]);
        AssessmentCommodityResponse::create([
            'assessment_id' => $midline->id, 'commodity_id' => $commodity->id, 'assessment_department_id' => $department->id,
            'available' => true, 'not_applicable' => false,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($midline);

        $this->assertStringContainsString('Amoxicillin', $html);
        $this->assertStringContainsString('Baseline', $html);
        $this->assertStringContainsString('Midline', $html);
    }
}
