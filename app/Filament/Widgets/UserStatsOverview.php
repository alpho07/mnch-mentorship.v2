<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserStatsOverview extends BaseWidget {

    protected static ?int $sort = 1;

    protected function getStats(): array {
        // Cache for 2 minutes to avoid hammering the DB on every page load
        $stats = Cache::remember('user_management_stats', 120, function () {
            $base = DB::table('users')->whereNull('deleted_at');

            $total = (clone $base)->count();
            $active = (clone $base)->where('status', 'active')->count();
            $suspended = (clone $base)->where('status', 'suspended')->count();
            $inactive = (clone $base)->where('status', 'inactive')->count();

            // Role counts via model_has_roles pivot
            $roleCounts = DB::table('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                    ->whereNull('users.deleted_at')
                    ->where('model_has_roles.model_type', 'App\\Models\\User')
                    ->select('roles.name', DB::raw('count(*) as count'))
                    ->groupBy('roles.name')
                    ->pluck('count', 'name')
                    ->toArray();

            return compact('total', 'active', 'suspended', 'inactive', 'roleCounts');
        });

        $roleCounts = $stats['roleCounts'];

        return [
                    Stat::make('Total Users', number_format($stats['total']))
                    ->description("{$stats['active']} active · {$stats['inactive']} inactive · {$stats['suspended']} suspended")
                    ->descriptionIcon('heroicon-m-users')
                    ->color('primary'),
                    Stat::make('Mentees', number_format($roleCounts['mentee'] ?? $roleCounts['Mentee'] ?? 0))
                    ->description('Healthcare professionals enrolled in mentorship')
                    ->descriptionIcon('heroicon-m-academic-cap')
                    ->color('success'),
                    Stat::make('Mentors', number_format(
                                    ($roleCounts['mentor'] ?? 0) + ($roleCounts['Mentor'] ?? 0)
                            ))
                    ->description(sprintf(
                                    '%d lead mentors · %d co-mentors',
                                    ($roleCounts['mentor'] ?? $roleCounts['Mentor'] ?? 0),
                                    ($roleCounts['co_mentor'] ?? $roleCounts['Co-Mentor'] ?? $roleCounts['co-mentor'] ?? 0)
                            ))
                    ->descriptionIcon('heroicon-m-briefcase')
                    ->color('warning'),
                    Stat::make('Admin Staff', number_format(
                                    ($roleCounts['super_admin'] ?? 0) +
                                    ($roleCounts['admin'] ?? 0) +
                                    ($roleCounts['division'] ?? 0) +
                                    ($roleCounts['division_lead'] ?? 0)
                            ))
                    ->description('Admins, division leads, super admins')
                    ->descriptionIcon('heroicon-m-shield-check')
                    ->color('danger'),
        ];
    }
}
