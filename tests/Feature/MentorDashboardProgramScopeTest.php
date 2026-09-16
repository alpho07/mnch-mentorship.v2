<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorDashboard;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorDashboardProgramScopeTest extends TestCase
{
    use RefreshDatabase;

    private function grantDashboardAccess(User $user): void
    {
        Permission::firstOrCreate(['name' => 'page_MentorDashboard', 'guard_name' => 'web']);
        $user->givePermissionTo('page_MentorDashboard');
    }

    public function test_scoped_mentor_only_counts_trainings_for_their_program_when_setting_is_on(): void
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, true);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);

        $mentor = User::factory()->create(['program_scope' => 'emonc']);
        $mentor->assignRole('facility_mentor');
        $this->grantDashboardAccess($mentor);

        $t1 = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $emonc->id,
            'status' => 'active',
        ]);
        $c1 = MentorshipClass::factory()->create(['training_id' => $t1->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $c1->id, 'user_id' => User::factory()->create()->id]);

        $t2 = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $newborn->id,
            'status' => 'active',
        ]);
        $c2 = MentorshipClass::factory()->create(['training_id' => $t2->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $c2->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('kpis.active_mentorships', 1);
    }

    public function test_scoped_mentor_counts_all_trainings_when_setting_is_off(): void
    {
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, false);

        $emonc = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $newborn = Program::factory()->create(['name' => 'Newborn Care']);

        $mentor = User::factory()->create(['program_scope' => 'emonc']);
        $mentor->assignRole('facility_mentor');
        $this->grantDashboardAccess($mentor);

        $t1 = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $emonc->id,
            'status' => 'active',
        ]);
        $c1 = MentorshipClass::factory()->create(['training_id' => $t1->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $c1->id, 'user_id' => User::factory()->create()->id]);

        $t2 = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'program_id' => $newborn->id,
            'status' => 'active',
        ]);
        $c2 = MentorshipClass::factory()->create(['training_id' => $t2->id, 'status' => 'active']);
        ClassParticipant::factory()->create(['mentorship_class_id' => $c2->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($mentor);

        Livewire::test(MentorDashboard::class)
            ->assertSet('kpis.active_mentorships', 2);
    }
}
