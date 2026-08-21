<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivityDashboard;
use App\Models\LoginLog;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_user_can_access_the_page(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->assertTrue(ActivityDashboard::canAccess());
    }

    public function test_non_privileged_user_cannot_access_the_page(): void
    {
        $mentee = User::factory()->create();
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $mentee->assignRole('mentee');

        $this->actingAs($mentee);

        $this->assertFalse(ActivityDashboard::canAccess());
    }

    public function test_page_shows_recent_logins_and_top_pages_within_range(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        LoginLog::create(['user_id' => $someone->id, 'logged_in_at' => now()->subDays(2)]);
        LoginLog::create(['user_id' => $someone->id, 'logged_in_at' => now()->subDays(20)]); // outside 7d range

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => null, 'path' => '/resources', 'created_at' => now()->subDays(20)]); // outside range

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertSet('range', '7d')
            ->assertViewHas('recentLogins', function ($logins) {
                return $logins->count() === 1;
            })
            ->assertViewHas('topPages', function ($pages) {
                return $pages->count() === 1 && (int) $pages->first()->visits === 2;
            });
    }

    public function test_set_range_recomputes_the_data_window(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        LoginLog::create(['user_id' => $someone->id, 'logged_in_at' => now()->subDays(20)]);

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertViewHas('recentLogins', fn ($logins) => $logins->count() === 0)
            ->call('setRange', '30d')
            ->assertSet('range', '30d')
            ->assertViewHas('recentLogins', fn ($logins) => $logins->count() === 1);
    }

    public function test_currently_online_section_shows_each_online_users_latest_page(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $online = User::factory()->create(['name' => 'Online Person', 'last_seen_at' => now()->subMinutes(2)]);
        User::factory()->create(['name' => 'Stale Person', 'last_seen_at' => now()->subMinutes(10)]);

        PageVisit::create(['user_id' => $online->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subMinutes(5)]);
        PageVisit::create(['user_id' => $online->id, 'route_name' => 'filament.admin.resources.mentorship-trainings.index', 'path' => '/admin/mentorship-trainings', 'created_at' => now()->subMinute()]);

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertViewHas('onlineUsers', function ($onlineUsers) use ($online) {
                return $onlineUsers->count() === 1
                    && $onlineUsers->first()->id === $online->id
                    && $onlineUsers->first()->currentPageVisit?->route_name === 'filament.admin.resources.mentorship-trainings.index';
            });
    }

    public function test_currently_online_section_shows_all_of_a_users_roles(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Viewer']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'facility_mentor_lead', 'guard_name' => 'web']);
        $online = User::factory()->create(['name' => 'Multi Role Person', 'last_seen_at' => now()->subMinutes(2)]);
        $online->assignRole(['facility_mentor', 'facility_mentor_lead']);

        $this->actingAs($admin);

        $response = $this->get(ActivityDashboard::getUrl().'#currently-online');

        $response->assertOk();
        $response->assertSee('Multi Role Person');
        $response->assertSee('facility_mentor, facility_mentor_lead');
    }

    public function test_refresh_online_recomputes_without_touching_login_range_data(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $component = Livewire::test(ActivityDashboard::class)
            ->assertViewHas('onlineUsers', fn ($onlineUsers) => $onlineUsers->count() === 0);

        User::factory()->create(['last_seen_at' => now()]);

        $component->call('refreshOnline')
            ->assertViewHas('onlineUsers', fn ($onlineUsers) => $onlineUsers->count() === 1);
    }
}

