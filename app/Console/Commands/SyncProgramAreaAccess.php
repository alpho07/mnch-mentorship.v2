<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Applies config/program_areas.php to the admin / newborn_child_admin /
 * emonc_admin roles. Idempotent; additive for permissions, never touches
 * any other role or any data.
 */
class SyncProgramAreaAccess extends Command
{
    protected $signature = 'access:sync {--dry-run : Show counts without saving}';

    protected $description = 'Sync admin, newborn_child_admin and emonc_admin roles from config/program_areas.php';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->ensurePermissions();

        $prefixes = config('filament-shield.permission_prefixes.resource');
        $all = Permission::where('guard_name', 'web')->pluck('name');

        // Keep Mentorship Settings behaviour for roles that could open it before (it used update_program).
        $legacy = Role::whereHas('permissions', fn ($q) => $q->where('name', 'update_program'))->pluck('name')
            ->reject(fn ($r) => in_array($r, ['admin', ...collect(config('program_areas.areas'))->pluck('role')->all()]));

        // admin: everything except the excluded set
        $ex = config('program_areas.admin_excluded');
        $excluded = collect($ex['permissions'])->merge(
            collect($ex['resources'])->flatMap(fn ($s) => collect($prefixes)->map(fn ($p) => "{$p}_{$s}"))
        );
        $this->apply('admin', $all->diff($excluded)->values());

        foreach (config('program_areas.roles') as $roleName => $def) {
            $names = collect($def['resources'])->flatMap(fn ($s) => collect($prefixes)->map(fn ($p) => "{$p}_{$s}"))
                ->merge($def['permissions']);

            if (($def['widgets'] ?? null) === 'all') {
                $deny = config('program_areas.widget_denylist_patterns');
                $names = $names->merge($all->filter(fn ($n) => str_starts_with($n, 'widget_')
                    && ! collect($deny)->contains(fn ($d) => str_contains($n, $d))));
            }

            $this->apply($roleName, $names->unique()->intersect($all)->values());
        }

        if (! $this->option('dry-run')) {
            foreach ($legacy as $r) {
                Role::findByName($r, 'web')->givePermissionTo('page_MentorshipSettings');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return self::SUCCESS;
    }

    private function ensurePermissions(): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        foreach (['page_MentorshipSettings'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (collect(config('program_areas.areas'))->pluck('role') as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function apply(string $roleName, $names): void
    {
        $this->line(sprintf('%-22s %d permissions', $roleName, $names->count()));

        if ($this->option('dry-run')) {
            return;
        }

        Role::findByName($roleName, 'web')->syncPermissions($names->all());
    }
}
