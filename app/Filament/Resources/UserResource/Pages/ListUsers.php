<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\UserStatsOverview;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListUsers extends ListRecords {

    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array {
        return [UserStatsOverview::class];
    }

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('New User')
                    ->icon('heroicon-o-user-plus'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Override the table query — applied BEFORE Filament hands it to the table.
    // Reads $this->activeTab and applies a raw subquery on model_has_roles+roles.
    // No closures on tab definitions = nothing for Livewire to serialize/fail on.
    // ─────────────────────────────────────────────────────────────────────────

    protected function getTableQuery(): Builder {
        $query = User::withTrashed()->with([
            'facility:id,name,mfl_code,subcounty_id',
            'facility.subcounty:id,name,county_id',
            'facility.subcounty.county:id,name',
            'cadre:id,name',
            'roles:id,name',
        ]);

        $tab = $this->activeTab ?? 'all';

        if ($tab === 'all' || $tab === null) {
            return $query;
        }

        if ($tab === 'no_role') {
            return $query->whereNotIn('users.id', function ($sub) {
                        $sub->select('model_id')
                                ->from('model_has_roles')
                                ->where('model_type', 'App\\Models\\User');
                    });
        }

        // Resolve slug back to role name via the roles table
        $roleName = DB::table('roles')
                ->whereRaw("REPLACE(LOWER(name), ' ', '_') = ?", [str_replace('-', '_', $tab)])
                ->orWhereRaw("LOWER(name) = ?", [str_replace('_', ' ', $tab)])
                ->value('name');

        if ($roleName) {
            return $query->whereIn('users.id', function ($sub) use ($roleName) {
                        $sub->select('model_has_roles.model_id')
                                ->from('model_has_roles')
                                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                                ->where('model_has_roles.model_type', 'App\\Models\\User')
                                ->where('roles.name', $roleName);
                    });
        }

        return $query;
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $this->applyColumnSearchesToTableQuery($query);
        $this->applyGlobalSearchToTableQuery($query);

        if (filled($search = $this->getTableSearch())) {
            $this->applySearchRelevanceOrdering($query, $search);
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tabs — labels, badges, colors only. NO modifyQueryUsing closures.
    // Filtering is handled entirely in getTableQuery() above.
    // ─────────────────────────────────────────────────────────────────────────

    public function getTabs(): array {
        $roleCounts = $this->getRoleCounts();
        $noRoleCount = User::withTrashed()->whereNotIn('id', function ($sub) {
                    $sub->select('model_id')
                            ->from('model_has_roles')
                            ->where('model_type', 'App\\Models\\User');
                })->count();
        $total = User::withTrashed()->count();

        $tabs = [
            'all' => Tab::make('All')->badge($total)->badgeColor('primary'),
        ];

        foreach ($roleCounts as $roleName => $count) {
            // Slug must match what Filament sets as $this->activeTab
            $slug = strtolower(str_replace([' ', '-'], '_', $roleName));

            $tabs[$slug] = Tab::make($roleName)
                    ->badge($count)
                    ->badgeColor($this->roleColor($roleName));
        }

        if ($noRoleCount > 0) {
            $tabs['no_role'] = Tab::make('No Role')
                    ->badge($noRoleCount)
                    ->badgeColor('gray');
        }

        return $tabs;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Single GROUP BY across model_has_roles + roles + users.
    // ─────────────────────────────────────────────────────────────────────────

    protected function getRoleCounts(): array {
        return DB::table('model_has_roles')
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', 'App\\Models\\User')
                        ->select('roles.name', DB::raw('COUNT(DISTINCT users.id) as count'))
                        ->groupBy('roles.id', 'roles.name')
                        ->orderByDesc('count')
                        ->pluck('count', 'name')
                        ->map(fn($v) => (int) $v)
                        ->toArray();
    }

    protected function roleColor(string $name): string {
        $lower = strtolower($name);
        return match (true) {
            str_contains($lower, 'super') => 'danger',
            str_contains($lower, 'admin') => 'rose',
            str_contains($lower, 'division') => 'warning',
            str_contains($lower, 'co') => 'purple',
            str_contains($lower, 'mentor') => 'info',
            str_contains($lower, 'mentee') => 'success',
            default => 'gray',
        };
    }

    protected function applySearchRelevanceOrdering(Builder $query, string $search): void
    {
        $compactSearch = $this->compactSearchValue($search);

        if ($compactSearch === '') {
            return;
        }

        $exact = $compactSearch;
        $startsWith = "{$compactSearch}%";
        $contains = "%{$compactSearch}%";

        $emailExpression = $this->compactSearchExpression("COALESCE(users.email, '')");
        $fullNameExpression = $this->compactSearchExpression("CONCAT_WS(' ', COALESCE(users.first_name, ''), COALESCE(users.middle_name, ''), COALESCE(users.last_name, ''), COALESCE(users.name, ''))");

        $query->orderByRaw(
            "CASE
                WHEN {$emailExpression} = ? THEN 0
                WHEN {$emailExpression} LIKE ? THEN 1
                WHEN {$emailExpression} LIKE ? THEN 2
                WHEN {$fullNameExpression} LIKE ? THEN 3
                WHEN {$fullNameExpression} LIKE ? THEN 4
                ELSE 5
            END",
            [$exact, $startsWith, $contains, $startsWith, $contains]
        );
    }

    protected function compactSearchExpression(string $expression): string
    {
        $normalized = "LOWER(CAST({$expression} AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";

        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$normalized}, ' ', ''), '.', ''), '_', ''), '-', ''), '@', ''), '+', '')";
    }

    protected function compactSearchValue(string $search): string
    {
        $search = Str::lower(trim($search));

        return preg_replace('/[\s.\-_@+]+/u', '', $search) ?? $search;
    }
}
