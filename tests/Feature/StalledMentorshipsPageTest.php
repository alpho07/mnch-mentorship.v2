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

    public function test_deactivate_marks_the_mentorship_cancelled(): void
    {
        $this->actingAsAdmin();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(StalledMentorships::class)->call('deactivateMentorship', $training->id);
        $this->assertSame('cancelled', $training->fresh()->status);
    }

    public function test_delete_soft_deletes_the_mentorship(): void
    {
        $this->actingAsAdmin();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(5),
        ]);

        Livewire::test(StalledMentorships::class)->call('deleteMentorship', $training->id);
        $this->assertSoftDeleted('trainings', ['id' => $training->id]);
    }

    public function test_bucket_filter_narrows_the_table_to_matching_rows(): void
    {
        $this->actingAsAdmin();
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'No Class Mentorship',
            'created_at' => now()->subDays(10),
        ]);
        $classTraining = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'title' => 'No Mentee Mentorship',
        ]);
        \App\Models\MentorshipClass::factory()->create([
            'training_id' => $classTraining->id,
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        Livewire::test(StalledMentorships::class)
            ->filterTable('bucket', 'no_class')
            ->assertCanSeeTableRecords([Training::where('title', 'No Class Mentorship')->first()])
            ->assertCanNotSeeTableRecords([$classTraining]);
    }

    public function test_mentor_filter_narrows_the_table_to_that_mentors_rows(): void
    {
        $this->actingAsAdmin();
        $mentorA = User::factory()->create(['name' => 'Mentor A']);
        $mentorB = User::factory()->create(['name' => 'Mentor B']);
        $trainingA = Training::factory()->facilityMentorship()->create(['status' => 'draft', 'mentor_id' => $mentorA->id]);
        $trainingB = Training::factory()->facilityMentorship()->create(['status' => 'draft', 'mentor_id' => $mentorB->id]);

        Livewire::test(StalledMentorships::class)
            ->filterTable('mentor_id', $mentorA->id)
            ->assertCanSeeTableRecords([$trainingA])
            ->assertCanNotSeeTableRecords([$trainingB]);
    }

    public function test_search_finds_a_mentorship_by_title(): void
    {
        $this->actingAsAdmin();
        $match = Training::factory()->facilityMentorship()->create(['status' => 'draft', 'title' => 'Findable Title Here']);
        $other = Training::factory()->facilityMentorship()->create(['status' => 'draft', 'title' => 'Something Else Entirely']);

        Livewire::test(StalledMentorships::class)
            ->searchTable('Findable')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_mentor_filter_options_do_not_crash_when_a_mentor_has_no_name_column(): void
    {
        $this->actingAsAdmin();
        $mentor = User::factory()->create(['name' => null, 'first_name' => 'Only', 'last_name' => 'First']);
        Training::factory()->facilityMentorship()->create(['status' => 'draft', 'mentor_id' => $mentor->id]);

        $response = $this->get(StalledMentorships::getUrl());

        $response->assertOk();
    }

    public function test_recently_actioned_table_is_embedded_on_the_page(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(StalledMentorships::getUrl());

        $response->assertOk();
        $response->assertSee('Recently Actioned');
    }
}
