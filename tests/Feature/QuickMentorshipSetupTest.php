<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Filament\Resources\MentorshipResource\Pages\QuickMentorshipSetup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuickMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_list_page_shows_quick_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSeeHtml('Quick Setup');
    }

    public function test_quick_setup_page_loads(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(QuickMentorshipSetup::class)
            ->assertSuccessful();
    }

    public function test_page_is_blocked_when_the_setting_is_off_for_a_fresh_visit(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);

        $this->assertFalse(QuickMentorshipSetup::canAccess());
    }

    public function test_page_stays_accessible_with_a_training_query_param_even_when_off(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);
        request()->merge(['training' => 1]);

        $this->assertTrue(QuickMentorshipSetup::canAccess());
    }
}
