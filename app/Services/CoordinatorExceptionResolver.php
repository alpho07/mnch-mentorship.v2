<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CoordinatorExceptionResolver
{
    private const FACILITY_COMPLETION_THRESHOLD_PCT = 40;

    private const FACILITY_ATTENDANCE_THRESHOLD = 0.6;

    private const MENTOR_INACTIVE_DAYS = 14;

    public function resolve(Collection $trainings, Collection $liveClasses, Collection $participants, array $cpdData): array
    {
        $seenMentorIds = [];
        $items = collect();

        $items = $items->merge($this->tier1FacilitiesFallingBehind($trainings, $liveClasses, $participants));
        $items = $items->merge($this->tier2InactiveMentors($trainings, $liveClasses, $seenMentorIds));
        $items = $items->merge($this->tier3ZeroCpdMentors($trainings, $liveClasses, $cpdData, $seenMentorIds));

        return $items->sort(function (array $a, array $b) {
            if ($a['tier'] !== $b['tier']) {
                return $a['tier'] <=> $b['tier'];
            }

            return $a['sort_ts'] <=> $b['sort_ts'];
        })->map(function (array $item) {
            unset($item['sort_ts']);

            return $item;
        })->values()->toArray();
    }

    private function tier1FacilitiesFallingBehind(Collection $trainings, Collection $liveClasses, Collection $participants): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByFacility = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->facility_id
        );

        $items = [];

        foreach ($classesByFacility as $facilityId => $classes) {
            if (! $facilityId) {
                continue;
            }

            $training = $trainingsById->get($classes->first()->training_id);
            $facility = $training?->facility;

            $modulesTotal = $classes->sum(fn ($c) => $c->classModules->count());
            $modulesCompleted = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
            $completionPct = $modulesTotal > 0 ? (int) round(($modulesCompleted / $modulesTotal) * 100) : 0;

            $classIds = $classes->pluck('id');
            $enrolled = $participants->whereIn('mentorship_class_id', $classIds)->count();
            $confirmed = ClassAttendance::whereIn('class_id', $classIds)->count();
            $attendanceRate = $enrolled > 0 ? $confirmed / $enrolled : 1.0;

            $failsCompletion = $modulesTotal > 0 && $completionPct < self::FACILITY_COMPLETION_THRESHOLD_PCT;
            $failsAttendance = $enrolled > 0 && $attendanceRate < self::FACILITY_ATTENDANCE_THRESHOLD;

            if (! $failsCompletion && ! $failsAttendance) {
                continue;
            }

            $items[] = [
                'tier' => 1,
                'label' => 'Review Facility',
                'headline' => ($facility?->name ?? 'A facility')." — {$completionPct}% avg completion across {$classes->count()} class(es)",
                'subtext' => 'Confirmed attendance: '.((int) round($attendanceRate * 100)).'%',
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'facility_id' => $facilityId]),
                'meta' => ['completion_pct' => $completionPct, 'attendance_pct' => (int) round($attendanceRate * 100)],
                'sort_ts' => $completionPct,
            ];
        }

        return $items;
    }

    private function tier2InactiveMentors(Collection $trainings, Collection $liveClasses, array &$seenMentorIds): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByMentor = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->mentor_id
        );

        $items = [];

        foreach ($classesByMentor as $mentorId => $classes) {
            if (! $mentorId || isset($seenMentorIds[$mentorId])) {
                continue;
            }

            $mentor = $trainingsById->get($classes->first()->training_id)?->mentor;
            $classIds = $classes->pluck('id');
            $participantIds = ClassParticipant::whereIn('mentorship_class_id', $classIds)->pluck('id');

            $lastAttendance = ClassAttendance::whereIn('class_id', $classIds)
                ->where('marked_by', $mentorId)
                ->max('marked_at');
            $lastVideoReview = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->max('video_reviewed_at');
            $lastRecommendation = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->max('recommendation_written_at');

            $lastActivity = collect([$lastAttendance, $lastVideoReview, $lastRecommendation])
                ->filter()
                ->map(fn ($d) => Carbon::parse($d))
                ->sortByDesc(fn ($d) => $d->timestamp)
                ->first();

            if (! $lastActivity) {
                $earliestClass = $classes->sortBy('created_at')->first();
                $lastActivity = $earliestClass?->created_at
                    ? Carbon::parse($earliestClass->created_at)
                    : now();
            }

            $daysInactive = (int) $lastActivity->diffInDays(now());
            if ($daysInactive < self::MENTOR_INACTIVE_DAYS) {
                continue;
            }

            $items[] = [
                'tier' => 2,
                'label' => 'Follow Up',
                'headline' => ($mentor?->name ?? 'A mentor')." — inactive {$daysInactive} days",
                'subtext' => 'No reviews, attendance marks, or feedback recorded',
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'mentor_id' => $mentorId]),
                'meta' => ['days_inactive' => $daysInactive],
                'sort_ts' => -$daysInactive,
            ];
            $seenMentorIds[$mentorId] = true;
        }

        return $items;
    }

    private function tier3ZeroCpdMentors(Collection $trainings, Collection $liveClasses, array $cpdData, array &$seenMentorIds): array
    {
        $trainingsById = $trainings->keyBy('id');
        $classesByMentor = $liveClasses->groupBy(
            fn ($class) => $trainingsById->get($class->training_id)?->mentor_id
        );

        $items = [];

        foreach ($classesByMentor as $mentorId => $classes) {
            if (! $mentorId || isset($seenMentorIds[$mentorId])) {
                continue;
            }

            $cpd = $cpdData[$mentorId] ?? null;
            if (! $cpd || ($cpd['total'] ?? 0) > 0) {
                continue;
            }

            $mentor = $trainingsById->get($classes->first()->training_id)?->mentor;
            $classesLed = $classes->count();

            $items[] = [
                'tier' => 3,
                'label' => 'View Mentor',
                'headline' => ($mentor?->name ?? 'A mentor').' — 0 CPD points',
                'subtext' => "{$classesLed} class(es) led, no modules completed",
                'url' => route('analytics.dashboard.index', ['mode' => 'mentor', 'mentor_id' => $mentorId]),
                'meta' => ['classes_led' => $classesLed],
                'sort_ts' => -$classesLed,
            ];
            $seenMentorIds[$mentorId] = true;
        }

        return $items;
    }
}
