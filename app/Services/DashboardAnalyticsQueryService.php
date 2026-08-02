<?php

namespace App\Services;

use App\Models\County;
use App\Models\Facility;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Small, focused read-only queries for MNCHGPT's dashboard-analytics tools —
 * applies the same isAboveSite()/scopedCountyIds()/scopedFacilityIds() rules
 * (documented in CLAUDE.md) directly, rather than reusing
 * AnalyticsDashboardController's methods, which are large and
 * HTTP-response-oriented (built for the map UI, not a clean data return).
 */
class DashboardAnalyticsQueryService
{
    public function countyCoverageSummary(User $user, string $countyName): ?array
    {
        $county = County::whereRaw('LOWER(name) = ?', [strtolower($countyName)])->first();

        if (! $county || ! in_array($county->id, $user->scopedCountyIds()->all(), true)) {
            return null;
        }

        $facilityIds = Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $county->id))->pluck('id');

        return [
            'county' => $county->name,
            'facilities' => $facilityIds->count(),
            'mentorships' => Training::where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->whereIn('facility_id', $facilityIds)
                ->count(),
            'mentees' => \App\Models\ClassParticipant::whereHas('mentorshipClass.training', fn (Builder $q) => $q
                ->where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->whereIn('facility_id', $facilityIds))
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    public function programSummary(User $user, string $programName): ?array
    {
        $program = Program::whereRaw('LOWER(name) = ?', [strtolower($programName)])->first();

        if (! $program) {
            return null;
        }

        $facilityIds = $user->scopedFacilityIds();

        $trainings = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->where('program_id', $program->id)
            ->whereIn('facility_id', $facilityIds)
            ->with('facility.subcounty.county')
            ->get();

        $byCounty = $trainings->groupBy(fn ($t) => $t->facility?->subcounty?->county?->name ?? 'Unknown')
            ->map->count();

        return [
            'program' => $program->name,
            'mentorships' => $trainings->count(),
            'by_county' => $byCounty->toArray(),
        ];
    }

    public function trainingCompletionStats(User $user, ?string $programName = null): array
    {
        $query = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->whereIn('facility_id', $user->scopedFacilityIds());

        if ($programName) {
            $query->whereHas('program', fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($programName)]));
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
        ];
    }
}
