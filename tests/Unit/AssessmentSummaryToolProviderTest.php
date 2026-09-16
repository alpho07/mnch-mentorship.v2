<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chat\Tools\AssessmentSummaryToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentSummaryToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWithAssessmentAccess(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Test Admin']);
        $admin->assignRole('super_admin');
        $admin->givePermissionTo('view_any_assessment');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_get_assessment_status_counts_tool_returns_scoped_counts(): void
    {
        $admin = $this->actingAsAdminWithAssessmentAccess();

        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard Facility Assessment', 'version' => '1.0', 'is_active' => true]
        );
        Assessment::create([
            'facility_id' => Facility::factory()->create()->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
        ]);

        $tool = AssessmentSummaryToolProvider::statusCountsTool();

        $this->assertTrue($tool->authorize($admin));

        $result = $tool->execute([], $admin);

        $this->assertSame(1, $result['completed']);
    }

    public function test_tools_are_not_authorized_for_a_user_without_assessment_access(): void
    {
        $plainUser = User::factory()->create();

        $this->assertFalse(AssessmentSummaryToolProvider::statusCountsTool()->authorize($plainUser));
        $this->assertFalse(AssessmentSummaryToolProvider::readinessScoresTool()->authorize($plainUser));
        $this->assertFalse(AssessmentSummaryToolProvider::executiveSummaryTool()->authorize($plainUser));
    }
}
