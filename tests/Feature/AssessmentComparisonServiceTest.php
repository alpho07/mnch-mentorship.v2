<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Cadre;
use App\Models\Facility;
use App\Models\HumanResourceResponse;
use App\Services\AssessmentComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_only_one_assessment_exists(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'baseline',
            'assessment_date' => now(),
            'assessor_name' => 'Test Assessor',
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($assessment);

        $this->assertNull($result);
    }

    public function test_orders_rounds_baseline_before_midline_before_endline(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP2', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $endline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'endline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);
        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonths(6), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now()->subMonths(3), 'assessor_name' => 'Test Assessor',
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($endline);

        $this->assertSame(
            [$baseline->id, $midline->id, $endline->id],
            array_column($result['rounds'], 'id')
        );
        $this->assertSame(['Baseline', 'Midline', 'Endline'], array_column($result['rounds'], 'label'));
    }

    public function test_viewing_baseline_shows_no_comparison_even_when_later_rounds_exist(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP4', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonths(6), 'assessor_name' => 'Test Assessor',
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now()->subMonths(3), 'assessor_name' => 'Test Assessor',
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'endline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($baseline);

        $this->assertNull($result);
    }

    public function test_viewing_midline_compares_only_baseline_and_midline(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP5', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();

        $baseline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now()->subMonths(6), 'assessor_name' => 'Test Assessor',
        ]);
        $midline = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'midline', 'assessment_date' => now()->subMonths(3), 'assessor_name' => 'Test Assessor',
        ]);
        Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'endline', 'assessment_date' => now(), 'assessor_name' => 'Test Assessor',
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($midline);

        $this->assertSame(
            [$baseline->id, $midline->id],
            array_column($result['rounds'], 'id')
        );
        $this->assertSame(['Baseline', 'Midline'], array_column($result['rounds'], 'label'));
    }

    public function test_merges_human_resources_rows_by_cadre_across_rounds_with_dash_for_missing(): void
    {
        $type = AssessmentType::create(['name' => 'T', 'code' => 'CMP3', 'version' => '1.0', 'is_active' => true]);
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

        HumanResourceResponse::create([
            'assessment_id' => $baseline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 5,
        ]);
        HumanResourceResponse::create([
            'assessment_id' => $midline->id, 'cadre_id' => $cadre->id, 'total_in_facility' => 8,
        ]);

        $result = app(AssessmentComparisonService::class)->prepareComparisonData($midline);

        $nurseRow = collect($result['humanResources'])->firstWhere('label', 'Nurse');
        $this->assertNotNull($nurseRow);
        $this->assertSame(5, $nurseRow['values'][$baseline->id]['total_in_facility']);
        $this->assertSame(8, $nurseRow['values'][$midline->id]['total_in_facility']);
    }
}
