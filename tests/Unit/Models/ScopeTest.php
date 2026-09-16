<?php

namespace Tests\Unit\Models;

use App\Models\Scope;
use App\Models\ScopeRoleAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createScope(string $slug, array $roles, bool $active = true): Scope
    {
        $scope = Scope::create([
            'slug'       => $slug,
            'label'      => ucfirst($slug),
            'icon'       => '📋',
            'color'      => '#6366F1',
            'gradient'   => ['#6366F1', '#4F46E5'],
            'tabs'       => ['home', $slug, 'profile'],
            'is_active'  => $active,
            'sort_order' => 0,
        ]);
        foreach ($roles as $role) {
            ScopeRoleAccess::create(['scope_id' => $scope->id, 'role_name' => $role]);
        }
        return $scope;
    }

    public function test_returns_only_allowed_scopes_for_role(): void
    {
        $this->createScope('assessments', ['facility_mentor']);
        $this->createScope('mentorships', ['facility_mentor', 'mentee']);
        $this->createScope('trainings', ['admin']);

        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('facility_mentor');

        $scopes = Scope::forUser($user);

        $this->assertCount(2, $scopes);
        $this->assertTrue($scopes->pluck('slug')->contains('assessments'));
        $this->assertTrue($scopes->pluck('slug')->contains('mentorships'));
        $this->assertFalse($scopes->pluck('slug')->contains('trainings'));
    }

    public function test_super_admin_gets_all_active_scopes(): void
    {
        $this->createScope('assessments', []);
        $this->createScope('mentorships', []);
        $this->createScope('trainings', []);
        $this->createScope('inactive_scope', [], false);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $scopes = Scope::forUser($user);

        $this->assertCount(3, $scopes); // inactive excluded
        $this->assertFalse($scopes->pluck('slug')->contains('inactive_scope'));
    }

    public function test_inactive_scope_not_returned(): void
    {
        $this->createScope('assessments', ['facility_mentor'], false);

        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('facility_mentor');

        $scopes = Scope::forUser($user);

        $this->assertCount(0, $scopes);
    }
}
