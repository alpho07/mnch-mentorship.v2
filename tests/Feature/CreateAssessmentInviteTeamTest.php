<?php

namespace Tests\Feature;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAssessmentInviteTeamTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAssessor(): User
    {
        $user = User::factory()->create(['name' => 'Creating Assessor']);
        Role::firstOrCreate(['name' => 'assessor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_assessment', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_assessment', 'create_assessment']);
        $this->actingAs($user);

        return $user;
    }

    public function test_creating_an_assessment_with_invited_members_adds_them_to_the_team(): void
    {
        $creator = $this->actingAsAssessor();
        $facility = Facility::factory()->create();
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->firstOrFail();

        $mentor = User::factory()->create(['name' => 'Invited Mentor', 'status' => 'active']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $mentor->assignRole('facility_mentor');

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
                'member_ids' => [$mentor->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $assessment = Assessment::where('facility_id', $facility->id)->firstOrFail();

        $this->assertTrue($assessment->isTeamLead($creator->id));
        $this->assertTrue($assessment->isTeamMember($mentor->id));
        $mentor->refresh();
        $this->assertTrue($mentor->hasRole('assessor'));
        $this->assertTrue($mentor->hasRole('facility_mentor'));
    }

    public function test_creating_an_assessment_without_inviting_anyone_still_works(): void
    {
        $creator = $this->actingAsAssessor();
        $facility = Facility::factory()->create();
        $type = AssessmentType::where('code', 'STANDARD_FACILITY_ASSESSMENT')->firstOrFail();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'assessment_type_id' => $type->id,
                'assessment_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $assessment = Assessment::where('facility_id', $facility->id)->firstOrFail();

        $this->assertTrue($assessment->isTeamLead($creator->id));
        $this->assertCount(1, $assessment->teamMembers);
    }
}
