<?php

namespace Tests\Feature\HeadDrmh;

use App\Filament\Pages\HeadDrmhDashboard;
use App\Filament\Pages\HeadDrmhReviewMentee;
use App\Filament\Resources\MentorshipResource\Pages\ManageClassMentees;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NonEmoncCertificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{training: Training, class: MentorshipClass, participant: ClassParticipant}
     */
    private function makeReadyNonEmoncParticipant(): array
    {
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $mentor = User::factory()->create();
        $facility = Facility::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id, 'mentor_id' => $mentor->id, 'facility_id' => $facility->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id, 'program_module_id' => $programModule->id, 'status' => 'in_progress',
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id, 'user_id' => $mentee->id, 'status' => 'completed',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $participant->id, 'class_module_id' => $classModule->id,
            'status' => 'completed', 'video_review_status' => 'pending',
        ]);

        return compact('training', 'class', 'participant');
    }

    private function actingAsHeadDrmh(): User
    {
        $user = User::factory()->create(['name' => 'Head DRMH']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhDashboard', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhReviewMentee', 'guard_name' => 'web']);
        $user->givePermissionTo(['page_HeadDrmhDashboard', 'page_HeadDrmhReviewMentee']);
        $this->actingAs($user);

        return $user;
    }

    public function test_completed_non_emonc_participant_appears_in_head_drmh_pending_list(): void
    {
        $this->actingAsHeadDrmh();
        ['participant' => $participant] = $this->makeReadyNonEmoncParticipant();

        $component = Livewire::test(HeadDrmhDashboard::class);

        $pendingIds = collect($component->get('pendingList'))->pluck('id');

        $this->assertTrue($pendingIds->contains($participant->id));
    }

    public function test_head_drmh_can_certify_a_ready_non_emonc_participant_with_no_prior_mentor_approval(): void
    {
        $this->actingAsHeadDrmh();
        ['participant' => $participant] = $this->makeReadyNonEmoncParticipant();

        $this->assertNull($participant->mentor_approved_at);

        Livewire::withQueryParams(['participant' => $participant->id])
            ->test(HeadDrmhReviewMentee::class)
            ->call('certify');

        $this->assertNotNull($participant->fresh()->head_drmh_approved_at);
    }

    public function test_roster_page_certify_action_is_visible_and_works_for_a_ready_non_emonc_participant(): void
    {
        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'page_HeadDrmhDashboard', 'guard_name' => 'web']);
        $mentor->givePermissionTo(['view_any_mentorship::training', 'page_HeadDrmhDashboard']);
        $this->actingAs($mentor);

        ['training' => $training, 'class' => $class, 'participant' => $participant] = $this->makeReadyNonEmoncParticipant();
        $training->update(['mentor_id' => $mentor->id]);

        Livewire::test(ManageClassMentees::class, [
            'training' => $training,
            'class' => $class,
        ])->callTableAction('head_drmh_certify', $participant);

        $this->assertNotNull($participant->fresh()->head_drmh_approved_at);
    }
}
