<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentTypeCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_type_category_can_be_created_and_scoped(): void
    {
        $active = AssessmentTypeCategory::create(['name' => 'EmONC', 'order' => 1, 'is_active' => true]);
        $inactive = AssessmentTypeCategory::create(['name' => 'Retired', 'order' => 2, 'is_active' => false]);

        $activeIds = AssessmentTypeCategory::active()->ordered()->pluck('id')->all();

        $this->assertSame([$active->id], $activeIds);
    }

    public function test_assessment_type_belongs_to_a_category(): void
    {
        $category = AssessmentTypeCategory::create(['name' => 'EmONC', 'order' => 1, 'is_active' => true]);
        $type = AssessmentType::create([
            'name' => 'Test Type',
            'code' => 'CATEGORY_RELATION_TEST',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($type->fresh()->category->is($category));
    }
}
