<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorshipSettings;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MentorshipSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Test Admin']);
        Permission::firstOrCreate(['name' => 'update_program', 'guard_name' => 'web']);
        $user->givePermissionTo('update_program');
        $this->actingAs($user);

        return $user;
    }

    public function test_page_is_hidden_from_users_without_the_update_program_permission(): void
    {
        $user = User::factory()->create(['name' => 'No Access']);
        $this->actingAs($user);

        $this->assertFalse(MentorshipSettings::canAccess());
    }

    public function test_page_loads_and_lists_programs_for_a_user_with_permission(): void
    {
        $this->actingAsAdmin();
        Program::factory()->create(['name' => 'Active Program X', 'is_active' => true]);
        Program::factory()->create(['name' => 'Inactive Program Y', 'is_active' => false]);

        $response = $this->get(MentorshipSettings::getUrl());

        $response->assertOk();
        $response->assertSee('Active Program X');
        $response->assertSee('Inactive Program Y');
    }

    public function test_toggle_active_action_flips_the_programs_status(): void
    {
        $this->actingAsAdmin();
        $program = Program::factory()->create(['is_active' => true]);

        Livewire::test(MentorshipSettings::class)
            ->callTableAction('toggle_active', $program);

        $this->assertFalse($program->fresh()->is_active);

        Livewire::test(MentorshipSettings::class)
            ->callTableAction('toggle_active', $program);

        $this->assertTrue($program->fresh()->is_active);
    }

    public function test_deactivating_a_program_here_disables_it_in_the_mentorship_creation_picker(): void
    {
        $admin = $this->actingAsAdmin();
        $program = Program::factory()->create(['name' => 'Toggle Target', 'is_active' => true, 'visible_to_roles' => []]);

        Livewire::test(MentorshipSettings::class)
            ->callTableAction('toggle_active', $program);

        $this->assertFalse($program->fresh()->isSelectableBy($admin));
    }
}
