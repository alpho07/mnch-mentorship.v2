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

    public function test_seeds_7_departments_and_12_categories(): void
    {
        $type = $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame(7, AssessmentDepartment::where('assessment_type_id', $type->id)->count());
        $this->assertSame(12, CommodityCategory::where('assessment_type_id', $type->id)->count());
        $this->assertTrue(AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'Paediatric outpatient')->exists());
        $this->assertTrue(AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'Laboratory')->exists());
        $this->assertTrue(CommodityCategory::where('assessment_type_id', $type->id)->where('name', 'LABORATORY')->exists());
    }

    /**
     * Promoted from ChecklistsSeeder's read-only "Skills Lab Checklist
     * Requirements" reference content into real, individually answerable
     * commodities scoped to the Skills lab department alone.
     */
    public function test_skills_lab_equipment_category_is_scoped_to_skills_lab_only(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'SKILLS LAB EQUIPMENT')->firstOrFail();
        $items = Commodity::where('commodity_category_id', $category->id)->get();
        $this->assertCount(7, $items);
        $this->assertTrue($items->contains('name', 'mama breast'));
        $this->assertTrue($items->contains('name', 'Radiant Warmer'));
        $this->assertTrue($items->contains('name', 'Flip charts'));

        foreach ($items as $item) {
            $departments = $item->applicableDepartments()->pluck('name');
            $this->assertSame(['Skills lab'], $departments->all());
        }
    }

    /**
     * Promoted from ChecklistsSeeder's "Triage requirements" reference
     * content, scoped to the Paediatric outpatient department alone.
     */
    public function test_triage_category_is_scoped_to_paediatric_outpatient_only(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'TRIAGE')->firstOrFail();
        $items = Commodity::where('commodity_category_id', $category->id)->get();
        $this->assertCount(17, $items);
        $this->assertTrue($items->contains('name', 'Stadiometer'));

        foreach ($items as $item) {
            $departments = $item->applicableDepartments()->pluck('name');
            $this->assertSame(['Paediatric outpatient'], $departments->all());
        }
    }

    /**
     * Promoted from ChecklistsSeeder's "ORT Corner checklist" reference
     * content, scoped to the Paediatric ward department alone ("Paediatric
     * Inpatient" in the source request maps to that existing department).
     */
    public function test_ort_corner_category_is_scoped_to_paediatric_ward_only(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'ORT CORNER')->firstOrFail();
        $items = Commodity::where('commodity_category_id', $category->id)->get();
        $this->assertCount(17, $items);
        $this->assertTrue($items->contains('name', 'Clean spoons'));

        foreach ($items as $item) {
            $departments = $item->applicableDepartments()->pluck('name');
            $this->assertSame(['Paediatric ward'], $departments->all());
        }
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

    /**
     * Row 112: whole group excluded from Theatre; sizes 4.5-6.0
     * additionally excluded from NBU ("N/A in newborn 4.5 to 6" — 4.0 is
     * not listed, so it stays applicable there). No NICU/PICU gate — the
     * source spreadsheet has no such marker on this row at all.
     */
    public function test_ett_sizes_have_no_gate_theatre_excluded_and_larger_sizes_also_exclude_nbu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Endotracheal Tubes (size 2 –size 4)')->get()->keyBy('name');
        $this->assertCount(8, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions === null));

        foreach (['2.5', '3.0', '3.5', '4.0'] as $size) {
            $departments = $items[$size]->applicableDepartments()->pluck('name');
            $this->assertNotContains('Theatre', $departments);
            $this->assertContains('Newborn Unit (NBU)', $departments);
        }
        foreach (['4.5', '5.0', '5.5', '6.0'] as $size) {
            $departments = $items[$size]->applicableDepartments()->pluck('name');
            $this->assertNotContains('Theatre', $departments);
            $this->assertNotContains('Newborn Unit (NBU)', $departments);
        }
    }

    /**
     * Magill forceps / umbilical vein / umbilical artery catheters had a
     * NICU gate that isn't backed by any marker on their spreadsheet rows
     * (107-227 has "For NICU" only on surfactant and Midazolam) — removed.
     */
    public function test_magill_forceps_and_umbilical_catheters_have_no_gate(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        foreach (['Magill forceps', 'Umbilical vein catheters', 'Umbilical artery catheters'] as $name) {
            $commodity = Commodity::where('name', $name)->firstOrFail();
            $this->assertNull($commodity->display_conditions, "{$name} should have no display_conditions");
            $this->assertSame('AIRWAY', $commodity->category->name);
        }
    }

    public function test_preterm_supplements_has_no_gate_but_excludes_skills_lab_and_theatre(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $items = Commodity::where('group_label', 'Preterm supplements')->get();
        $this->assertCount(5, $items);
        $this->assertTrue($items->every(fn ($c) => $c->display_conditions === null));

        $departments = $items->first()->applicableDepartments()->pluck('name');
        $this->assertNotContains('Skills lab', $departments);
        $this->assertNotContains('Theatre', $departments);
        $this->assertContains('Newborn Unit (NBU)', $departments);
    }

    /**
     * Row 180/183: blank in NBU/Maternity, N/A in Theatre/Paed ward/Paed
     * outpatient, and "For NICU" only within Skills lab — a single shared
     * gate can't express "gated in one department, unconditional in
     * others", so each is two commodity rows sharing the same name.
     */
    public function test_surfactant_and_midazolam_are_split_into_a_plain_row_and_a_nicu_gated_skills_lab_row(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        // surfactant (row 180) is N/A in Theatre/Paed ward/Paed outpatient;
        // Midazolam (row 183) is N/A in Theatre only — different exclude
        // sets, but both share the same "plain row + NICU-gated Skills lab
        // row" split.
        $expectedExcluded = [
            'surfactant' => ['Theatre', 'Paediatric ward', 'Paediatric outpatient'],
            'Midazolam' => ['Theatre'],
        ];

        foreach ($expectedExcluded as $name => $excluded) {
            $rows = Commodity::where('name', $name)->get();
            $this->assertCount(2, $rows, "{$name} should be seeded as two rows");

            $plainRow = $rows->firstWhere('display_conditions', null);
            $this->assertNotNull($plainRow, "{$name} should have one ungated row");
            $plainDepartments = $plainRow->applicableDepartments()->pluck('name');
            $this->assertContains('Newborn Unit (NBU)', $plainDepartments);
            $this->assertContains('Maternity', $plainDepartments);
            $this->assertNotContains('Skills lab', $plainDepartments);
            foreach ($excluded as $dept) {
                $this->assertNotContains($dept, $plainDepartments, "{$name}'s plain row should exclude {$dept}");
            }

            $gatedRow = $rows->first(fn ($c) => $c->display_conditions !== null);
            $this->assertNotNull($gatedRow, "{$name} should have one NICU-gated row");
            $this->assertSame(['question_code' => 'INFRA_HAS_NICU', 'operator' => 'equals', 'value' => 'Yes'], $gatedRow->display_conditions);
            $gatedDepartments = $gatedRow->applicableDepartments()->pluck('name');
            $this->assertSame(['Skills lab'], $gatedDepartments->all());
        }
    }

    public function test_every_commodity_is_applicable_to_all_6_matrix_departments_by_default(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $commodity = Commodity::where('name', 'Stethoscope')->first();
        $departments = $commodity->applicableDepartments()->pluck('name');
        $this->assertCount(6, $departments);
        $this->assertNotContains('Laboratory', $departments);
    }

    /**
     * Row 207/208: whole group excluded from Theatre; "long acting
     * insulin"/"Salbutamol inhaler" additionally excluded from NBU.
     * "Salbutamol respirator solution" is its own real sub-item — the old
     * seeder only ever created "Salbutamol inhaler" and used "Salbutamol
     * respirator solution" purely as the group label, silently dropping
     * the first sub-item entirely.
     */
    public function test_insulin_and_salbutamol_groups_exclude_theatre_and_their_second_item_also_excludes_nbu(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $this->assertCount(2, Commodity::where('group_label', 'Salbutamol')->get());

        foreach ([
            'Soluble insulin' => 'long acting insulin',
            'Salbutamol respirator solution' => 'Salbutamol inhaler',
        ] as $unrestricted => $restricted) {
            $unrestrictedDepartments = Commodity::where('name', $unrestricted)->firstOrFail()->applicableDepartments()->pluck('name');
            $this->assertNotContains('Theatre', $unrestrictedDepartments);
            $this->assertContains('Newborn Unit (NBU)', $unrestrictedDepartments);

            $restrictedDepartments = Commodity::where('name', $restricted)->firstOrFail()->applicableDepartments()->pluck('name');
            $this->assertNotContains('Theatre', $restrictedDepartments);
            $this->assertNotContains('Newborn Unit (NBU)', $restrictedDepartments);
        }
    }

    public function test_skills_lab_department_is_gated_on_skills_has_lab(): void
    {
        $type = $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $dept = AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'Skills lab')->firstOrFail();
        $this->assertSame(['question_code' => 'SKILLS_HAS_LAB', 'operator' => 'equals', 'value' => 'Yes'], $dept->display_conditions);

        $otherDept = AssessmentDepartment::where('assessment_type_id', $type->id)->where('name', 'Newborn Unit (NBU)')->firstOrFail();
        $this->assertNull($otherDept->display_conditions);
    }

    /**
     * Ported from the 2025 "Standard Facility Assessment" template's
     * LABORATORY category, which has no equivalent in the 2026 spreadsheet
     * at all — every item is scoped to the Laboratory department alone.
     */
    public function test_laboratory_category_is_scoped_to_the_laboratory_department_only(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'LABORATORY')->firstOrFail();
        $items = Commodity::where('commodity_category_id', $category->id)->get();
        $this->assertCount(9, $items);
        $this->assertTrue($items->contains('name', 'Microscope'));
        $this->assertTrue($items->contains('name', 'Malaria rapid diagnostic test (mRDT)'));

        foreach ($items as $item) {
            $this->assertNull($item->display_conditions);
            $departments = $item->applicableDepartments()->pluck('name');
            $this->assertSame(['Laboratory'], $departments->all());
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);
        $countBefore = Commodity::count();
        $this->seed(HealthProductsSeeder::class);

        $this->assertSame($countBefore, Commodity::count());
    }

    /**
     * Regression: renaming or reordering a row shifts createCommodity()'s
     * natural key for everything seeded after it, so a re-seed creates a
     * fresh row instead of updating the old one — the old row is never
     * touched again and silently lingers (this is exactly how 70 stale
     * rows accumulated in one revision of this seeder). Anything this run
     * didn't touch is pruned, unless a real assessment already recorded a
     * response against it.
     */
    public function test_a_row_this_run_no_longer_produces_is_pruned_unless_it_has_a_response(): void
    {
        $this->makeType();
        $this->seed(HealthProductsSeeder::class);

        $category = CommodityCategory::where('name', 'AIRWAY')->firstOrFail();
        $staleWithResponse = Commodity::create([
            'commodity_category_id' => $category->id, 'name' => 'Retired stray item', 'order' => 999, 'is_active' => true,
        ]);
        $staleWithoutResponse = Commodity::create([
            'commodity_category_id' => $category->id, 'name' => 'Another retired stray item', 'order' => 1000, 'is_active' => true,
        ]);
        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $category->assessment_type_id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_name' => 'Prune Test Assessor',
        ]);
        $dept = AssessmentDepartment::where('assessment_type_id', $category->assessment_type_id)->where('name', 'Newborn Unit (NBU)')->firstOrFail();
        \App\Models\AssessmentCommodityResponse::create([
            'assessment_id' => $assessment->id, 'commodity_id' => $staleWithResponse->id,
            'assessment_department_id' => $dept->id, 'available' => true,
        ]);

        $this->seed(HealthProductsSeeder::class);

        $this->assertNotNull(Commodity::find($staleWithResponse->id), 'A stale row with a real response must not be deleted');
        $this->assertNull(Commodity::find($staleWithoutResponse->id), 'A stale row with no response must be pruned');
    }
}
