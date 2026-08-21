<?php

namespace Tests\Unit\Models;

use App\Mail\EmoncNotificationMail;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MentorshipClassStartNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function readyClass(): MentorshipClass
    {
        $training = Training::factory()->facilityMentorship()->create();
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'draft']);
        ClassModule::factory()->create(['mentorship_class_id' => $class->id]);
        $mentee = User::factory()->create();
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'invitation_sent_at' => now(),
        ]);

        return $class;
    }

    public function test_start_sends_a_notification_by_default(): void
    {
        Mail::fake();
        $class = $this->readyClass();

        $class->start();

        Mail::assertQueued(EmoncNotificationMail::class);
        $this->assertSame('active', $class->fresh()->status);
    }

    public function test_start_with_notify_false_sends_no_notification(): void
    {
        Mail::fake();
        $class = $this->readyClass();

        $class->start(notify: false);

        Mail::assertNothingQueued();
        $this->assertSame('active', $class->fresh()->status);
    }
}
