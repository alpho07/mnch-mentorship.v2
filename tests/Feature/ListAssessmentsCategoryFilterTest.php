<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ListAssessmentsCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filter_narrows_the_assessments_table(): void
    {
        $user = User::factory()->create(['name' => 'List Filter Assessor']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $this->actingAs($user);

        $catA = AssessmentTypeCategory::create(['name' => 'List Filter Cat A', 'order' => 1, 'is_active' => true]);
        $catB = AssessmentTypeCategory::create(['name' => 'List Filter Cat B', 'order' => 2, 'is_active' => true]);
        $typeA = AssessmentType::create(['name' => 'List Filter Type A', 'code' => 'LIST_FILTER_TYPE_A', 'is_active' => true, 'category_id' => $catA->id]);
        $typeB = AssessmentType::create(['name' => 'List Filter Type B', 'code' => 'LIST_FILTER_TYPE_B', 'is_active' => true, 'category_id' => $catB->id]);

        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $assessmentA = Assessment::create(['facility_id' => $facilityA->id, 'assessment_type_id' => $typeA->id, 'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id]);
        $assessmentB = Assessment::create(['facility_id' => $facilityB->id, 'assessment_type_id' => $typeB->id, 'assessment_type' => 'baseline', 'assessment_date' => now(), 'assessor_id' => $user->id]);

        Livewire::test(ListAssessments::class)
            ->filterTable('assessment_type_category_id', $catA->id)
            ->assertCanSeeTableRecords([$assessmentA])
            ->assertCanNotSeeTableRecords([$assessmentB]);
    }
}
