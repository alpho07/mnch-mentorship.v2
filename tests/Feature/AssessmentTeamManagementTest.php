<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['name' => "Test {$role}"]);
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole($role);
        $user->givePermissionTo('view_any_assessment');

        return $user;
    }

    private function createAssessmentAs(User $assessor): Assessment
    {
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);
    }

    public function test_creating_an_assessment_auto_attaches_the_assessor_as_team_lead(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $this->assertTrue($assessment->isTeamLead($assessor->id));
        $this->assertTrue($assessment->canManageTeam($assessor->id));
    }

    public function test_a_regular_team_member_cannot_manage_the_team(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $member = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $assessor->id,
            'added_at' => now(),
        ]);

        $this->assertTrue($assessment->isTeamMember($member->id));
        $this->assertFalse($assessment->canManageTeam($member->id));
    }

    public function test_an_uninvited_assessor_is_not_a_team_member(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $outsider = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $this->assertFalse($assessment->isTeamMember($outsider->id));
        $this->assertFalse($assessment->canManageTeam($outsider->id));
    }

    public function test_super_admin_admin_and_division_can_always_manage_the_team(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        foreach (['super_admin', 'admin', 'division'] as $role) {
            $privileged = $this->makeUserWithRole($role);
            $this->assertTrue(
                $assessment->canManageTeam($privileged->id),
                "Role {$role} should be able to manage the team"
            );
        }
    }

    public function test_lock_and_unlock_toggle_is_locked_and_stamp_locked_by(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $assessment->lock($assessor->id);
        $this->assertTrue($assessment->fresh()->isLocked());
        $this->assertSame($assessor->id, $assessment->fresh()->locked_by);

        $assessment->unlock();
        $this->assertFalse($assessment->fresh()->isLocked());
        $this->assertNull($assessment->fresh()->locked_by);
    }

    public function test_adding_a_member_to_a_legacy_assessment_promotes_the_original_assessor_to_lead(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $newMember = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        // Simulate a pre-team-management assessment: no pivot rows at all,
        // as if it were created before the `created` boot hook existed.
        $assessment->teamMembers()->detach();
        $this->assertTrue($assessment->teamLeads()->doesntExist());

        app(AssessmentTeamService::class)->addMember($assessment, $newMember->id, $assessor->id);

        $this->assertTrue($assessment->fresh()->isTeamLead($assessor->id));
        $this->assertTrue($assessment->fresh()->isTeamMember($newMember->id));
    }

    public function test_adding_a_non_assessor_user_grants_them_the_assessor_role(): void
    {
        $lead = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($lead);

        $mentor = User::factory()->create(['name' => 'Some Mentor']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentor->assignRole('facility_mentor');

        $this->assertFalse($mentor->hasRole('assessor'));

        app(AssessmentTeamService::class)->addMember($assessment, $mentor->id, $lead->id);

        $mentor->refresh();
        $this->assertTrue($assessment->fresh()->isTeamMember($mentor->id));
        $this->assertTrue($mentor->hasRole('assessor'));
        $this->assertTrue($mentor->hasRole('facility_mentor'), 'existing roles must not be removed');
    }

    public function test_adding_an_existing_assessor_does_not_duplicate_their_role(): void
    {
        $lead = $this->makeUserWithRole('assessor');
        $existingAssessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($lead);

        app(AssessmentTeamService::class)->addMember($assessment, $existingAssessor->id, $lead->id);

        $existingAssessor->refresh();
        $this->assertCount(1, $existingAssessor->roles()->where('name', 'assessor')->get());
    }

    public function test_search_eligible_users_finds_users_regardless_of_role(): void
    {
        $lead = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($lead);

        $mentor = User::factory()->create(['name' => 'Searchable Mentor']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentor->assignRole('facility_mentor');

        $results = app(AssessmentTeamService::class)->searchEligibleUsers($assessment, 'Searchable Mentor');

        $this->assertTrue($results->contains('id', $mentor->id));
    }

    public function test_search_eligible_users_excludes_existing_team_members(): void
    {
        $lead = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($lead);

        $results = app(AssessmentTeamService::class)->searchEligibleUsers($assessment, 'Test assessor');

        $this->assertFalse($results->contains('id', $lead->id));
    }

    public function test_search_eligible_users_works_before_the_assessment_exists(): void
    {
        $someone = User::factory()->create(['name' => 'Not Yet Invited', 'status' => 'active']);

        $results = app(AssessmentTeamService::class)->searchEligibleUsers(null, 'Not Yet Invited');

        $this->assertTrue($results->contains('id', $someone->id));
    }
}
