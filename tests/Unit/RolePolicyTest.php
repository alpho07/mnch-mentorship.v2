<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_grants_when_the_real_permission_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'restore_role', 'guard_name' => 'web']);
        $user->givePermissionTo('restore_role');
        $role = Role::create(['name' => 'some_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertTrue(
            $policy->restore($user, $role),
            'RolePolicy::restore() used to check the literal, un-replaced Shield stub token "{{ Restore }}" '
            . '(Phase 1 risk 9.2, now fixed) — it checks the real restore_role permission now.'
        );
    }

    public function test_restore_still_denies_without_the_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'some_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertFalse($policy->restore($user, $role));
    }

    public function test_force_delete_any_grants_when_the_real_permission_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'force_delete_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('force_delete_any_role');

        $policy = new RolePolicy();

        $this->assertTrue($policy->forceDeleteAny($user));
    }

    public function test_replicate_grants_when_the_real_permission_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'replicate_role', 'guard_name' => 'web']);
        $user->givePermissionTo('replicate_role');
        $role = Role::create(['name' => 'another_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertTrue($policy->replicate($user, $role));
    }

    public function test_reorder_grants_when_the_real_permission_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'reorder_role', 'guard_name' => 'web']);
        $user->givePermissionTo('reorder_role');

        $policy = new RolePolicy();

        $this->assertTrue($policy->reorder($user));
    }

    public function test_force_delete_grants_when_the_real_permission_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'force_delete_role', 'guard_name' => 'web']);
        $user->givePermissionTo('force_delete_role');
        $role = Role::create(['name' => 'yet_another_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertTrue($policy->forceDelete($user, $role));
    }

    public function test_view_any_correctly_grants_when_the_real_permission_slug_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_role');

        $policy = new RolePolicy();

        $this->assertTrue($policy->viewAny($user));
    }
}
