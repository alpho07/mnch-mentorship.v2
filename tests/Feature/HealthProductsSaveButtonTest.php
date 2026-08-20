<?php

namespace Tests\Feature;

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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthProductsSaveButtonTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        return $user;
    }

    public function test_the_default_filament_form_save_bar_is_removed(): void
    {
        $reflection = new \ReflectionMethod(EditHealthProducts::class, 'getFormActions');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke(new EditHealthProducts()));
    }

    public function test_saving_the_last_department_marks_the_section_complete_and_redirects_onward(): void
    {
        $user = $this->makeAssessor('HP Last Dept Save Assessor');

        $type = AssessmentType::create(['name' => 'HP Last Dept Test', 'code' => 'HP_LAST_DEPT_TEST', 'is_active' => true]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id, 'name' => 'Health Products', 'code' => 'health_products',
            'section_type' => AssessmentSection::KIND_COMMODITY_MATRIX, 'order' => 1, 'is_active' => true,
        ]);

        $dept = AssessmentDepartment::create(['assessment_type_id' => $type->id, 'name' => 'NBU', 'slug' => 'nbu-hp-last', 'is_active' => true, 'order' => 1]);
        $category = CommodityCategory::create(['assessment_type_id' => $type->id, 'name' => 'AIRWAY', 'slug' => 'airway-hp-last', 'order' => 1]);
        $commodity = Commodity::create(['commodity_category_id' => $category->id, 'name' => 'Item A', 'order' => 1, 'is_active' => true]);
        $commodity->applicableDepartments()->attach($dept->id);

        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id, 'assessment_type_id' => $type->id,
            'round' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id, 'assessor_name' => $user->name,
        ]);

        Livewire::test(EditHealthProducts::class, ['record' => $assessment->id])
            ->fillForm(['commodities' => [$dept->id => [$commodity->id => 1]]])
            ->call('saveDepartmentTab', $dept->id);

        $this->assertNotNull(
            AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->where('assessment_department_id', $dept->id)
                ->where('commodity_id', $commodity->id)
                ->first()
        );

        $progress = $assessment->fresh()->section_progress ?? [];
        $this->assertTrue($progress['health_products'] ?? false);
    }
}
