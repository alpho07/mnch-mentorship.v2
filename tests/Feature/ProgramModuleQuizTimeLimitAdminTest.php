<?php

namespace Tests\Feature;

use App\Filament\Resources\ProgramModuleQuizResource\Pages\CreateProgramModuleQuiz;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProgramModuleQuizTimeLimitAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_a_time_limit_when_creating_a_quiz(): void
    {
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'create_program::module::quiz', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_program::module::quiz', 'guard_name' => 'web']);
        $admin->givePermissionTo(['create_program::module::quiz', 'view_any_program::module::quiz']);
        $this->actingAs($admin);

        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

        Livewire::test(CreateProgramModuleQuiz::class)
            ->fillForm([
                'program_module_id' => $module->id,
                'title' => 'Pre-Test',
                'type' => 'pre_test',
                'pass_mark_percentage' => 80,
                'time_limit_minutes' => 15,
                'order_sequence' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('program_module_quizzes', [
            'title' => 'Pre-Test',
            'time_limit_minutes' => 15,
        ]);
    }

    public function test_time_limit_is_optional(): void
    {
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'create_program::module::quiz', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_program::module::quiz', 'guard_name' => 'web']);
        $admin->givePermissionTo(['create_program::module::quiz', 'view_any_program::module::quiz']);
        $this->actingAs($admin);

        $program = Program::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id]);

        Livewire::test(CreateProgramModuleQuiz::class)
            ->fillForm([
                'program_module_id' => $module->id,
                'title' => 'Untimed Pre-Test',
                'type' => 'pre_test',
                'pass_mark_percentage' => 80,
                'order_sequence' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('program_module_quizzes', [
            'title' => 'Untimed Pre-Test',
            'time_limit_minutes' => null,
        ]);
    }
}
