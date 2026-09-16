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

class AssessmentResourceTest extends TestCase
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

    public function test_assessor_only_sees_their_own_assessments(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $assessorB = $this->makeUserWithRole('assessor');

        $assessmentA = $this->createAssessmentAs($assessorA);
        $assessmentB = $this->createAssessmentAs($assessorB);

        $this->actingAs($assessorA);
        $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assessmentA->id, $visibleIds);
        $this->assertNotContains($assessmentB->id, $visibleIds);
    }

    public function test_super_admin_admin_and_division_see_every_assessment(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $assessorB = $this->makeUserWithRole('assessor');
        $assessmentA = $this->createAssessmentAs($assessorA);
        $assessmentB = $this->createAssessmentAs($assessorB);

        foreach (['super_admin', 'admin', 'division'] as $role) {
            $privileged = $this->makeUserWithRole($role);
            $this->actingAs($privileged);

            $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

            $this->assertContains($assessmentA->id, $visibleIds, "Role {$role} should see assessor A's assessment");
            $this->assertContains($assessmentB->id, $visibleIds, "Role {$role} should see assessor B's assessment");
        }
    }

    public function test_assessor_cannot_view_another_assessors_record_via_direct_url(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $assessorB = $this->makeUserWithRole('assessor');
        $assessmentB = $this->createAssessmentAs($assessorB);

        $this->actingAs($assessorA);

        $response = $this->get(AssessmentResource::getUrl('dashboard', ['record' => $assessmentB]));

        $response->assertNotFound();
    }

    public function test_assessor_column_reflects_the_current_user_name_not_the_frozen_snapshot(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);

        $this->assertSame($assessor->name, $assessment->fresh()->assessor->name);

        // The user's name changes later — the live relationship should
        // reflect that, unlike the frozen assessor_name snapshot column.
        $assessor->update(['name' => 'Renamed Assessor']);

        $this->assertSame('Renamed Assessor', $assessment->fresh()->assessor->name);
    }

    public function test_dashboard_page_shows_the_assessors_current_name_via_the_relationship(): void
    {
        $assessor = $this->makeUserWithRole('assessor');
        $assessment = $this->createAssessmentAs($assessor);
        $assessor->update(['name' => 'Renamed On Dashboard']);

        $response = $this->get(AssessmentResource::getUrl('dashboard', ['record' => $assessment]));

        $response->assertOk();
        $response->assertSee('Renamed On Dashboard');
    }

    public function test_team_member_can_see_an_assessment_they_were_invited_to(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $assessorB = $this->makeUserWithRole('assessor');
        $assessmentA = $this->createAssessmentAs($assessorA);

        $assessmentA->teamMembers()->attach($assessorB->id, [
            'role' => 'member',
            'added_by' => $assessorA->id,
            'added_at' => now(),
        ]);

        $this->actingAs($assessorB);
        $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assessmentA->id, $visibleIds);
    }

    public function test_an_uninvited_assessor_still_cannot_see_another_assessors_record(): void
    {
        $assessorA = $this->makeUserWithRole('assessor');
        $outsider = $this->makeUserWithRole('assessor');
        $assessmentA = $this->createAssessmentAs($assessorA);

        $this->actingAs($outsider);
        $visibleIds = AssessmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($assessmentA->id, $visibleIds);
    }

    public function test_list_page_appends_mfl_code_to_the_facility_name_instead_of_a_separate_column(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin);
        $facility = Facility::factory()->create(['name' => 'Test Facility Name', 'mfl_code' => '99999']);

        Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        $response = $this->get(AssessmentResource::getUrl());

        $response->assertOk();
        $response->assertDontSee('MFL Code');
        $response->assertSee('Test Facility Name (99999)');
    }
}
