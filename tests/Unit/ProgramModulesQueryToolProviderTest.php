<?php

namespace Tests\Unit;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use App\Services\Chat\Tools\ProgramModulesQueryToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramModulesQueryToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        $this->actingAs($user);

        return $user;
    }

    public function test_it_returns_the_real_modules_for_a_standard_program(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'order_sequence' => 1, 'name' => 'Module 1: Infection Prevention and Control (IPC)']);
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'order_sequence' => 2, 'name' => 'Module 6: Newborn Resuscitation']);
        // An inactive module must never surface — same rule
        // getModuleFieldOptions() already applies for the fill-slots tool.
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => false, 'name' => 'Retired Module']);

        $tool = ProgramModulesQueryToolProvider::tools()[0];
        $result = $tool->execute(['program_name' => 'Newborn Care'], $user);

        $this->assertSame('Newborn Care', $result['program']);
        $names = array_column($result['modules'], 'name');
        $this->assertSame(['Module 1: Infection Prevention and Control (IPC)', 'Module 6: Newborn Resuscitation'], $names);
        $this->assertNotContains('Retired Module', $names);
    }

    public function test_it_includes_tracks_for_emonc_parent_modules(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Maternal Health (EmONC)', 'is_active' => true]);
        $pph = ProgramModule::factory()->create([
            'program_id' => $program->id,
            'is_active' => true,
            'order_sequence' => 5,
            'name' => 'Module 5: Management of Postpartum Hemorrhage (PPH)',
        ]);
        ProgramModule::factory()->create(['program_id' => $program->id, 'parent_id' => $pph->id, 'is_active' => true, 'order_sequence' => 1, 'name' => 'Track 1: Bimanual compression of the uterus']);
        ProgramModule::factory()->create(['program_id' => $program->id, 'parent_id' => $pph->id, 'is_active' => true, 'order_sequence' => 2, 'name' => 'Track 2: Uterine massage']);
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'order_sequence' => 1, 'name' => 'Module 1: Ante Partum Hemorrhage']);

        $tool = ProgramModulesQueryToolProvider::tools()[0];
        $result = $tool->execute(['program_name' => 'Maternal Health (EmONC)'], $user);

        $modulesByName = collect($result['modules'])->keyBy('name');
        $this->assertSame(
            ['Track 1: Bimanual compression of the uterus', 'Track 2: Uterine massage'],
            $modulesByName['Module 5: Management of Postpartum Hemorrhage (PPH)']['tracks']
        );
        $this->assertSame([], $modulesByName['Module 1: Ante Partum Hemorrhage']['tracks']);
    }

    public function test_it_rejects_a_program_name_that_does_not_exist_instead_of_guessing(): void
    {
        $user = $this->actingAsCoordinator();
        Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);

        $tool = ProgramModulesQueryToolProvider::tools()[0];
        $result = $tool->execute(['program_name' => 'Made Up Program'], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('modules', $result);
    }

    public function test_it_matches_a_program_name_case_insensitively(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Infant and Child Care', 'is_active' => true]);
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Module 3: Triage']);

        $tool = ProgramModulesQueryToolProvider::tools()[0];
        $result = $tool->execute(['program_name' => 'infant and child care'], $user);

        $this->assertSame('Infant and Child Care', $result['program']);
    }
}
