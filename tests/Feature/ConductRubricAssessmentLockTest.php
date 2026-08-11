<?php

namespace Tests\Feature;

use App\Filament\Resources\RubricAssessmentResource\Pages\ConductRubricAssessment;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConductRubricAssessmentLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{classModule: ClassModule, rubric: ModuleRubric, mentee: User, participant: ClassParticipant, progress: MenteeModuleProgress, mentor: User}
     */
    private function buildScenario(string $progressStatus): array
    {
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)']);
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);
        $training = Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $module->id,
        ]);

        $rubric = ModuleRubric::create([
            'program_module_id' => $module->id,
            'title' => 'Practical Assessment',
            'total_marks' => 5,
            'pass_marks' => 3,
            'order_sequence' => 1,
            'is_active' => true,
        ]);

        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);
        $mentor = User::factory()->create();

        $progress = MenteeModuleProgress::create([
            'class_participant_id' => $participant->id,
            'class_module_id' => $classModule->id,
            'status' => $progressStatus,
            'completed_at' => $progressStatus === 'completed' ? now() : null,
            // The rubric-assessment lock checks the video's own pass state
            // (not just `status`, which the unrelated Activity Completion
            // Matrix can also set) — so a "locked" fixture needs this too.
            'video_review_status' => $progressStatus === 'completed' ? 'passed' : 'pending',
        ]);

        return compact('classModule', 'rubric', 'mentee', 'participant', 'progress', 'mentor');
    }

    /**
     * mount() only reads its #[Url] properties from actual query-string
     * state supplied by Livewire's request lifecycle, so we set them
     * directly here (equivalent to what Livewire would have hydrated) and
     * drive the same public methods a real request would call.
     */
    private function buildPage(int $rubricId, int $menteeId, int $classModuleId, User $mentor): ConductRubricAssessment
    {
        \Illuminate\Support\Facades\Auth::login($mentor);

        $page = new ConductRubricAssessment();
        $page->module_rubric_id = $rubricId;
        $page->mentee_id = $menteeId;
        $page->class_module_id = $classModuleId;
        $page->mount();

        return $page;
    }

    public function test_arriving_at_an_already_locked_module_stays_on_step_one_with_a_locked_flag(): void
    {
        ['classModule' => $classModule, 'rubric' => $rubric, 'mentee' => $mentee, 'mentor' => $mentor] = $this->buildScenario('completed');

        $page = $this->buildPage($rubric->id, $mentee->id, $classModule->id, $mentor);

        $this->assertSame(1, $page->step);
        $this->assertTrue($page->isModuleLocked);
    }

    public function test_proceeding_to_scoring_is_blocked_once_locked(): void
    {
        ['classModule' => $classModule, 'rubric' => $rubric, 'mentee' => $mentee, 'mentor' => $mentor] = $this->buildScenario('completed');

        \Illuminate\Support\Facades\Auth::login($mentor);
        $page = new ConductRubricAssessment();
        // Arrives via the manual picker (no preset mentee/module), so mount()
        // doesn't auto-advance — proceedToScoring() itself must catch the lock.
        $page->mount();
        $page->module_rubric_id = $rubric->id;
        $page->mentee_id = $mentee->id;
        $page->class_module_id = $classModule->id;
        $page->mentor_id = $mentor->id;
        $page->assessed_at = now()->format('Y-m-d\TH:i');

        $page->proceedToScoring();

        $this->assertSame(1, $page->step);
        $this->assertTrue($page->isModuleLocked);
    }

    public function test_submitting_is_rejected_if_the_module_locks_after_the_page_was_opened(): void
    {
        ['classModule' => $classModule, 'rubric' => $rubric, 'mentee' => $mentee, 'progress' => $progress, 'mentor' => $mentor] = $this->buildScenario('in_progress');

        // Page opens while the module is still open — mount() advances to
        // step 2 and loads the rubric normally.
        $page = $this->buildPage($rubric->id, $mentee->id, $classModule->id, $mentor);
        $this->assertSame(2, $page->step);
        $this->assertNotNull($page->rubric);

        // The video gets passed out-of-band (e.g. a different mentor
        // reviewed it moments earlier) while this mentor still has the
        // page open for what they thought was a fresh assessment.
        $progress->update(['video_review_status' => 'passed']);

        $page->submitAssessment();

        $this->assertTrue($page->isModuleLocked);
        $this->assertDatabaseCount('rubric_assessments', 0);
        $this->assertSame('passed', $progress->fresh()->video_review_status);
    }
}
