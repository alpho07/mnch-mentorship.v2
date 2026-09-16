<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivityDashboard;
use App\Filament\Pages\ActivityLog;
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

    public function test_page_shows_top_pages_within_range(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => null, 'path' => '/resources', 'created_at' => now()->subDays(20)]); // outside range

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertSet('range', '7d')
            ->assertViewHas('topPages', function ($pages) {
                return $pages->count() === 1 && (int) $pages->first()->visits === 2;
            });
    }

    public function test_set_range_recomputes_top_pages(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDays(20)]);

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 0)
            ->call('setRange', '30d')
            ->assertSet('range', '30d')
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 1);
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

    public function test_dashboard_links_to_the_activity_log_page(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Viewer']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $response = $this->get(ActivityDashboard::getUrl());

        $response->assertOk();
        $response->assertSee(ActivityLog::getUrl());
    }

    public function test_last_active_card_lists_recent_users_with_deep_activity_links(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Viewer']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $fresh = User::factory()->create(['name' => 'Fresh Fiona', 'last_seen_at' => now()->subHours(2)]);
        $stale = User::factory()->create(['name' => 'Stale Sue', 'last_seen_at' => now()->subDays(30)]);

        $this->actingAs($admin);

        $response = $this->get(ActivityDashboard::getUrl());

        $response->assertOk();
        $response->assertSee('Users — Last Active');
        $response->assertSee('Fresh Fiona');
        $response->assertSee('activity-log?user='.$fresh->id);
        $response->assertDontSee($stale->name);
    }

    public function test_refresh_online_recomputes_online_users(): void
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

    public function test_top_pages_survive_a_refresh_online_cycle(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 1 && (int) $pages->first()->visits === 2)
            ->call('refreshOnline')
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 1 && (int) $pages->first()->visits === 2);
    }

    public function test_refresh_online_loads_each_users_navigation_history(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $online = User::factory()->create(['name' => 'Online Person', 'last_seen_at' => now()->subMinutes(2)]);

        PageVisit::create(['user_id' => $online->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subMinutes(5)]);
        PageVisit::create(['user_id' => $online->id, 'route_name' => 'filament.admin.resources.mentorship-trainings.index', 'path' => '/admin/mentorship-trainings', 'created_at' => now()->subMinute()]);

        $this->actingAs($admin);

        Livewire::test(ActivityDashboard::class)
            ->assertViewHas('onlineUsers', function ($onlineUsers) {
                return $onlineUsers->first()->relationLoaded('recentPageVisits')
                    && $onlineUsers->first()->recentPageVisits->count() === 2
                    && $onlineUsers->first()->recentPageVisits->first()->route_name === 'filament.admin.resources.mentorship-trainings.index';
            });
    }
}
