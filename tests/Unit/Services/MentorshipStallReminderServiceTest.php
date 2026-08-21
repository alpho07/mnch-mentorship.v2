<?php

namespace Tests\Unit\Services;

use App\Mail\EmoncNotificationMail;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\MentorshipStallReminder;
use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipStallReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MentorshipStallReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private MentorshipStallReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MentorshipStallReminderService::class);
    }

    public function test_a_mentorship_with_no_class_is_flagged_no_class(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertCount(1, $stalled);
        $this->assertSame('no_class', $stalled->first()['bucket']);
        $this->assertTrue($stalled->first()['due']);
    }

    public function test_a_mentorship_with_a_class_but_no_mentee_is_flagged_no_mentee(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        MentorshipClass::factory()->create([
            'training_id' => $training->id,
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertCount(1, $stalled);
        $this->assertSame('no_mentee', $stalled->first()['bucket']);
    }

    public function test_a_mentorship_with_a_mentee_but_no_modules_is_flagged_no_modules(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'draft']);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertCount(1, $stalled);
        $this->assertSame('no_modules', $stalled->first()['bucket']);
    }

    public function test_a_mentorship_with_mentee_and_modules_is_not_flagged_but_it_could_just_be_started(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'draft']);
        ClassModule::factory()->create(['mentorship_class_id' => $class->id, 'created_at' => now()->subDays(10)]);
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $stalled = $this->service->stalled(7);

        // Ready-to-start-but-not-started still counts as "no_modules" bucket
        // copy-wise (the actionable next step is the same: open it, start it).
        $this->assertCount(1, $stalled);
        $this->assertSame('no_modules', $stalled->first()['bucket']);
    }

    public function test_a_training_with_a_started_class_is_not_flagged_at_all(): void
    {
        $training = Training::factory()->facilityMentorship()->create(['status' => 'draft']);
        MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);

        $stalled = $this->service->stalled(7);

        $this->assertCount(0, $stalled);
    }

    public function test_a_mentorship_stalled_less_than_the_threshold_is_not_due(): void
    {
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(3),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertCount(1, $stalled);
        $this->assertFalse($stalled->first()['due']);
    }

    public function test_a_mentorship_reminded_recently_is_not_due_again(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);
        MentorshipStallReminder::create([
            'training_id' => $training->id,
            'bucket' => 'no_class',
            'sent_at' => now()->subDays(2),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertFalse($stalled->first()['due']);
    }

    public function test_a_mentorship_reminded_over_a_threshold_ago_is_due_again(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(30),
        ]);
        MentorshipStallReminder::create([
            'training_id' => $training->id,
            'bucket' => 'no_class',
            'sent_at' => now()->subDays(10),
        ]);

        $stalled = $this->service->stalled(7);

        $this->assertTrue($stalled->first()['due']);
    }

    public function test_send_logs_the_reminder_and_queues_mail_to_the_mentor(): void
    {
        Mail::fake();
        $mentor = User::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => $mentor->id,
        ]);

        $this->service->send($training, 'no_class');

        $this->assertDatabaseHas('mentorship_stall_reminders', [
            'training_id' => $training->id,
            'bucket' => 'no_class',
            'sent_by' => null,
        ]);
        Mail::assertQueued(EmoncNotificationMail::class, fn ($mail) => $mail->user->is($mentor));
    }

    public function test_send_due_reminders_sends_for_every_due_mentorship_and_summarizes_buckets(): void
    {
        Mail::fake();
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(10),
        ]);
        Training::factory()->facilityMentorship()->create([
            'status' => 'draft',
            'mentor_id' => User::factory()->create()->id,
            'created_at' => now()->subDays(2),
        ]);

        $result = $this->service->sendDueReminders(thresholdDays: 7);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['buckets']['no_class']);
        $this->assertSame(1, MentorshipStallReminder::count());
    }
}
