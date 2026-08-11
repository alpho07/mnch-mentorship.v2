<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateAssessmentCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_category_narrows_the_template_options(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_assessment', 'create_assessment']);
        $this->actingAs($user);

        $catA = AssessmentTypeCategory::create(['name' => 'Filter Test Cat A', 'order' => 1, 'is_active' => true]);
        $catB = AssessmentTypeCategory::create(['name' => 'Filter Test Cat B', 'order' => 2, 'is_active' => true]);
        $typeA = AssessmentType::create(['name' => 'Filter Test Type A', 'code' => 'FILTER_TEST_TYPE_A', 'is_active' => true, 'category_id' => $catA->id]);
        $typeB = AssessmentType::create(['name' => 'Filter Test Type B', 'code' => 'FILTER_TEST_TYPE_B', 'is_active' => true, 'category_id' => $catB->id]);

        $component = Livewire::test(CreateAssessment::class);

        // No category picked yet -> both templates visible (mirrors the
        // existing county_filter -> facility_id UX elsewhere on this form).
        $optionsBefore = $component->instance()->form->getComponent('data.assessment_type_id')->getOptions();
        $this->assertArrayHasKey($typeA->id, $optionsBefore);
        $this->assertArrayHasKey($typeB->id, $optionsBefore);

        $component->fillForm(['category_filter' => $catA->id]);

        $optionsAfter = $component->instance()->form->getComponent('data.assessment_type_id')->getOptions();
        $this->assertArrayHasKey($typeA->id, $optionsAfter);
        $this->assertArrayNotHasKey($typeB->id, $optionsAfter);
    }
}
