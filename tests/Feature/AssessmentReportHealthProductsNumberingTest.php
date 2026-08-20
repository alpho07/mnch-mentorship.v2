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

class AssessmentReportHealthProductsNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_products_items_are_numbered_sequentially_within_a_category(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HPNUM1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Antibiotics']);
        $commodity1 = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Amoxicillin', 'order' => 1]);
        $commodity2 = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Gentamicin', 'order' => 2]);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $commodity1->id, 'assessment_department_id' => $department->id,
            'available' => true, 'not_applicable' => false,
        ]);
        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $commodity2->id, 'assessment_department_id' => $department->id,
            'available' => false, 'not_applicable' => false,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('1. Amoxicillin', $html);
        $this->assertStringContainsString('2. Gentamicin', $html);
    }

    public function test_health_products_lettered_sub_items_are_indented(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HPNUM2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Suction Catheters']);
        $sizeA = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Fr-6', 'order' => 1, 'group_label' => 'Suction catheter sizes', 'indent_level' => 1]);
        $sizeB = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Fr-8', 'order' => 2, 'group_label' => 'Suction catheter sizes', 'indent_level' => 1]);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $sizeA->id, 'assessment_department_id' => $department->id,
            'available' => true, 'not_applicable' => false,
        ]);
        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $sizeB->id, 'assessment_department_id' => $department->id,
            'available' => false, 'not_applicable' => false,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('a) Fr-6', $html);
        $this->assertStringContainsString('b) Fr-8', $html);
        $this->assertStringContainsString('padding-left: 24px;', $html);
    }

    public function test_group_label_renders_as_its_own_header_row_above_its_lettered_options(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'HPNUM3', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Suction Catheters']);
        $sizeA = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Fr-6', 'order' => 1, 'group_label' => 'Suction catheters size fr', 'indent_level' => 1]);
        $sizeB = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Fr-8', 'order' => 2, 'group_label' => 'Suction catheters size fr', 'indent_level' => 1]);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $sizeA->id, 'assessment_department_id' => $department->id,
            'available' => true, 'not_applicable' => false,
        ]);
        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $sizeB->id, 'assessment_department_id' => $department->id,
            'available' => false, 'not_applicable' => false,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        // The group header itself ("1. Suction catheters size fr") appears
        // exactly once — not once per lettered option — and carries no
        // Yes/No badge of its own; only "a) Fr-6" and "b) Fr-8" do.
        $this->assertSame(1, substr_count($html, '1. Suction catheters size fr'));
        $headerPos = strpos($html, '1. Suction catheters size fr');
        $aPos = strpos($html, 'a) Fr-6');
        $bPos = strpos($html, 'b) Fr-8');
        $this->assertLessThan($aPos, $headerPos, 'The group header should render before its lettered options');
        $this->assertLessThan($bPos, $aPos);
    }
}
