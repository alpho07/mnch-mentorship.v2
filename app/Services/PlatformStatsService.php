<?php

namespace App\Services;

use App\Models\ClassParticipant;
use App\Models\Training;
use Illuminate\Support\Facades\Cache;

/**
 * Headline platform stats shown on the public auth pages (login, register,
 * password reset) — cached since these pages are unauthenticated and hit
 * far more often than the underlying counts change.
 */
class PlatformStatsService
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array{mentorships: int, mentees: int, facilities: int, counties: int}
     */
    public function summary(): array
    {
        return Cache::remember('platform_stats.summary', self::CACHE_TTL_SECONDS, function () {
            $mentorships = Training::where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->whereIn('status', ['active', 'completed']);

            return [
                'mentorships' => (clone $mentorships)->count(),
                'mentees' => ClassParticipant::whereIn('status', ['enrolled', 'completed'])
                    ->whereHas('mentorshipClass.training', function ($query) {
                        $query->where('type', 'facility_mentorship')
                            ->where('is_pilot', false)
                            ->whereIn('status', ['active', 'completed']);
                    })
                    ->distinct('user_id')
                    ->count('user_id'),
                'facilities' => (clone $mentorships)->whereNotNull('facility_id')
                    ->distinct('facility_id')
                    ->count('facility_id'),
                'counties' => (clone $mentorships)->whereNotNull('county_id')
                    ->distinct('county_id')
                    ->count('county_id'),
            ];
        });
    }
}
