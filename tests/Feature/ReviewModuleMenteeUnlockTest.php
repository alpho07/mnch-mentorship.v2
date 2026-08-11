<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ReviewModuleMentee;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ReviewModuleMenteeUnlockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{page: ReviewModuleMentee, progress: MenteeModuleProgress, participant: ClassParticipant}
     */
    private function buildScenario(string $progressStatus, bool $mentorApproved = false): array
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $mentor = User::factory()->create();
        $training = Training::factory()->facilityMentorship()->create([
            'program_id' => $program->id,
            'mentor_id' => $mentor->id,
        ]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
        ]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'mentor_approved_at' => $mentorApproved ? now() : null,
        ]);

        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => $progressStatus,
            'completed_at' => $progressStatus === 'completed' ? now() : null,
            // isLockedForMentee() checks the mentee's own steps, not just
            // `status` — this scenario has no quizzes, so a passed video is
            // the only thing needed to represent "genuinely locked".
            'video_review_status' => $progressStatus === 'completed' ? 'passed' : 'pending',
        ]);

        Auth::login($mentor);
        $page = new ReviewModuleMentee();
        $page->mount($training, $class, $classModule, $participant);

        return compact('page', 'progress', 'participant');
    }

    public function test_mentor_can_unlock_a_completed_module(): void
    {
        ['page' => $page, 'progress' => $progress] = $this->buildScenario('completed');

        $page->unlockModule();

        $this->assertSame('in_progress', $progress->fresh()->status);
        $this->assertNull($progress->fresh()->completed_at);
        $this->assertFalse($page->progress->isLockedForMentee());
    }

    public function test_unlocking_a_module_that_is_not_locked_is_a_noop(): void
    {
        ['page' => $page, 'progress' => $progress] = $this->buildScenario('in_progress');

        $page->unlockModule();

        $this->assertSame('in_progress', $progress->fresh()->status);
    }

    public function test_unlocking_is_refused_once_the_mentee_is_mentor_approved(): void
    {
        ['page' => $page, 'progress' => $progress] = $this->buildScenario('completed', mentorApproved: true);

        $page->unlockModule();

        $this->assertSame('completed', $progress->fresh()->status);
        $this->assertNotNull($progress->fresh()->completed_at);
    }
}
