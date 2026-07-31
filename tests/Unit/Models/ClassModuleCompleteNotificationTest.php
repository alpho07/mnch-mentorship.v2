<?php

namespace Tests\Unit\Models;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class ClassModuleCompleteNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_notifies_attended_mentees_but_not_absent_ones(): void
    {
        NotificationFacade::fake();

        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'name' => 'Essential Newborn Care']);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'in_progress',
        ]);

        $attendedMentee = User::factory()->create();
        $attendedParticipant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $attendedMentee->id,
            'status' => 'active',
        ]);
        MenteeModuleProgress::create([
            'class_participant_id' => $attendedParticipant->id,
            'class_module_id' => $classModule->id,
            'status' => 'in_progress',
        ]);

        $absentMentee = User::factory()->create();
        ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $absentMentee->id,
            'status' => 'active',
        ]);
        // No MenteeModuleProgress row at all for the absent mentee — never confirmed attendance.

        $classModule->complete();

        NotificationFacade::assertSentTo($attendedMentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Module Completed';
        });
        NotificationFacade::assertNotSentTo($absentMentee, DatabaseNotification::class);
    }
}
