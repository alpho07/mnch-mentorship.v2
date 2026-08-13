<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class FacilityProfileSeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'facility_profile'],
            [
                'name' => 'Health Facility Profile',
                'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
                'is_scored' => false,
                'order' => 1,
                'is_active' => true,
            ]
        );

        $this->command->info('  ✓ facility_profile section (informational).');
    }
}
