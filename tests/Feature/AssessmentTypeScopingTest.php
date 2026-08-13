<?php

namespace Tests\Feature;

use App\Models\AssessmentDepartment;
use App\Models\AssessmentType;
use App\Models\CommodityCategory;
use App\Models\MainCadre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_commodity_categories_can_share_the_same_slug_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST', 'is_active' => true]);

        $cat2025 = CommodityCategory::create(['assessment_type_id' => $type2025->id, 'name' => 'AIRWAY', 'slug' => 'airway']);
        $cat2026 = CommodityCategory::create(['assessment_type_id' => $type2026->id, 'name' => 'AIRWAY', 'slug' => 'airway']);

        $this->assertNotSame($cat2025->id, $cat2026->id);
        $this->assertSame($type2025->id, $cat2025->fresh()->assessment_type_id);
        $this->assertSame($type2026->id, $cat2026->fresh()->assessment_type_id);
    }

    public function test_assessment_departments_can_share_the_same_slug_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST2', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST2', 'is_active' => true]);

        $dept2025 = AssessmentDepartment::create(['assessment_type_id' => $type2025->id, 'name' => 'Skills Lab', 'slug' => 'skills-lab']);
        $dept2026 = AssessmentDepartment::create(['assessment_type_id' => $type2026->id, 'name' => 'Skills Lab', 'slug' => 'skills-lab']);

        $this->assertNotSame($dept2025->id, $dept2026->id);
    }

    public function test_assessment_cadres_can_share_the_same_code_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST3', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST3', 'is_active' => true]);

        $cadre2025 = MainCadre::create(['assessment_type_id' => $type2025->id, 'name' => 'Neonatologist', 'code' => 'neonatologist']);
        $cadre2026 = MainCadre::create(['assessment_type_id' => $type2026->id, 'name' => 'Neonatologist', 'code' => 'neonatologist']);

        $this->assertNotSame($cadre2025->id, $cadre2026->id);
    }

    public function test_assessment_sections_and_questions_can_share_codes_across_two_assessment_types(): void
    {
        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_TEST4', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_TEST4', 'is_active' => true]);

        $section2025 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2025->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => 'dynamic_questions', 'order' => 1, 'is_active' => true,
        ]);
        $section2026 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Infrastructure', 'code' => 'infrastructure',
            'section_type' => 'dynamic_questions', 'order' => 1, 'is_active' => true,
        ]);

        $q2025 = \App\Models\AssessmentQuestion::create([
            'assessment_section_id' => $section2025->id, 'question_code' => 'INFRA_Q1',
            'question_text' => 'Test', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);
        $q2026 = \App\Models\AssessmentQuestion::create([
            'assessment_section_id' => $section2026->id, 'question_code' => 'INFRA_Q1',
            'question_text' => 'Test', 'question_type' => 'yes_no', 'order' => 1, 'is_active' => true,
        ]);

        $this->assertNotSame($section2025->id, $section2026->id);
        $this->assertNotSame($q2025->id, $q2026->id);
    }

    public function test_health_products_page_only_shows_the_assessments_own_type_departments_and_categories(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'Scoping Assessor']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_HP', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_HP', 'is_active' => true]);

        $section2026 = \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => \App\Models\AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept2025 = AssessmentDepartment::create(['assessment_type_id' => $type2025->id, 'name' => 'Old Dept Only 2025', 'slug' => 'old-dept-2025', 'is_active' => true, 'order' => 1]);
        $dept2026 = AssessmentDepartment::create(['assessment_type_id' => $type2026->id, 'name' => 'New Dept Only 2026', 'slug' => 'new-dept-2026', 'is_active' => true, 'order' => 1]);

        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type2026->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = \App\Filament\Resources\AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('New Dept Only 2026');
        $response->assertDontSee('Old Dept Only 2025');
    }

    public function test_human_resources_page_only_shows_the_assessments_own_type_cadres(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'HR Scoping Assessor']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type2025 = AssessmentType::create(['name' => 'Standard 2025', 'code' => 'STD_2025_HR', 'is_active' => true]);
        $type2026 = AssessmentType::create(['name' => 'Standard 2026', 'code' => 'STD_2026_HR', 'is_active' => true]);

        \App\Models\AssessmentSection::create([
            'assessment_type_id' => $type2026->id, 'name' => 'Human Resources', 'code' => 'human_resources',
            'section_type' => \App\Models\AssessmentSection::KIND_HUMAN_RESOURCES, 'order' => 1, 'is_active' => true,
        ]);

        MainCadre::create(['assessment_type_id' => $type2025->id, 'name' => 'Old Cadre Only 2025', 'code' => 'old_cadre_2025', 'is_active' => true, 'order' => 1]);
        MainCadre::create(['assessment_type_id' => $type2026->id, 'name' => 'New Cadre Only 2026', 'code' => 'new_cadre_2026', 'is_active' => true, 'order' => 1]);

        $facility = \App\Models\Facility::factory()->create();
        $assessment = \App\Models\Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type2026->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = \App\Filament\Resources\AssessmentResource::getUrl('edit-human-resources', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('New Cadre Only 2026');
        $response->assertDontSee('Old Cadre Only 2025');
    }
}
