<?php

namespace Tests\Feature;

use App\Filament\Pages\StalledMentorships;
use App\Models\MentorshipStallReminder;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StalledMentorshipsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Test Admin']);
        Permission::firstOrCreate(['name' => 'page_StalledMentorships', 'guard_name' => 'web']);
        $user->givePermissionTo('page_StalledMentorships');
        $this->actingAs($user);

        return $user;
    }

    public function test_page_is_hidden_from_users_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(StalledMentorships::canAccess());
    }

    public function test_page_loads_and_lists_a_stalled_mentorship(): void
    {
        $this->actingAsAdmin();
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Kakamega County Referral - Newborn Care',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->get(StalledMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('Kakamega County Referral - Newborn Care');
        $response->assertSee('No class created');
    }

    public function test_send_reminder_action_logs_and_notifies_the_mentor(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $mentor = User::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $mentor->id,
            'created_at' => now()->subDays(10),
        ]);

        Livewire::test(StalledMentorships::class)
            ->call('sendReminder', $training->id, 'no_class');

        $this->assertDatabaseHas('mentorship_stall_reminders', [
            'training_id' => $training->id,
            'bucket' => 'no_class',
        ]);
    }

    public function test_send_all_due_sends_for_every_due_mentorship(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(1),
        ]);

        Livewire::test(StalledMentorships::class)
            ->call('sendAllDue');

        $this->assertSame(1, MentorshipStallReminder::count());
    }

    public function test_page_shows_mentor_contact_details(): void
    {
        $this->actingAsAdmin();
        $mentor = User::factory()->create(['name' => 'Contact Mentor', 'email' => 'mentor@example.com', 'phone' => '0700000000']);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $mentor->id,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->get(StalledMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('Contact Mentor');
        $response->assertSee('mentor@example.com');
        $response->assertSee('0700000000');
    }

    public function test_deactivate_marks_the_mentorship_cancelled_and_reactivate_reverses_it(): void
    {
        $this->actingAsAdmin();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(StalledMentorships::class)->call('deactivateMentorship', $training->id);
        $this->assertSame('cancelled', $training->fresh()->status);

        Livewire::test(StalledMentorships::class)->call('reactivateMentorship', $training->id);
        $this->assertSame('draft', $training->fresh()->status);
    }

    public function test_delete_soft_deletes_and_restore_reverses_it(): void
    {
        $this->actingAsAdmin();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(StalledMentorships::class)->call('deleteMentorship', $training->id);
        $this->assertSoftDeleted('trainings', ['id' => $training->id]);

        Livewire::test(StalledMentorships::class)->call('restoreMentorship', $training->id);
        $this->assertDatabaseHas('trainings', ['id' => $training->id, 'deleted_at' => null]);
    }

    public function test_recently_actioned_section_lists_deactivated_and_deleted_mentorships(): void
    {
        $this->actingAsAdmin();
        $deactivated = Training::factory()->facilityMentorship()->create([
            'status' => 'cancelled',
            'title' => 'Deactivated One',
        ]);
        $deleted = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'Deleted One',
        ]);
        $deleted->delete();

        $response = $this->get(StalledMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('Deactivated One');
        $response->assertSee('Deleted One');
        $response->assertSeeInOrder(['Recently Actioned']);
    }
}
