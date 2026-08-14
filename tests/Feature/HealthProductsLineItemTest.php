<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Filament\Resources\AssessmentResource\Pages\EditHealthProducts;
use App\Models\Assessment;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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

    /**
     * Regression: saving used to call AssessmentCommodityResponse::
     * updateOrCreate() once per commodity, and the model's `saved` event
     * recalculated the whole department's score on every single one of
     * those calls — for a facility with hundreds of commodities, a single
     * Save click fired thousands of queries and could exceed PHP's 30s
     * execution limit. The fix bulk-upserts all commodities in one query
     * and recalculates each department's score exactly once.
     */
    public function test_saving_the_full_form_uses_a_bounded_number_of_queries_not_one_per_commodity(): void
    {
        $user = User::factory()->create(['name' => 'HP Bulk Save Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP Bulk Save Test', 'code' => 'HP_BULK_SAVE_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-bulk', 'is_active' => true, 'order' => 1]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-bulk', 'order' => 1]);

        $commodityIds = [];
        for ($i = 1; $i <= 40; $i++) {
            $commodity = Commodity::create([
                'commodity_category_id' => $category->id, 'name' => "Item {$i}", 'order' => $i, 'is_active' => true,
            ]);
            $commodity->applicableDepartments()->attach($dept->id);
            $commodityIds[] = $commodity->id;
        }

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        $formCommodities = array_fill_keys($commodityIds, 1);

        $component = Livewire::test(EditHealthProducts::class, ['record' => $assessment->id])
            ->fillForm(['commodities' => [$dept->id => $formCommodities]]);

        // Only the save() call itself is measured — mounting/rendering the
        // page has its own baseline query cost unrelated to this fix.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('save')->assertHasNoFormErrors();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The old code ran one updateOrCreate() + one full department
        // rescore per commodity (each rescore itself several queries) —
        // for 40 commodities that was in the hundreds. Bulk upsert keeps
        // this near-constant regardless of commodity count.
        $this->assertLessThan(40, $queryCount, "Expected a bounded query count, not roughly one per commodity (40 commodities produced {$queryCount} queries for save() alone)");
        $this->assertSame(40, AssessmentCommodityResponse::where('assessment_id', $assessment->id)->where('available', true)->count());
    }

    /**
     * The per-department Save button inside each tab must only persist
     * that department's commodities, leaving other departments' data
     * (already-saved or not-yet-filled-in) completely untouched.
     */
    public function test_save_department_tab_only_persists_that_departments_commodities(): void
    {
        $user = User::factory()->create(['name' => 'HP Per-Tab Save Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $type = AssessmentType::create(['name' => 'HP Per-Tab Save Test', 'code' => 'HP_PER_TAB_SAVE_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $deptA = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-tab', 'is_active' => true, 'order' => 1]);
        $deptB = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'Paediatric', 'slug' => 'paed-hp-tab', 'is_active' => true, 'order' => 2]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-tab', 'order' => 1]);

        $commodityA = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item A', 'order' => 1, 'is_active' => true]);
        $commodityA->applicableDepartments()->attach([$deptA->id, $deptB->id]);
        $commodityB = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item B', 'order' => 2, 'is_active' => true]);
        $commodityB->applicableDepartments()->attach([$deptA->id, $deptB->id]);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id,
        ]);

        Livewire::test(EditHealthProducts::class, ['record' => $assessment->id])
            ->fillForm([
                'commodities' => [
                    $deptA->id => [$commodityA->id => 1],
                    $deptB->id => [$commodityB->id => 0],
                ],
            ])
            ->call('saveDepartmentTab', $deptA->id);

        $this->assertNotNull(
            AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->where('assessment_department_id', $deptA->id)
                ->where('commodity_id', $commodityA->id)
                ->first()
        );
        // Department B's data was filled in the form but never submitted
        // via saveDepartmentTab(A) — it must not have been persisted.
        $this->assertNull(
            AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->where('assessment_department_id', $deptB->id)
                ->first()
        );
    }
}
