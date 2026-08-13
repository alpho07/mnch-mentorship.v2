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

    public function test_seeds_14_cadres(): void
    {
        $type = $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $this->assertSame(14, MainCadre::where('assessment_type_id', $type->id)->count());
    }

    public function test_tots_hides_total_in_facility_but_keeps_all_training_areas(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'No of TOTs')->firstOrFail();
        $this->assertTrue($cadre->hidesTotalInFacility());
        foreach (MainCadre::TRAINING_COLUMNS as $column) {
            $this->assertFalse($cadre->isColumnNotApplicable($column));
        }
    }

    public function test_general_nurses_nbu_has_type_1_diabetes_na(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'General nurses NBU')->firstOrFail();
        $this->assertSame(['type_1_diabetes'], $cadre->na_training_columns);
    }

    public function test_maternity_theatre_anaesthetists_is_only_trained_in_comprehensive_and_essential_newborn_care(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Maternity theatre anaesthetists')->firstOrFail();
        $this->assertEqualsCanonicalizing(['etat_plus', 'imnci', 'type_1_diabetes'], $cadre->na_training_columns);
        $this->assertEqualsCanonicalizing(
            ['comprehensive_newborn_care', 'essential_newborn_care'],
            array_diff(MainCadre::TRAINING_COLUMNS, $cadre->na_training_columns)
        );
    }

    public function test_maternity_and_midwifery_cadres_are_only_trained_in_comprehensive_and_essential_newborn_care(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        foreach (['Maternity theatre anaesthetists', 'Maternity theatre nurses', 'Midwives', 'Post natal ward nurses'] as $name) {
            $cadre = MainCadre::where('name', $name)->firstOrFail();
            $this->assertEqualsCanonicalizing(['etat_plus', 'imnci', 'type_1_diabetes'], $cadre->na_training_columns, "{$name} should hide ETAT+, IMNCI, and Type 1 Diabetes");
        }
    }

    public function test_clinical_officer_has_all_training_areas(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Clinical officer')->firstOrFail();
        $this->assertEmpty($cadre->na_training_columns ?? []);
    }

    public function test_neonatologist_has_no_na_columns(): void
    {
        $this->makeType();
        $this->seed(HumanResourcesSeeder::class);

        $cadre = MainCadre::where('name', 'Neonatologist')->firstOrFail();
        $this->assertEmpty($cadre->na_training_columns ?? []);
    }
}
