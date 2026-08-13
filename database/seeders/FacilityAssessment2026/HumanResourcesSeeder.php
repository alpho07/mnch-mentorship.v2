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
        ['Maternity theatre anaesthetists', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Maternity theatre nurses', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Midwives', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
        ['Post natal ward nurses', ['comprehensive_newborn_care', 'imnci', 'type_1_diabetes']],
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

        $this->command->info('  ✓ human_resources: 13 cadres seeded (tots_count captured on the Assessment record directly).');
    }
}
