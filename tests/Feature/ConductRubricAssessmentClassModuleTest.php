<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ReviewModuleMentee;
use App\Filament\Resources\RubricAssessmentResource\Pages\ConductRubricAssessment;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricAssessment;
use App\Models\RubricItem;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConductRubricAssessmentClassModuleTest extends TestCase
{
    use RefreshDatabase;

    private function getViewData(ReviewModuleMentee $page): array
    {
        $reflection = new \ReflectionMethod($page, 'getViewData');
        $reflection->setAccessible(true);

        return $reflection->invoke($page);
    }

    private function actingAsMentor(): User
    {
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Mentor User']);
        $user->assignRole('facility_mentor');

        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_rubric::assessment', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_rubric::assessment', 'guard_name' => 'web']);
        $user->givePermissionTo([
            'view_any_mentorship::training',
            'view_any_rubric::assessment',
            'create_rubric::assessment',
        ]);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @return array{training: Training, class: MentorshipClass, classModule: ClassModule, participant: ClassParticipant, rubric: ModuleRubric, mentor: User}
     */
    private function buildScenario(User $mentor): array
    {
        $program = Program::factory()->create();
        $programModule = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $mentor->id, 'program_id' => $program->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        $classModule = ClassModule::factory()->create([
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);

        $rubric = ModuleRubric::create([
            'program_module_id' => $programModule->id,
            'title' => 'Hands-on Rubric',
            'total_marks' => 2,
            'pass_marks' => 2,
            'pass_percentage' => 100,
            'order_sequence' => 1,
            'is_active' => true,
        ]);
        RubricItem::create(['module_rubric_id' => $rubric->id, 'description' => 'Item 1', 'order_sequence' => 1, 'is_active' => true]);
        RubricItem::create(['module_rubric_id' => $rubric->id, 'description' => 'Item 2', 'order_sequence' => 2, 'is_active' => true]);

        return compact('training', 'class', 'classModule', 'participant', 'rubric', 'mentor');
    }

    public function test_conduct_assessment_url_includes_class_module_id(): void
    {
        $mentor = $this->actingAsMentor();
        $scenario = $this->buildScenario($mentor);

        $component = Livewire::test(ReviewModuleMentee::class, [
            'training' => $scenario['training'],
            'class' => $scenario['class'],
            'module' => $scenario['classModule'],
            'participant' => $scenario['participant'],
        ]);

        $viewData = $this->getViewData($component->instance());

        $this->assertStringContainsString('class_module_id='.$scenario['classModule']->id, $viewData['conductAssessmentUrl']);
    }

    public function test_submitted_assessment_is_linked_to_the_class_module(): void
    {
        $mentor = $this->actingAsMentor();
        $scenario = $this->buildScenario($mentor);

        $component = Livewire::withQueryParams([
            'rubric_id' => $scenario['rubric']->id,
            'mentee_id' => $scenario['participant']->user_id,
            'class_module_id' => $scenario['classModule']->id,
        ])->test(ConductRubricAssessment::class);

        $component->call('proceedToScoring');

        foreach ($scenario['rubric']->items()->pluck('id') as $itemId) {
            $component->call('toggleItem', $itemId);
        }

        $component->call('submitAssessment');

        $assessment = RubricAssessment::where('mentee_id', $scenario['participant']->user_id)->first();

        $this->assertNotNull($assessment);
        $this->assertSame($scenario['classModule']->id, $assessment->class_module_id);
    }

    public function test_rubric_lookup_on_review_page_is_scoped_to_this_class_module(): void
    {
        $mentor = $this->actingAsMentor();
        $scenario = $this->buildScenario($mentor);

        // An assessment recorded against a DIFFERENT class/class_module for
        // the same mentee + rubric — must not leak into this class's review.
        $otherClass = MentorshipClass::factory()->create(['training_id' => $scenario['training']->id]);
        $otherClassModule = ClassModule::factory()->create([
            'mentorship_class_id' => $otherClass->id,
            'program_module_id' => $scenario['classModule']->program_module_id,
        ]);
        RubricAssessment::create([
            'module_rubric_id' => $scenario['rubric']->id,
            'class_module_id' => $otherClassModule->id,
            'mentee_id' => $scenario['participant']->user_id,
            'mentor_id' => $mentor->id,
            'score' => 2,
            'passed' => true,
            'assessed_at' => now(),
        ]);

        $component = Livewire::test(ReviewModuleMentee::class, [
            'training' => $scenario['training'],
            'class' => $scenario['class'],
            'module' => $scenario['classModule'],
            'participant' => $scenario['participant'],
        ]);

        $this->assertNull($this->getViewData($component->instance())['latestRubricAssessment']);
    }
}
