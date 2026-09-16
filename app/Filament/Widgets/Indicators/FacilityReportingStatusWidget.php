<?php

namespace App\Filament\Widgets\Indicators;

use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\Indicators\IndicatorReportType;
use App\Services\Indicators\IndicatorReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FacilityReportingStatusWidget extends BaseWidget {

    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = null;

    // Only show to facility-level users (not validators/admins)
    public static function canView(): bool {
        if (!auth()->check())
            return false;
        $user = auth()->user();
        return $user->facility_id !== null;
    }

    protected function getStats(): array {
        $user = auth()->user();
        $facilityId = $user->facility_id;

        if (!$facilityId)
            return [];

        $cacheKey = "indicator_facility_widget_{$facilityId}";

        $data = Cache::remember($cacheKey, 120, function () use ($facilityId) {
            $assignment = FacilityIndicatorAssignment::where('facility_id', $facilityId)->first();
            $isSetup = $assignment?->is_locked && !empty($assignment->enabled_report_types);

            if (!$isSetup) {
                return ['setup' => false];
            }

            $service = app(IndicatorReportingService::class);

            // Count reports by status for this facility
            $periods = IndicatorReportPeriod::where('facility_id', $facilityId)->get();

            $draft = $periods->where('status', IndicatorReportPeriod::STATUS_DRAFT)->count();
            $submitted = $periods->where('status', IndicatorReportPeriod::STATUS_SUBMITTED)->count();
            $validated = $periods->where('status', IndicatorReportPeriod::STATUS_VALIDATED)->count();
            $rejected = $periods->where('status', IndicatorReportPeriod::STATUS_REJECTED)->count();

            // Current period completion (most recent draft)
            $currentDraft = IndicatorReportPeriod::where('facility_id', $facilityId)
                    ->where('status', IndicatorReportPeriod::STATUS_DRAFT)
                    ->orderByDesc('updated_at')
                    ->first();

            $currentCompletion = $currentDraft ? $service->getOverallCompletion($currentDraft) : null;

            return [
                'setup' => true,
                'draft' => $draft,
                'submitted' => $submitted,
                'validated' => $validated,
                'rejected' => $rejected,
                'currentCompletion' => $currentCompletion,
                'reportTypeCount' => count($assignment->enabled_report_types ?? []),
            ];
        });

        if (!($data['setup'] ?? false)) {
            return [
                        Stat::make('Setup Status', 'Not Configured')
                        ->description('Facility setup required before reporting')
                        ->descriptionIcon('heroicon-m-exclamation-triangle')
                        ->color('warning'),
            ];
        }

        $stats = [];

        // Current period completion
        if ($data['currentCompletion']) {
            $pct = $data['currentCompletion']['percentage'];
            $stats[] = Stat::make('Current Draft', $pct . '% Complete')
                    ->description(
                            $data['currentCompletion']['filled'] . ' / ' . $data['currentCompletion']['total']
                            . ' indicators filled'
                    )
                    ->descriptionIcon('heroicon-m-pencil-square')
                    ->color($pct === 100 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'));
        }

        // Submitted (awaiting validation)
        $stats[] = Stat::make('Awaiting Validation', (string) $data['submitted'])
                ->description($data['submitted'] === 0 ? 'No reports pending review' : 'Submitted reports')
                ->descriptionIcon('heroicon-m-clock')
                ->color($data['submitted'] > 0 ? 'info' : 'gray');

        // Validated total
        $stats[] = Stat::make('Validated Reports', (string) $data['validated'])
                ->description('Approved and ready for DHIS2')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($data['validated'] > 0 ? 'success' : 'gray');

        // Rejected (needs attention)
        if ($data['rejected'] > 0) {
            $stats[] = Stat::make('Needs Correction', (string) $data['rejected'])
                    ->description('Rejected — please review and resubmit')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger');
        }

        return $stats;
    }
}
