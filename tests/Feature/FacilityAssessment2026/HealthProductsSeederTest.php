<?php

namespace Tests\Feature\FacilityAssessment2026;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use Database\Seeders\FacilityAssessment2026\HealthProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthProductsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): AssessmentType
    {
        $type = AssessmentType::create(['name' => 'HP Test', 'code' => 'STANDARD_FACILITY_ASSESSMENT_2026', 'is_active' => true]);
        $infraSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 2, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'INFRA_HAS_NICU',
            'question_text' => 'Do you have a NICU', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $infraSection->id, 'question_code' => 'INFRA_HAS_PICU',
            'question_text' => 'Do you have a PICU', 'question_type' => 'yes_no', 'order' => 2, 'is_active' => true,
        ]);
        $skillsLabSection = AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Skills Lab', 'code' => 'skills_lab',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP, 'order' => 4, 'is_active' => true,
        ]);
        AssessmentQuestion::create([
            'assessment_section_id' => $skillsLabSection->id, 'question_code' => 'SKILLS_HAS_LAB',
            'question_text' => 'Is there a functional skills lab', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        return $type;
    }

    public function test_seeds_5_departments_and_8_categories(): void
    {
        $type = $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame(5, AssessmentDepartment::where('assessment_type_id', $type->id)->count());
        $this->assertSame(8, CommodityCategory::where('assessment_type_id', $type->id)->count());
        $this->assertFalse(CommodityCategory::where('assessment_type_id', $type->id)->where('name', 'NICU/PICU')->exists());
    }

    public function test_airway_category_has_40_commodities(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'AIRWAY')->firstOrFail();
        $this->assertSame(40, Commodity::where('commodity_category_id', $category->id)->count());
    }

    public function test_suction_catheters_split_into_4_lettered_commodities(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Suction catheters size')->orderBy('order')->get();
        $this->assertCount(4, $items);
        $this->assertTrue($items->every(fn ($c) => $c->indent_level === 1));
        $this->assertEqualsCanonicalizing(['Fr-6', 'Fr-8', 'Fr-10', 'Fr-12'], $items->pluck('name')->all());
    }

    public function test_ett_sizes_are_gated_on_nicu_or_picu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'ETT')->get();
        $this->assertCount(8, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions['operator'] === 'or'));
        $expectedConditions = [
            ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
            ['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'],
        ];
        $this->assertTrue($items->every(fn ($c) => collect($c->display_conditions['conditions'])->all() == $expectedConditions));
    }

    public function test_ett_sizes_4_and_up_are_excluded_from_nbu_but_smaller_sizes_are_not(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'ETT')->get()->keyBy('name');

        foreach (['2.5', '3.0', '3.5'] as $size) {
            $this->assertSame(5, $items[$size]->applicableDepartments()->count(), "{$size} should apply to all 5 departments");
        }
        foreach (['4.0', '4.5', '5.0', '5.5', '6.0'] as $size) {
            $departments = $items[$size]->applicableDepartments()->pluck('name');
            $this->assertCount(4, $departments, "{$size} should apply to 4 departments (excluding NBU)");
            $this->assertNotContains('NBU', $departments);
        }
    }

    public function test_magill_forceps_is_individually_nicu_gated_not_category_gated(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $magill = Commodity::where('name', 'Magill forceps')->firstOrFail();
        $this->assertSame(['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'], $magill->display_conditions);
        $this->assertSame('AIRWAY', $magill->category->name);
    }

    public function test_preterm_supplements_has_no_display_conditions(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Preterm supplements')->get();
        $this->assertCount(5, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions === null));
    }

    public function test_surfactant_and_midazolam_are_gated_on_nicu_or_picu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        foreach (['surfactant', 'Midazolam'] as $name) {
            $c = Commodity::where('name', $name)->firstOrFail();
            $this->assertSame('or', $c->display_conditions['operator']);
            $this->assertEqualsCanonicalizing(
                [
                    ['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'],
                    ['question_code' => 'INFRA_HAS_PICU', 'operator' => 'equals', 'value' => 'Yes'],
                ],
                $c->display_conditions['conditions']
            );
        }
    }

    public function test_every_commodity_is_applicable_to_all_5_departments(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $commodity = Commodity::where('name', 'Stethoscope')->first();
        $this->assertSame(5, $commodity->applicableDepartments()->count());
    }

    public function test_long_acting_insulin_and_salbutamol_inhaler_are_excluded_from_nbu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        foreach (['long acting insulin', 'Salbutamol inhaler'] as $name) {
            $commodity = Commodity::where('name', $name)->firstOrFail();
            $departments = $commodity->applicableDepartments()->pluck('name');
            $this->assertCount(4, $departments, "{$name} should apply to 4 departments (excluding NBU)");
            $this->assertNotContains('NBU', $departments);
        }

        // Soluble insulin (same group as long acting insulin) isn't excluded.
        $solubleInsulin = Commodity::where('name', 'Soluble insulin')->firstOrFail();
        $this->assertSame(5, $solubleInsulin->applicableDepartments()->count());
    }

    public function test_skills_lab_department_is_gated_on_skills_has_lab(): void
    {
        $type = $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $dept = AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'Skills lab')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'], $dept->display_conditions);

        $otherDept = AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'NBU')->firstOrFail();
        $this->assertNull($otherDept->display_conditions);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);
        $countBefore = Commodity::count();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame($countBefore, Commodity::count());
    }
}
