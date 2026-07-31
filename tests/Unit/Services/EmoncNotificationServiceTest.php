<?php

namespace Tests\Unit\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use App\Services\EmoncNotificationService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class EmoncNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProgress(string $programName): array
    {
        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => $programName]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Essential Newborn Care']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        return compact('mentee', 'progress');
    }

    public function test_mentor_recommendation_written_notifies_newborn_care_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Newborn Care');

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }

    public function test_mentor_recommendation_written_notifies_infant_child_care_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Infant and Child Care');

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }

    public function test_module_completed_notifies_mentee(): void
    {
        NotificationFacade::fake();
        ['mentee' => $mentee, 'progress' => $progress] = $this->makeProgress('Newborn Care');

        app(EmoncNotificationService::class)->moduleCompleted($progress);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Module Completed'
                && str_contains($notification->data['body'], 'Essential Newborn Care');
        });
    }

    public function test_no_notification_sent_when_participant_has_no_user(): void
    {
        NotificationFacade::fake();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);
        // Soft-delete the user rather than pointing at a non-existent id — class_participants.user_id
        // has an enforced foreign key, so an id with no matching row would fail at insert time.
        // A soft-deleted user still satisfies the FK but is excluded by User's default scope, so
        // $participant->user resolves to null exactly like the "no user" case this test targets.
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'active',
        ]);
        $mentee->delete();
        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        app(EmoncNotificationService::class)->mentorRecommendationWritten($progress);
        app(EmoncNotificationService::class)->moduleCompleted($progress);

        NotificationFacade::assertNothingSent();
    }
}
