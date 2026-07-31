<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ManageModuleMentees;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManageModuleMenteesNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_writing_a_recommendation_notifies_the_mentee(): void
    {
        NotificationFacade::fake();

        $mentor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $mentor->givePermissionTo('view_any_mentorship::training');

        $mentee = User::factory()->create();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
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
        // The write_recommendation action is gated by ManageModuleMentees::isPresent(), which
        // requires either an in_progress/completed progress row or a ClassAttendance record —
        // without one, the action is invisible and callTableAction() would fail.
        ClassAttendance::create([
            'class_id' => $class->id,
            'class_module_id' => $classModule->id,
            'user_id' => $mentee->id,
            'marked_by' => $mentor->id,
            'marked_at' => now(),
            'source' => 'manual',
        ]);

        $this->actingAs($mentor);

        Livewire::test(ManageModuleMentees::class, [
            'training' => $training,
            'class' => $class,
            'module' => $classModule,
        ])
            ->callTableAction('write_recommendation', $participant, data: [
                'mentor_recommendation' => 'Great progress on newborn resuscitation technique.',
            ]);

        NotificationFacade::assertSentTo($mentee, DatabaseNotification::class, function ($notification) {
            return $notification->data['title'] === 'Mentor Feedback Received';
        });
    }
}
