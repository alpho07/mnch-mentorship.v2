<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentManageTeamActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessor(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        $user->assignRole('assessor');
        $user->givePermissionTo('view_any_assessment');

        return $user;
    }

    public function test_manage_team_action_invites_selected_assessors(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $invitee = $this->makeAssessor('Invitee Assessor');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        Livewire::test(ListAssessments::class)
            ->callTableAction('manage_team', $assessment, data: [
                'member_ids' => [$invitee->id],
            ]);

        $this->assertTrue($assessment->fresh()->isTeamMember($invitee->id));
    }

    public function test_manage_team_action_invites_a_non_assessor_and_grants_them_the_role(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');

        $this->actingAs($lead);
        $facility = Facility::factory()->create();
        $assessment = Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
        ]);

        $mentor = User::factory()->create(['name' => 'Facility Mentor']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentor->assignRole('facility_mentor');

        Livewire::test(ListAssessments::class)
            ->callTableAction('manage_team', $assessment, data: [
                'member_ids' => [$mentor->id],
            ]);

        $mentor->refresh();
        $this->assertTrue($assessment->fresh()->isTeamMember($mentor->id));
        $this->assertTrue($mentor->hasRole('assessor'));
        $this->assertTrue($mentor->hasRole('facility_mentor'));
    }

    public function test_manage_team_action_is_hidden_for_a_regular_team_member(): void
    {
        $lead = $this->makeAssessor('Lead Assessor');
        $member = $this->makeAssessor('Member Assessor');

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

        $this->actingAs($member);

        Livewire::test(ListAssessments::class)
            ->assertTableActionHidden('manage_team', $assessment);
    }
}
