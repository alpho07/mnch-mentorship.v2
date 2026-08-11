<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentApiTeamScopingTest extends TestCase
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
        $user->assignRole('assessor');
        $user->givePermissionTo('view_assessment');

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

    public function test_index_includes_assessments_the_user_was_added_to_as_a_team_member(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($member))
            ->getJson('/api/v1/assessments');

        $response->assertSuccessful();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($assessment->id, $ids);
    }

    public function test_index_still_excludes_assessments_the_user_has_no_relationship_to(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $outsider = $this->makeAssessor('Outsider Assessor');
        $assessment = $this->createAssessmentAs($lead);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($outsider))
            ->getJson('/api/v1/assessments');

        $response->assertSuccessful();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($assessment->id, $ids);
    }

    public function test_show_payload_includes_team_and_lead_assessor_fields(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');
        $assessment = $this->createAssessmentAs($lead);
        $assessment->teamMembers()->attach($member->id, [
            'role' => 'member',
            'added_by' => $lead->id,
            'added_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($lead))
            ->getJson("/api/v1/assessments/{$assessment->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('data.lead_assessor.id', $lead->id);
        $response->assertJsonPath('data.can_manage_team', true);
        $memberIds = collect($response->json('data.team_members'))->pluck('id')->all();
        $this->assertContains($member->id, $memberIds);
    }
}
