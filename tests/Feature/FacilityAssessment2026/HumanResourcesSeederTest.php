<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentType;
use App\Models\MainCadre;
use Database\Seeders\FacilityAssessment2026\HumanResourcesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanResourcesSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        return AssessmentType::create(['name' => 'HR Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
    }

    public function test_seeds_13_cadres(): void
    {
        $type = $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $this->assertSame(13, MainCadre::where('assessment_type_id', $type->id)->count());
    }

    public function test_general_nurses_nbu_has_type_1_diabetes_na(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'General nurses NBU')->firstOrFail();
        $this->assertSame(['type_1_diabetes'], $cadre->na_training_columns);
    }

    public function test_maternity_theatre_anaesthetists_has_three_na_columns(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Maternity theatre anaesthetists')->firstOrFail();
        $this->assertEqualsCanonicalizing(['comprehensive_newborn_care', 'imnci', 'type_1_diabetes'], $cadre->na_training_columns);
    }

    public function test_neonatologist_has_no_na_columns(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Neonatologist')->firstOrFail();
        $this->assertEmpty($cadre->na_training_columns ?? []);
    }
}
