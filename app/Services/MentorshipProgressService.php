<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Indicators\IndicatorReportPeriod;
use App\Models\Indicators\IndicatorReportType;
use App\Models\Indicators\IndicatorValue;
use App\Models\Training;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MentorshipProgressService {

    /**
     * Get the headline summary stats for the dashboard top cards.
     */
    public function getHeadlineStats(array $filters = []): array {
        $facilityIds = $this->resolveFacilityIds($filters);

        $validatedPeriods = IndicatorReportPeriod::query()
                ->whereIn('facility_id', $facilityIds)
                ->whereIn('status', ['validated', 'pushed_to_dhis2'])
                ->when($filters['report_type_id'] ?? null, fn($q, $v) => $q->where('report_type_id', $v))
                ->when($filters['year'] ?? null, fn($q, $v) => $q->where('period_year', $v));

        $totalPeriods = $validatedPeriods->count();
        $facilitiesWithData = $validatedPeriods->distinct('facility_id')->count('facility_id');

        // Average overall completion across all validated periods
        $avgCompletion = IndicatorValue::query()
                ->join('indicator_report_periods', 'indicator_values.period_id', '=', 'indicator_report_periods.id')
                ->whereIn('indicator_report_periods.facility_id', $facilityIds)
                ->whereIn('indicator_report_periods.status', ['validated', 'pushed_to_dhis2'])
                ->whereNotNull('indicator_values.computed_percentage')
                ->avg('indicator_values.computed_percentage');

        // Count facilities showing improvement (latest period % > earliest period %)
        $improvingFacilities = $this->countImprovingFacilities($facilityIds, $filters);

        // Total mentorship sessions linked to these facilities
        $mentorshipCount = Training::query()
                ->whereIn('facility_id', $facilityIds)
                ->where('type', 'like', '%mentorship%')
                ->whereNull('deleted_at')
                ->count();

        return [
            'total_reports' => $totalPeriods,
            'facilities_reporting' => $facilitiesWithData,
            'avg_completion' => round($avgCompletion ?? 0, 1),
            'improving_facilities' => $improvingFacilities,
            'mentorship_count' => $mentorshipCount,
        ];
    }

    /**
     * Get per-facility trend: for each facility, earliest vs latest validated report % per report type.
     */
    public function getFacilityTrends(array $filters = []): Collection {
        $facilityIds = $this->resolveFacilityIds($filters);

        $periods = IndicatorReportPeriod::query()
                ->with(['facility:id,name,mfl_code', 'reportType:id,name,color'])
                ->whereIn('facility_id', $facilityIds)
                ->whereIn('status', ['validated', 'pushed_to_dhis2'])
                ->when($filters['report_type_id'] ?? null, fn($q, $v) => $q->where('report_type_id', $v))
                ->when($filters['year'] ?? null, fn($q, $v) => $q->where('period_year', $v))
                ->orderBy('facility_id')
                ->orderBy('report_type_id')
                ->orderBy('period_year')
                ->orderByRaw('COALESCE(period_month, period_quarter * 3)')
                ->get();

        // Attach computed completion % per period
        $periodIds = $periods->pluck('id');
        // Use DB::table to avoid raw-alias property issues on stdClass
        $completions = DB::table('indicator_values')
                ->whereIn('period_id', $periodIds)
                ->groupBy('period_id')
                ->selectRaw('period_id, ROUND(AVG(computed_percentage), 1) as avg_pct')
                ->pluck('avg_pct', 'period_id')
                ->toArray();

        // Fill rate for count/yes_no indicators
        $fillRates = DB::table('indicator_values')
                ->whereIn('period_id', $periodIds)
                ->groupBy('period_id')
                ->selectRaw('period_id, ROUND(
                COUNT(CASE WHEN computed_percentage IS NOT NULL OR count_value IS NOT NULL OR yes_no_value IS NOT NULL THEN 1 END)
                * 100.0 / NULLIF(COUNT(*), 0)
            , 1) as fill_rate')
                ->pluck('fill_rate', 'period_id')
                ->toArray();

        $periods->each(function ($period) use ($completions, $fillRates) {
            $period->avg_pct = $completions[$period->id] ?? null;
            $period->fill_rate = $fillRates[$period->id] ?? 0;
            // Use fill rate as proxy if no proportion indicators
            $period->display_pct = $period->avg_pct ?? $period->fill_rate;
        });

        // Group by facility → report_type, get first/last period
        return $periods
                        ->groupBy(fn($p) => $p->facility_id . '_' . $p->report_type_id)
                        ->map(function ($group) {
                            $first = $group->first();
                            $last = $group->last();
                            $diff = ($last->display_pct !== null && $first->display_pct !== null) ? round($last->display_pct - $first->display_pct, 1) : null;

                            return [
                                'facility_id' => $first->facility_id,
                                'facility_name' => $first->facility?->name ?? 'Unknown',
                                'mfl_code' => $first->facility?->mfl_code,
                                'report_type' => $first->reportType?->name ?? '—',
                                'report_type_color' => $first->reportType?->color ?? 'blue',
                                'periods' => $group->values(),
                                'period_count' => $group->count(),
                                'first_pct' => $first->display_pct,
                                'first_label' => $this->periodLabel($first),
                                'latest_pct' => $last->display_pct,
                                'latest_label' => $this->periodLabel($last),
                                'change' => $diff,
                                'trend' => $diff === null ? 'unknown' : ($diff > 2 ? 'up' : ($diff < -2 ? 'down' : 'stable')),
                            ];
                        })
                        ->values();
    }

    /**
     * Get monthly/quarterly aggregate trend line for charting.
     * Returns periods with average % across all reporting facilities.
     */
    public function getAggregateTrend(array $filters = []): array {
        $facilityIds = $this->resolveFacilityIds($filters);

        $rows = DB::table('indicator_report_periods as irp')
                ->join('indicator_values as iv', 'iv.period_id', '=', 'irp.id')
                ->join('indicator_report_types as irt', 'irp.report_type_id', '=', 'irt.id')
                ->whereIn('irp.facility_id', $facilityIds)
                ->whereIn('irp.status', ['validated', 'pushed_to_dhis2'])
                ->whereNotNull('iv.computed_percentage')
                ->when($filters['report_type_id'] ?? null, fn($q, $v) => $q->where('irp.report_type_id', $v))
                ->when($filters['year'] ?? null, fn($q, $v) => $q->where('irp.period_year', $v))
                ->selectRaw('
                irp.period_year,
                irp.period_month,
                irp.period_quarter,
                irp.report_type_id,
                irt.name as report_type_name,
                irt.color as report_type_color,
                ROUND(AVG(iv.computed_percentage), 1) as avg_pct,
                COUNT(DISTINCT irp.facility_id) as facility_count
            ')
                ->groupBy('irp.period_year', 'irp.period_month', 'irp.period_quarter', 'irp.report_type_id', 'irt.name', 'irt.color')
                ->orderBy('irp.period_year')
                ->orderByRaw('COALESCE(irp.period_month, irp.period_quarter * 3)')
                ->get();

        return $rows->map(function ($row) {
                    $month = isset($row->period_month) ? (int) $row->period_month : null;
                    $quarter = isset($row->period_quarter) ? (int) $row->period_quarter : null;
                    $year = (int) $row->period_year;
                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $label = $month ? (($months[$month - 1] ?? '') . ' ' . $year) : ($quarter ? ('Q' . $quarter . ' ' . $year) : (string) $year);

                    return [
                        'label' => $label,
                        'sort_key' => $year * 100 + ($month ?? ($quarter ? $quarter * 3 : 0)),
                        'avg_pct' => (float) $row->avg_pct,
                        'facility_count' => (int) $row->facility_count,
                        'report_type' => $row->report_type_name ?? '—',
                        'color' => $row->report_type_color ?? 'blue',
                    ];
                })->sortBy('sort_key')->values()->toArray();
    }

    /**
     * Top/bottom performers — facilities by their latest validated report's avg %.
     */
    public function getPerformerRankings(array $filters = [], int $limit = 10): array {
        $facilityIds = $this->resolveFacilityIds($filters);

        // Get latest validated period per facility per report type
        $latestPeriods = IndicatorReportPeriod::query()
                ->select('facility_id', 'report_type_id', DB::raw('MAX(id) as latest_id'))
                ->whereIn('facility_id', $facilityIds)
                ->whereIn('status', ['validated', 'pushed_to_dhis2'])
                ->when($filters['report_type_id'] ?? null, fn($q, $v) => $q->where('report_type_id', $v))
                ->when($filters['year'] ?? null, fn($q, $v) => $q->where('period_year', $v))
                ->groupBy('facility_id', 'report_type_id')
                ->pluck('latest_id');

        $rankings = IndicatorReportPeriod::query()
                ->select(
                        'indicator_report_periods.facility_id',
                        'indicator_report_periods.report_type_id',
                        'indicator_report_periods.period_year',
                        'indicator_report_periods.period_month',
                        'indicator_report_periods.period_quarter',
                        DB::raw('ROUND(AVG(iv.computed_percentage), 1) as avg_pct'),
                        DB::raw('COUNT(DISTINCT iv.id) as value_count')
                )
                ->join('indicator_values as iv', 'iv.period_id', '=', 'indicator_report_periods.id')
                ->whereIn('indicator_report_periods.id', $latestPeriods)
                ->whereNotNull('iv.computed_percentage')
                ->with(['facility:id,name,mfl_code', 'reportType:id,name,color'])
                ->groupBy(
                        'indicator_report_periods.facility_id',
                        'indicator_report_periods.report_type_id',
                        'indicator_report_periods.period_year',
                        'indicator_report_periods.period_month',
                        'indicator_report_periods.period_quarter'
                )
                ->orderByRaw('AVG(iv.computed_percentage) DESC')
                ->get()
                ->map(fn($r) => [
                    'facility_name' => $r->facility?->name ?? 'Unknown',
                    'mfl_code' => $r->facility?->mfl_code,
                    'report_type' => $r->reportType?->name ?? '—',
                    'color' => $r->reportType?->color ?? 'blue',
                    'avg_pct' => $r->avg_pct,
                    'period_label' => $this->periodLabel($r),
        ]);

        return [
            'top' => $rankings->take($limit)->values()->toArray(),
            'bottom' => $rankings->reverse()->take($limit)->values()->toArray(),
        ];
    }

    /**
     * Per-indicator breakdown showing average % across all facilities for each indicator.
     */
    public function getIndicatorBreakdown(array $filters = []): Collection {
        $facilityIds = $this->resolveFacilityIds($filters);

        return DB::table('indicator_values as iv')
                        ->join('indicator_report_periods as irp', 'iv.period_id', '=', 'irp.id')
                        ->join('indicators as ind', 'iv.indicator_id', '=', 'ind.id')
                        ->join('indicator_groups as ig', 'ind.group_id', '=', 'ig.id')
                        ->whereIn('irp.facility_id', $facilityIds)
                        ->whereIn('irp.status', ['validated', 'pushed_to_dhis2'])
                        ->whereNotNull('iv.computed_percentage')
                        ->when($filters['report_type_id'] ?? null, fn($q, $v) => $q->where('irp.report_type_id', $v))
                        ->when($filters['year'] ?? null, fn($q, $v) => $q->where('irp.period_year', $v))
                        ->selectRaw('
                ind.id,
                ind.code,
                ind.name,
                ind.category,
                ind.target_value,
                ig.name as group_name,
                ROUND(AVG(iv.computed_percentage), 1) as avg_pct,
                ROUND(MIN(iv.computed_percentage), 1) as min_pct,
                ROUND(MAX(iv.computed_percentage), 1) as max_pct,
                COUNT(DISTINCT irp.facility_id) as facility_count,
                COUNT(iv.id) as data_points
            ')
                        ->groupBy('ind.id', 'ind.code', 'ind.name', 'ind.category', 'ind.target_value', 'ig.name')
                        ->orderBy('ig.name')
                        ->orderByRaw('AVG(iv.computed_percentage) DESC')
                        ->get()
                        ->map(fn($row) => (array) $row)
                        ->values();
    }

    /**
     * Get filter options for dropdowns.
     */
    public function getFilterOptions(): array {
        $reportTypes = IndicatorReportType::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name', 'id')
                ->toArray();

        $years = IndicatorReportPeriod::query()
                ->whereIn('status', ['validated', 'pushed_to_dhis2'])
                ->distinct()
                ->orderByDesc('period_year')
                ->pluck('period_year')
                ->map(fn($y) => (string) $y)
                ->toArray();

        $counties = DB::table('counties')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();

        return compact('reportTypes', 'years', 'counties');
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function resolveFacilityIds(array $filters): array {
        $user = auth()->user();

        $query = Facility::query()->select('id');

        // County filter (admin/national only)
        if ($filters['county_id'] ?? null) {
            $query->whereHas('subcounty', fn($q) => $q->where('county_id', $filters['county_id']));
        }

        // Facility filter
        if ($filters['facility_id'] ?? null) {
            $query->where('id', $filters['facility_id']);
        }

        // Role scoping
        if ($user->hasRole(['county_mentor_lead'])) {
            $countyIds = $user->counties()->pluck('counties.id');
            $query->whereHas('subcounty', fn($q) => $q->whereIn('county_id', $countyIds));
        } elseif (!$user->hasRole(['super_admin', 'admin', 'national_mentor_lead'])) {
            // Facility user — only their own
            $query->where('id', $user->facility_id);
        }

        return $query->pluck('id')->toArray();
    }

    private function countImprovingFacilities(array $facilityIds, array $filters): int {
        $trends = $this->getFacilityTrends($filters);
        return $trends->where('trend', 'up')->count();
    }

    private function periodLabel($period): string {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        if ($period->period_month) {
            return ($months[$period->period_month - 1] ?? '') . ' ' . $period->period_year;
        }
        if ($period->period_quarter) {
            return 'Q' . $period->period_quarter . ' ' . $period->period_year;
        }
        return (string) $period->period_year;
    }
}
