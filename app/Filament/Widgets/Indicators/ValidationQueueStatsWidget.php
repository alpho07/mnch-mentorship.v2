<?php

namespace App\Filament\Widgets\Indicators;

use App\Models\Indicators\IndicatorReportPeriod;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ValidationQueueStatsWidget extends BaseWidget {

    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool {
        return auth()->check() && auth()->user()->can('widget_ValidationQueueStatsWidget');
    }

    protected function getStats(): array {
        $user = auth()->user();
        $countyId = $user->facility?->subcounty?->county_id ?? $user->counties()->first()?->id;

        $cacheKey = "indicator_validation_widget_{$user->id}";

        $data = Cache::remember($cacheKey, 60, function () use ($user, $countyId) {
            $base = IndicatorReportPeriod::query();

            // County mentor leads scoped to their county; admins/super_admin see all
            if ($user->hasRole('county_mentor_lead') && $countyId) {
                $base->whereHas('facility.subcounty', fn($q) => $q->where('county_id', $countyId));
            }

            $pending = (clone $base)->where('status', IndicatorReportPeriod::STATUS_SUBMITTED)->count();
            $validated = (clone $base)->where('status', IndicatorReportPeriod::STATUS_VALIDATED)->count();
            $rejected = (clone $base)->where('status', IndicatorReportPeriod::STATUS_REJECTED)->count();
            $pushed = (clone $base)->where('status', IndicatorReportPeriod::STATUS_PUSHED_TO_DHIS2)->count();

            // Oldest pending submission age
            $oldest = (clone $base)
                    ->where('status', IndicatorReportPeriod::STATUS_SUBMITTED)
                    ->orderBy('submitted_at')
                    ->value('submitted_at');

            return compact('pending', 'validated', 'rejected', 'pushed', 'oldest');
        });

        $oldestLabel = $data['oldest'] ? \Carbon\Carbon::parse($data['oldest'])->diffForHumans() . ' ago' : null;

        return [
                    Stat::make('Pending Validation', (string) $data['pending'])
                    ->description($oldestLabel ? 'Oldest: ' . $oldestLabel : 'No pending reports')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($data['pending'] > 5 ? 'danger' : ($data['pending'] > 0 ? 'warning' : 'success')),
                    Stat::make('Validated This Month', (string) (
                            IndicatorReportPeriod::where('status', IndicatorReportPeriod::STATUS_VALIDATED)
                            ->whereMonth('validated_at', now()->month)
                            ->whereYear('validated_at', now()->year)
                            ->count()
                            ))
                    ->description('Reports approved in ' . now()->format('F Y'))
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
                    Stat::make('Rejected', (string) $data['rejected'])
                    ->description('Returned for corrections')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color($data['rejected'] > 0 ? 'danger' : 'gray'),
                    Stat::make('Pushed to DHIS2', (string) $data['pushed'])
                    ->description('Successfully synced to national system')
                    ->descriptionIcon('heroicon-m-arrow-up-circle')
                    ->color('info'),
        ];
    }
}
