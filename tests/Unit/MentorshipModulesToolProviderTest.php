<?php

namespace Tests\Unit;

use App\Filament\Resources\MentorshipResource\Pages\ChatMentorshipSetup;
use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use App\Services\Chat\Tools\MentorshipModulesToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipModulesToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create(['name' => 'Coordinator']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    private function advanceToModulesStage($page, Program $program, Facility $facility): void
    {
        $page->answer('is_pilot', 0);
        $page->answer('county_id', $facility->subcounty->county_id);
        $page->answer('facility_id', $facility->id);
        $page->answer('program_id', $program->id);
        $page->answer('start_date', now()->addDay()->toDateString());
        $page->answer('end_date', now()->addMonth()->toDateString());
        $page->answer('max_participants', 8);
        $page->answer('class_name', 'Cohort A');
        $page->answer('class_start_date', now()->addDay()->toDateString());
        $page->answer('class_end_date', now()->addMonth()->toDateString());
        $page->answer('class_description', 'skip');
    }

    public function test_schema_exposes_module_names_for_a_standard_program(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        $enum = $tool->schema()['properties']['module_names']['items']['enum'];

        $this->assertContains('Neonatal Resuscitation', $enum);
    }

    public function test_execute_resolves_module_names_and_assigns_them(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        $result = $tool->execute(['module_names' => ['Neonatal Resuscitation']], $user);

        $this->assertSame([$module->id], $result['submitted']);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $page->class->id,
            'program_module_id' => $module->id,
        ]);
    }

    public function test_execute_skips_when_the_user_wants_no_modules_yet(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        $tool->execute(['skip' => true], $user);

        $this->assertArrayHasKey('module_ids', $page->answers);
        $this->assertSame(0, $page->class->classModules()->count());
    }

    public function test_execute_does_not_assign_anything_if_a_name_is_unresolved(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        $module = ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        // "Neonatal Resuscitation" is real, "Made Up Module" isn't — since
        // submitModules() is a one-shot "Continue" action that finalizes
        // the whole module list, silently dropping the unresolved one and
        // submitting just the real one would truncate what the user asked
        // for without telling them.
        $result = $tool->execute(['module_names' => ['Neonatal Resuscitation', 'Made Up Module']], $user);

        $this->assertArrayNotHasKey('submitted', $result);
        $this->assertContains('Made Up Module', $result['unresolved']);
        $this->assertArrayNotHasKey('module_ids', $page->answers);
        $this->assertDatabaseMissing('class_modules', [
            'mentorship_class_id' => $page->class->id,
            'program_module_id' => $module->id,
        ]);
    }

    public function test_schema_excludes_modules_from_a_different_program(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $otherProgram = Program::factory()->create(['name' => 'Adolescent Health', 'is_active' => true]);
        $facility = Facility::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        ProgramModule::factory()->create(['program_id' => $otherProgram->id, 'is_active' => true, 'name' => 'Peer Counseling Basics']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        $enum = $tool->schema()['properties']['module_names']['items']['enum'];

        $this->assertContains('Neonatal Resuscitation', $enum);
        $this->assertNotContains('Peer Counseling Basics', $enum);
    }

    public function test_execute_suggests_close_matches_for_an_unresolved_name(): void
    {
        $user = $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care', 'is_active' => true]);
        $facility = Facility::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true, 'name' => 'Neonatal Resuscitation']);
        $page = new ChatMentorshipSetup;
        $page->mount();
        $this->advanceToModulesStage($page, $program, $facility);

        $tool = MentorshipModulesToolProvider::tool($page);
        // "Resusitation" — a real, plausible typo.
        $result = $tool->execute(['module_names' => ['Neonatal Resusitation']], $user);

        $this->assertArrayNotHasKey('submitted', $result);
        $this->assertArrayHasKey('suggestions', $result);
        $labels = array_column($result['suggestions']['Neonatal Resusitation'], 'label');
        $this->assertContains('Neonatal Resuscitation', $labels);
    }
}
