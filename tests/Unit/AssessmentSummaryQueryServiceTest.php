<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentSummaryQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentSummaryQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessment(User $assessor, Facility $facility, string $status = 'completed', ?float $percentage = 80.0): Assessment
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
            'status' => $status,
            'overall_percentage' => $percentage,
            'overall_grade' => $percentage >= 70 ? 'green' : 'red',
        ]);
    }

    public function test_status_counts_are_scoped_to_the_assessors_own_assessments(): void
    {
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        $assessorA = User::factory()->create(['name' => 'A']);
        $assessorA->assignRole('assessor');
        $assessorB = User::factory()->create(['name' => 'B']);
        $assessorB->assignRole('assessor');

        $facility = Facility::factory()->create();
        $this->makeAssessment($assessorA, $facility, 'completed');
        $this->makeAssessment($assessorB, $facility, 'completed');

        $service = new AssessmentSummaryQueryService;
        $counts = $service->statusCounts($assessorA);

        $this->assertSame(1, $counts['completed'] ?? 0);
    }

    public function test_readiness_scores_filters_below_a_threshold(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Test Admin']);
        $admin->assignRole('super_admin');

        $weakFacility = Facility::factory()->create(['name' => 'Weak Facility']);
        $strongFacility = Facility::factory()->create(['name' => 'Strong Facility']);
        $this->makeAssessment($admin, $weakFacility, 'completed', 40.0);
        $this->makeAssessment($admin, $strongFacility, 'completed', 90.0);

        $this->actingAs($admin);
        $service = new AssessmentSummaryQueryService;
        $scores = $service->readinessScores($admin, belowPercentage: 50.0);

        $this->assertCount(1, $scores);
        $this->assertSame('Weak Facility', $scores[0]['facility']);
    }
}
