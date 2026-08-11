<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Models\AssessmentTypeCategory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentDashboardCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_the_templates_category(): void
    {
        $user = User::factory()->create(['name' => 'Dashboard Category Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_assessment');
        $user->assignRole('assessor');
        $this->actingAs($user);

        $category = AssessmentTypeCategory::create(['name' => 'Dashboard Category Test', 'order' => 1, 'is_active' => true]);
        $type = AssessmentType::create(['name' => 'Dashboard Category Type', 'code' => 'DASHBOARD_CATEGORY_TEST', 'is_active' => true, 'category_id' => $category->id]);
        AssessmentSection::create([
            'assessment_type_id' => $type->id,
            'name' => 'Section',
            'code' => 'dashboard_category_section_test',
            'section_type' => AssessmentSection::KIND_QUESTION_GROUP,
            'is_scored' => true,
            'order' => 1,
            'is_active' => true,
        ]);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'assessor_id' => $user->id,
        ]);

        $url = AssessmentResource::getUrl('dashboard', ['record' => $assessment->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Dashboard Category Test');
    }
}
