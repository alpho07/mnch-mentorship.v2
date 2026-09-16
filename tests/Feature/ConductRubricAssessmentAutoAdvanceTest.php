<?php

namespace Tests\Feature;

use App\Filament\Resources\RubricAssessmentResource\Pages\ConductRubricAssessment;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\RubricItem;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConductRubricAssessmentAutoAdvanceTest extends TestCase
{
    use RefreshDatabase;

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
     * @return array{classModule: ClassModule, mentee: User, rubric: ModuleRubric}
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
        $mentee = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Mentee']);
        ClassParticipant::factory()->create([
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

        return compact('classModule', 'mentee', 'rubric');
    }

    public function test_arriving_with_rubric_and_mentee_in_the_url_skips_straight_to_scoring(): void
    {
        $mentor = $this->actingAsMentor();
        $scenario = $this->buildScenario($mentor);

        $component = Livewire::withQueryParams([
            'rubric_id' => $scenario['rubric']->id,
            'mentee_id' => $scenario['mentee']->id,
            'class_module_id' => $scenario['classModule']->id,
        ])->test(ConductRubricAssessment::class);

        $this->assertSame(2, $component->instance()->step);
        $this->assertNotNull($component->instance()->rubric);
        $component->assertSee('Jane Mentee');
        $component->assertDontSee('— Select mentee —');
    }

    public function test_arriving_with_no_query_params_still_shows_the_manual_picker(): void
    {
        $mentor = $this->actingAsMentor();
        $this->buildScenario($mentor);

        $component = Livewire::test(ConductRubricAssessment::class);

        $this->assertSame(1, $component->instance()->step);
        $component->assertSee('— Select mentee —');
    }
}
