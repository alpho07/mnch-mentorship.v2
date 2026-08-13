<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class BedCapacitySeeder extends Seeder
{
    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'bed_capacity'],
            [
                'name' => 'Bed Capacities',
                'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES,
                'is_scored' => false,
                'order' => 3,
                'is_active' => true,
            ]
        );

        $this->command->info('  ✓ bed_capacity section (informational placeholder — real fields live in infrastructure).');
    }
}
