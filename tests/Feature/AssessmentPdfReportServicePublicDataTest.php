<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPdfReportServicePublicDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_report_data_is_publicly_callable(): void
    {
        $type = AssessmentType::create(['name' => 'Template', 'code' => 'PUB1', 'version' => '1.0', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'round' => 'baseline',
            'assessment_date' => now(),
            'assessor_name' => 'Test Assessor',
        ]);

        $data = app(AssessmentPdfReportService::class)->prepareReportData($assessment);

        $this->assertArrayHasKey('facilityInfo', $data);
        $this->assertArrayHasKey('humanResourcesDetails', $data);
        $this->assertArrayHasKey('healthProductsDetails', $data);
    }
}
