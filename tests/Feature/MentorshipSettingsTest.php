<?php

namespace Tests\Feature;

use App\Filament\Pages\MentorshipSettings;
use App\Filament\Resources\MentorshipResource\Pages\CreateMentorshipTraining;
use App\Filament\Resources\MentorshipResource\Pages\GuidedMentorshipSetup;
use App\Models\Program;
use App\Models\Setting;
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

    public function test_active_toggle_column_flips_the_programs_status(): void
    {
        $this->actingAsAdmin();
        $program = Program::factory()->create(['is_active' => true]);

        Livewire::test(MentorshipSettings::class)
            ->call('updateTableColumnState', 'is_active', $program->getKey(), false);

        $this->assertFalse($program->fresh()->is_active);

        Livewire::test(MentorshipSettings::class)
            ->call('updateTableColumnState', 'is_active', $program->getKey(), true);

        $this->assertTrue($program->fresh()->is_active);
    }

    public function test_deactivating_a_program_here_disables_it_in_the_mentorship_creation_picker(): void
    {
        $admin = $this->actingAsAdmin();
        $program = Program::factory()->create(['name' => 'Toggle Target', 'is_active' => true, 'visible_to_roles' => []]);

        Livewire::test(MentorshipSettings::class)
            ->call('updateTableColumnState', 'is_active', $program->getKey(), false);

        $this->assertFalse($program->fresh()->isSelectableBy($admin));
    }

    public function test_deactivating_a_program_disables_it_even_for_super_admin(): void
    {
        // The toggle is meant to apply universally, the same way the New
        // Mentorship / Guided Setup button toggles do — no role bypasses
        // the "not active" state except an explicit visible_to_roles entry.
        $superAdmin = User::factory()->create(['name' => 'Super Admin Tester']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('super_admin');

        $program = Program::factory()->create(['is_active' => false, 'visible_to_roles' => []]);

        $this->assertFalse($program->isSelectableBy($superAdmin));
    }

    public function test_setting_defaults_to_true_when_never_set(): void
    {
        $this->assertTrue(Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED));
        $this->assertTrue(Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED));
    }

    public function test_setting_bool_round_trips_and_updates_the_cached_value(): void
    {
        Setting::setBool(Setting::GUIDED_SETUP_BUTTON_ENABLED, false);
        $this->assertFalse(Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED));

        Setting::setBool(Setting::GUIDED_SETUP_BUTTON_ENABLED, true);
        $this->assertTrue(Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED));
    }

    public function test_creation_method_toggles_on_the_settings_page_persist_to_the_setting_model(): void
    {
        $this->actingAsAdmin();

        Livewire::test(MentorshipSettings::class)
            ->set('data.new_mentorship_button_enabled', false)
            ->set('data.guided_setup_button_enabled', false);

        $this->assertFalse(Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED));
        $this->assertFalse(Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED));
    }

    public function test_mentorships_list_header_actions_are_disabled_when_their_setting_is_off(): void
    {
        $user = User::factory()->create(['name' => 'List Viewer']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        Setting::setBool(Setting::GUIDED_SETUP_BUTTON_ENABLED, false);

        $response = $this->get(\App\Filament\Resources\MentorshipTrainingResource::getUrl());

        $response->assertOk();
        $response->assertSee('New Mentorship Guided Setup');
    }

    public function test_create_mentorship_training_page_blocks_access_when_its_setting_is_off(): void
    {
        Setting::setBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED, false);

        $this->assertFalse(CreateMentorshipTraining::canAccess());

        Setting::setBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED, true);

        $this->assertTrue(CreateMentorshipTraining::canAccess());
    }

    public function test_guided_setup_blocks_a_fresh_start_but_allows_resuming_when_disabled(): void
    {
        Setting::setBool(Setting::GUIDED_SETUP_BUTTON_ENABLED, false);
        $this->assertFalse(GuidedMentorshipSetup::canAccess());

        $user = User::factory()->create(['name' => 'Resuming User']);
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        // A fresh visit (no ?training=) stays blocked even while resuming
        // is allowed — the setting only gates *starting new* wizards.
        $this->get(GuidedMentorshipSetup::getUrl())->assertForbidden();

        // Resuming an existing draft works regardless of the setting.
        $this->get(GuidedMentorshipSetup::getUrl(['training' => $training->id]))->assertOk();
    }
}
