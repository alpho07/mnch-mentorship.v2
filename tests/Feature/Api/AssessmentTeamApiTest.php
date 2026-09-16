<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentTeamApiTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'update_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo(['view_assessment', 'update_assessment']);

        return $user;
    }

    private function createAssessmentAs(User $assessor): Assessment
    {
        $this->actingAs($assessor);
        $facility = Facility::factory()->create();

        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        // Requests below authenticate via a Sanctum bearer token, but a
        // lingering session from actingAs() takes priority over that token
        // (EnsureFrontendRequestsAreStateful) — log out so the token wins.
        auth()->logout();

        return $assessment;
    }

    public function test_show_returns_the_lead_and_team_members(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->getJson("/api/v1/assessments/{$assessment->id}/team");

        $response->assertSuccessful();
        $response->assertJsonPath('lead_assessor.id', $lead->id);
        $response->assertJsonPath('can_manage_team', true);
    }

    public function test_eligible_rejects_a_non_manager(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $outsider = $this->makeAssessor('Outsider Assessor');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($outsider))
            ->getJson("/api/v1/assessments/{$assessment->id}/team/eligible");

        $response->assertForbidden();
    }

    public function test_store_adds_team_members_and_returns_the_updated_payload(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $invitee = $this->makeAssessor('Invitee Assessor');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->postJson("/api/v1/assessments/{$assessment->id}/team", [
                'member_ids' => [$invitee->id],
            ]);

        $response->assertSuccessful();
        $this->assertTrue($assessment->fresh()->isTeamMember($invitee->id));
        $response->assertJsonPath('team_members.0.id', $invitee->id);
    }
}
