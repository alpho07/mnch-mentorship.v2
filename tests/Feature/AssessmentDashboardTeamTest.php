<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentDashboardTeamTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole($role);
        $user->givePermissionTo('view_any_assessment');

        return $user;
    }

    public function test_dashboard_shows_team_lead_and_members_by_name(): void
    {
        $lead = $this->makeUserWithRole('assessor', 'Lead Assessor Jane');
        $member = $this->makeUserWithRole('assessor', 'Member Assessor John');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $response = $this->get(AssessmentResource::getUrl('dashboard', ['record' => $assessment]));

        $response->assertOk();
        $response->assertSeeInOrder(['Lead Assessor Jane', 'Lead']);
        $response->assertSeeInOrder(['Member Assessor John', 'Member']);
    }

    public function test_dashboard_shows_only_the_lead_when_no_members_have_been_invited(): void
    {
        $lead = $this->makeUserWithRole('assessor', 'Solo Assessor');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        $response = $this->get(AssessmentResource::getUrl('dashboard', ['record' => $assessment]));

        $response->assertOk();
        $response->assertSeeInOrder(['Solo Assessor', 'Lead']);
    }
}
