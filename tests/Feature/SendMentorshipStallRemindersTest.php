<?php

namespace Tests\Feature;

use App\Models\MentorshipStallReminder;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMentorshipStallRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminders_when_enabled(): void
    {
        Mail::fake();
        Setting::setBool(Setting::STALL_REMINDER_ENABLED, true);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('mentorships:send-stall-reminders')->assertSuccessful();

        $this->assertSame(1, MentorshipStallReminder::count());
    }

    public function test_does_nothing_when_disabled(): void
    {
        Mail::fake();
        Setting::setBool(Setting::STALL_REMINDER_ENABLED, false);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('mentorships:send-stall-reminders')->assertSuccessful();

        $this->assertSame(0, MentorshipStallReminder::count());
    }

    public function test_force_flag_sends_even_when_disabled(): void
    {
        Mail::fake();
        Setting::setBool(Setting::STALL_REMINDER_ENABLED, false);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('mentorships:send-stall-reminders --force')->assertSuccessful();

        $this->assertSame(1, MentorshipStallReminder::count());
    }
}
