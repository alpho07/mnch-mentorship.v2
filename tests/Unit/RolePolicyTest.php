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

    public function test_restore_currently_always_denies_even_with_every_real_permission_granted(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'restore_role', 'guard_name' => 'web']);
        $user->givePermissionTo('restore_role');
        $role = Role::create(['name' => 'some_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->restore($user, $role),
            'RolePolicy::restore() checks the literal permission name "{{ Restore }}", an un-replaced Shield '
            . 'stub token that can never exist as a real permission (see docs/PHASE1-DISCOVERY-BASELINE.md '
            . '§9.2). This test locks in the current fail-closed (always-deny) behavior. Once the stub is '
            . 'replaced with a real slug (e.g. restore_role), update this test to assertTrue given the '
            . 'restore_role grant above.'
        );
    }

    public function test_force_delete_any_currently_always_denies_for_the_same_reason(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'force_delete_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('force_delete_any_role');

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->forceDeleteAny($user),
            'Same defect as restore() — forceDeleteAny() checks "{{ ForceDeleteAny }}". '
            . 'See docs/PHASE1-DISCOVERY-BASELINE.md §9.2.'
        );
    }

    public function test_replicate_currently_always_denies_for_the_same_reason(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'replicate_role', 'guard_name' => 'web']);
        $user->givePermissionTo('replicate_role');
        $role = Role::create(['name' => 'another_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->replicate($user, $role),
            'Same defect — replicate() checks "{{ Replicate }}". See docs/PHASE1-DISCOVERY-BASELINE.md §9.2.'
        );
    }

    public function test_view_any_correctly_grants_when_the_real_permission_slug_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_role');

        $policy = new RolePolicy();

        $this->assertTrue(
            $policy->viewAny($user),
            'viewAny() uses a real permission slug (view_any_role) and works correctly — included as a '
            . 'control case to prove the denials above are specifically about the stub tokens, not about '
            . 'RolePolicy or Spatie permissions being broken in general.'
        );
    }
}
