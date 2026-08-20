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

class AssessmentReportCommodityQuantityTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantity_shows_next_to_yes_when_present(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'QTY1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Equipment']);
        $commodity = Commodity::create([
            'commodity_category_id' => $category->id, 'name' => 'Functional Infusion Pumps. If yes indicate number',
            'order' => 1, 'requires_quantity' => true,
        ]);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $commodity->id, 'assessment_department_id' => $department->id,
            'available' => true, 'not_applicable' => false, 'quantity' => 4,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('(4)', $html);
    }

    public function test_no_quantity_shown_when_answer_is_no(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'QTY2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $department = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Pharmacy']);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'Equipment']);
        $commodity = Commodity::create([
            'commodity_category_id' => $category->id, 'name' => 'Functional Syringe pumps. If yes indicate number',
            'order' => 1, 'requires_quantity' => true,
        ]);

        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $commodity->id, 'assessment_department_id' => $department->id,
            'available' => false, 'not_applicable' => false, 'quantity' => null,
        ]);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Functional Syringe pumps', $html);
        $this->assertStringNotContainsString('(4)', $html);
    }

    public function test_model_marks_only_the_two_named_commodities_as_requiring_quantity(): void
    {
        $this->seed(\Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder::class);

        $flagged = Commodity::where('requires_quantity', true)->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'Functional Infusion Pumps. If yes indicate number',
            'Functional Syringe pumps. If yes indicate number',
        ], $flagged);
    }
}
