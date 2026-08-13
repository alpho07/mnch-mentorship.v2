<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthProductsLineItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_line_item_commodities_render_a_group_header_then_lettered_indented_rows(): void
    {
        $user = User::factory()->create(['name' => 'HP Line Item Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP Line Item Test', 'code' => 'HP_LINE_ITEM_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-li', 'is_active' => true, 'order' => 1]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-li', 'order' => 1]);

        $sizes = ['Fr-6' => 1, 'Fr-8' => 2, 'Fr-10' => 3];
        foreach ($sizes as $size => $order) {
            $commodity = Commodity::create([
                'commodity_category_id' => $category->id,
                'name' => $size,
                'group_label' => 'Suction catheter sizes',
                'indent_level' => 1,
                'order' => $order,
                'is_active' => true,
            ]);
            $commodity->applicableDepartments()->attach($dept->id);
        }

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('1. Suction catheter sizes', false);
        $response->assertSee('a) Fr-6', false);
        $response->assertSee('b) Fr-8', false);
        $response->assertSee('c) Fr-10', false);
    }

    public function test_standalone_commodities_are_numbered_restarting_per_category(): void
    {
        $user = User::factory()->create(['name' => 'HP Numbering Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP Numbering Test', 'code' => 'HP_NUMBERING_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-num', 'is_active' => true, 'order' => 1]);
        $categoryA = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-num', 'order' => 1]);
        $categoryB = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'CIRCULATION', 'slug' => 'circulation-hp-num', 'order' => 2]);

        foreach (['Item A' => 1, 'Item B' => 2] as $name => $order) {
            $commodity = Commodity::create([
                'commodity_category_id' => $categoryA->id, 'name' => $name, 'order' => $order, 'is_active' => true,
            ]);
            $commodity->applicableDepartments()->attach($dept->id);
        }

        $commodity = Commodity::create([
            'commodity_category_id' => $categoryB->id, 'name' => 'Item C', 'order' => 1, 'is_active' => true,
        ]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('1. Item A', false);
        $response->assertSee('2. Item B', false);
        // Numbering restarts at 1 for the next category, not continuing at 3.
        $response->assertSee('1. Item C', false);
    }

    public function test_not_applicable_option_is_removed(): void
    {
        $user = User::factory()->create(['name' => 'HP NA Removal Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP NA Removal Test', 'code' => 'HP_NA_REMOVAL_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-na', 'is_active' => true, 'order' => 1]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-na', 'order' => 1]);
        $commodity = Commodity::create([
            'commodity_category_id' => $category->id, 'name' => 'Stethoscope', 'order' => 1, 'is_active' => true,
        ]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('edit-health-products', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Available', false);
        $response->assertSee('Not Available', false);
        $response->assertDontSee('Not Applicable', false);
    }
}
