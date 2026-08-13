<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Database\Seeders\FacilityAssessment2026\FacilityAssessment2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityAssessment2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_2026_assessment_type_with_quality_of_care_parameter(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);

        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->first();

        $this->assertNotNull($type);
        $this->assertSame('2026', $type->version);
        $this->assertTrue($type->is_active);
        $this->assertSame('Neonates 7–28 days', $type->template_parameters['quality_of_care_timeline'] ?? null);
    }

    public function test_creates_facility_profile_and_bed_capacity_as_empty_informational_sections(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->first();

        $profile = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'facility_profile')->first();
        $bedCapacity = AssessmentSection::where('assessment_type_id', $type->id)->where('code', 'bed_capacity')->first();

        $this->assertNotNull($profile);
        $this->assertSame(0, $profile->questions()->count());
        $this->assertNotNull($bedCapacity);
        $this->assertSame(0, $bedCapacity->questions()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(FacilityAssessment2026Seeder::class);
        $this->seed(FacilityAssessment2026Seeder::class);

        $this->assertSame(1, AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->count());
    }

    public function test_does_not_touch_the_2025_standard_facility_assessment(): void
    {
        $before = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->count();

        $this->seed(FacilityAssessment2026Seeder::class);

        $this->assertSame($before, AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->count());
    }
}
