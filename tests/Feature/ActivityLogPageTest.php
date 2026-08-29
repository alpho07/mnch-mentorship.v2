<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivityLog;
use App\Models\LoginLog;
use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_user_can_access_the_page(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);

        $this->assertTrue(ActivityLog::canAccess());
    }

    public function test_non_privileged_user_cannot_access_the_page(): void
    {
        $mentee = User::factory()->create();
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $mentee->assignRole('mentee');

        $this->actingAs($mentee);

        $this->assertFalse(ActivityLog::canAccess());
    }

    public function test_page_loads_top_pages_within_range(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create(['name' => 'Test User']);

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'filament.admin.pages.dashboard', 'path' => '/admin', 'created_at' => now()->subDay()]);
        PageVisit::create(['user_id' => $someone->id, 'route_name' => null, 'path' => '/resources', 'created_at' => now()->subDays(20)]); // outside 7d range

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->assertSet('range', '7d')
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 1 && (int) $pages->first()->visits === 2);
    }

    public function test_set_range_updates_top_pages(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create();

        PageVisit::create(['user_id' => $someone->id, 'route_name' => 'test.route', 'path' => '/test', 'created_at' => now()->subDays(20)]);

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 0)
            ->call('setRange', '30d')
            ->assertSet('range', '30d')
            ->assertViewHas('topPages', fn ($pages) => $pages->count() === 1);
    }

    public function test_page_renders_with_login_log_records(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        $someone = User::factory()->create(['name' => 'Login User']);

        LoginLog::create(['user_id' => $someone->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'PHPUnit', 'logged_in_at' => now()->subDays(1)]);

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->assertSuccessful()
            ->assertViewHas('topPages');
    }

    public function test_table_includes_role_and_location_columns(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        $someone = User::factory()->create(['name' => 'Role User']);
        $someone->assignRole('county_mentor_lead');

        LoginLog::create(['user_id' => $someone->id, 'ip_address' => '192.168.1.5', 'user_agent' => 'PHPUnit', 'logged_in_at' => now()->subHours(1)]);

        $this->actingAs($admin);

        Http::fake();

        Livewire::test(ActivityLog::class)
            ->assertSuccessful()
            ->assertSeeHtml('Role(s)')
            ->assertSeeHtml('Location')
            ->assertSee('county_mentor_lead')
            ->assertSee('Local network');
    }

    public function test_user_filter_narrows_records_to_the_selected_user(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $aliceLogin = LoginLog::create(['user_id' => $alice->id, 'ip_address' => '192.168.0.10', 'logged_in_at' => now()->subHours(2)]);
        $bobLogin = LoginLog::create(['user_id' => $bob->id, 'ip_address' => '192.168.0.11', 'logged_in_at' => now()->subHour()]);

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->set('tableFilters', ['user' => ['value' => (string) $alice->id]])
            ->assertCanSeeTableRecords([$aliceLogin])
            ->assertCanNotSeeTableRecords([$bobLogin]);
    }

    public function test_role_filter_limits_records_to_users_with_that_role(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);

        $mentor = User::factory()->create(['name' => 'Mentor Person']);
        $mentor->assignRole('county_mentor_lead');

        $mentee = User::factory()->create(['name' => 'Mentee Person']);
        $mentee->assignRole('mentee');

        $mentorLogin = LoginLog::create(['user_id' => $mentor->id, 'ip_address' => '192.168.0.20', 'logged_in_at' => now()->subHours(2)]);
        $menteeLogin = LoginLog::create(['user_id' => $mentee->id, 'ip_address' => '192.168.0.21', 'logged_in_at' => now()->subHour()]);

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->set('tableFilters', ['role' => ['value' => 'county_mentor_lead']])
            ->assertCanSeeTableRecords([$mentorLogin])
            ->assertCanNotSeeTableRecords([$menteeLogin]);
    }

    public function test_ip_filter_matches_by_prefix(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $someone = User::factory()->create();

        $matched = LoginLog::create(['user_id' => $someone->id, 'ip_address' => '197.232.60.1', 'logged_in_at' => now()->subHours(2)]);
        $other = LoginLog::create(['user_id' => $someone->id, 'ip_address' => '41.90.10.5', 'logged_in_at' => now()->subHour()]);

        $this->actingAs($admin);

        Livewire::test(ActivityLog::class)
            ->set('tableFilters', ['ip_address' => ['value' => '197.232']])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_role_filter_options_only_include_roles_present_in_range(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');
        Role::firstOrCreate(['name' => 'county_mentor_lead', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);

        $mentor = User::factory()->create();
        $mentor->assignRole('county_mentor_lead');

        $mentee = User::factory()->create();
        $mentee->assignRole('mentee');

        LoginLog::create(['user_id' => $mentor->id, 'ip_address' => '192.168.0.30', 'logged_in_at' => now()->subHours(2)]);
        LoginLog::create(['user_id' => $mentee->id, 'ip_address' => '192.168.0.31', 'logged_in_at' => now()->subDays(20)]); // outside 7d range

        $this->actingAs($admin);

        $options = Livewire::test(ActivityLog::class)->instance()->roleFilterOptions();

        $this->assertSame(['county_mentor_lead' => 'county_mentor_lead'], $options);
    }

    public function test_user_filter_options_only_include_users_present_in_range(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.test']);
        $carol = User::factory()->create(['name' => 'Carol', 'email' => 'carol@example.test']);

        LoginLog::create(['user_id' => $alice->id, 'ip_address' => '192.168.0.40', 'logged_in_at' => now()->subHours(2)]);
        LoginLog::create(['user_id' => $carol->id, 'ip_address' => '192.168.0.41', 'logged_in_at' => now()->subDays(20)]); // outside 7d range

        $this->actingAs($admin);

        $options = Livewire::test(ActivityLog::class)->instance()->userFilterOptions();

        $this->assertSame([$alice->id => 'Alice'], $options);
    }

    public function test_user_query_param_pre_filters_the_table(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole('super_admin');

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        $aliceLogin = LoginLog::create(['user_id' => $alice->id, 'ip_address' => '192.168.0.50', 'logged_in_at' => now()->subHours(2)]);
        $bobLogin = LoginLog::create(['user_id' => $bob->id, 'ip_address' => '192.168.0.51', 'logged_in_at' => now()->subHour()]);

        $this->actingAs($admin);

        Livewire::withQueryParams(['user' => (string) $alice->id])
            ->test(ActivityLog::class)
            ->assertSet('tableFilters', fn ($filters) => ($filters['user']['value'] ?? null) == $alice->id)
            ->assertCanSeeTableRecords([$aliceLogin])
            ->assertCanNotSeeTableRecords([$bobLogin]);
    }
}
