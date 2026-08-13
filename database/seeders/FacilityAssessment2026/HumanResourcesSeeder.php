<?php

namespace Database\Seeders\FacilityAssessment2026;

use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\MainCadre;
use Illuminate\Database\Seeder;

class HumanResourcesSeeder extends Seeder
{
    private const CADRES = [
        ['Neonatologist', []],
        ['Paediatrician', []],
        ['Medical officer', []],
        ['General nurses NBU', ['type_1_diabetes']],
        ['Neonatal nurses', ['type_1_diabetes']],
        ['General nurses-paediatric', []],
        ['Paediatric nurses', []],
        ['Clinical officer paediatric', []],
        ['Clinical officer', []],
        // Only trained in Comprehensive Newborn Care and Essential Newborn
        // Care — ETAT+, IMNCI, and Type 1 Diabetes are N/A for these 4.
        ['Maternity theatre anaesthetists', ['etat_plus', 'imnci', 'type_1_diabetes']],
        ['Maternity theatre nurses', ['etat_plus', 'imnci', 'type_1_diabetes']],
        ['Midwives', ['etat_plus', 'imnci', 'type_1_diabetes']],
        ['Post natal ward nurses', ['etat_plus', 'imnci', 'type_1_diabetes']],
        // Reports per-area training counts like any other cadre, but has
        // no meaningful "total staff" figure of its own — 'total_in_facility'
        // is a na_training_columns sentinel MainCadre::hidesTotalInFacility()
        // reads, not one of the five real TRAINING_COLUMNS. Kept last so it
        // renders at the bottom of the matrix, after every real cadre.
        ['No of TOTs', ['total_in_facility']],
    ];

    public function run(): void
    {
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT_2026')->firstOrFail();

        AssessmentSection::firstOrCreate(
            ['assessment_type_id' => $type->id, 'code' => 'human_resources'],
            ['name' => 'Human Resources managing newborns and paediatric patients', 'section_type' => AssessmentSection::KIND_HUMAN_RESOURCES, 'is_scored' => false, 'order' => 5, 'is_active' => true]
        );

        foreach (self::CADRES as $order => [$name, $naColumns]) {
            MainCadre::updateOrCreate(
                ['assessment_type_id' => $type->id, 'name' => $name],
                ['order' => $order + 1, 'is_active' => true, 'na_training_columns' => $naColumns ?: null]
            );
        }

        $this->command->info('  ✓ human_resources: 14 cadres seeded (incl. ToTs, no total-in-facility column).');
    }
}
