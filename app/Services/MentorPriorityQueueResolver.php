<?php

namespace App\Services;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Carbon\Carbon;

class MentorPriorityQueueResolver
{
    private const INACTIVE_DAYS = 14;

    private const STRUGGLING_THRESHOLD_PCT = 40;

    private const LOW_ATTENDANCE_THRESHOLD = 0.6;

    public function __construct(private EmoncReportingService $reportingService)
    {
    }

    public function resolve(User $mentor, array $trainingIds): array
    {
        if (empty($trainingIds)) {
            return [];
        }

        $seenUserIds = [];
        $items = collect();

        $items = $items->merge($this->tier1PendingVideoReviews($mentor, $trainingIds, $seenUserIds));
        $items = $items->merge($this->tier2PendingApprovals($trainingIds, $seenUserIds));

        $classIds = MentorshipClass::whereIn('training_id', $trainingIds)->pluck('id');
        $participants = ClassParticipant::with('user', 'mentorshipClass.training.program')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        $items = $items->merge($this->tier3InactiveMentees($participants, $seenUserIds));
        $items = $items->merge($this->tier4StrugglingMentees($participants, $seenUserIds));
        $items = $items->merge($this->tier5LowAttendanceClasses($classIds, $participants));

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

    private function tier1PendingVideoReviews(User $mentor, array $trainingIds, array &$seenUserIds): array
    {
        $reviews = $this->reportingService->pendingVideoReviewItemsForUser($mentor->id, $trainingIds);
        $items = [];

        foreach ($reviews as $review) {
            $userId = $review['mentee']?->id;
            if (! $userId || isset($seenUserIds[$userId])) {
                continue;
            }

            $items[] = [
                'tier' => 1,
                'label' => 'Review Video',
                'headline' => ($review['mentee']?->full_name ?? $review['mentee']?->name ?? 'A mentee').' — hands-on video ready for review',
                'subtext' => $review['programModule']?->name ?? 'Module',
                'url' => $review['url'],
                'meta' => [],
                'sort_ts' => optional($review['progress']->updated_at)->timestamp ?? 0,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier2PendingApprovals(array $trainingIds, array &$seenUserIds): array
    {
        $trainings = Training::whereIn('id', $trainingIds)->with('program')->get();
        $items = [];

        foreach ($trainings as $training) {
            if (! $this->isEmonc($training->program?->name)) {
                continue;
            }

            $classes = MentorshipClass::where('training_id', $training->id)->get();

            foreach ($classes as $class) {
                $participants = ClassParticipant::with('user', 'mentorshipClass')
                    ->where('mentorship_class_id', $class->id)
                    ->whereNull('mentor_approved_at')
                    ->get();

                foreach ($participants as $participant) {
                    $userId = $participant->user_id;
                    if (isset($seenUserIds[$userId]) || ! $participant->hasCompletedAllModules()) {
                        continue;
                    }

                    $items[] = [
                        'tier' => 2,
                        'label' => 'Approve Mentee',
                        'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee').' — ready for your approval',
                        'subtext' => $class->name ?? 'Class',
                        'url' => MentorshipTrainingResource::getUrl('class-mentees', [
                            'training' => $training->id,
                            'class' => $class->id,
                        ]),
                        'meta' => [],
                        'sort_ts' => optional($participant->completed_at)->timestamp ?? 0,
                    ];
                    $seenUserIds[$userId] = true;
                }
            }
        }

        return $items;
    }

    private function tier3InactiveMentees($participants, array &$seenUserIds): array
    {
        $items = [];

        foreach ($participants->groupBy('user_id') as $userId => $userParticipants) {
            if (isset($seenUserIds[$userId])) {
                continue;
            }

            $participantIds = $userParticipants->pluck('id');
            $hasIncomplete = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->whereNotIn('status', ['completed', 'exempted'])
                ->exists();

            if (! $hasIncomplete) {
                continue;
            }

            $lastProgress = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)->max('updated_at');
            $lastAttendance = ClassAttendance::where('user_id', $userId)
                ->whereIn('class_id', $userParticipants->pluck('mentorship_class_id'))
                ->max('marked_at');

            $lastActivity = collect([$lastProgress, $lastAttendance])
                ->filter()
                ->map(fn ($d) => Carbon::parse($d))
                ->sortByDesc(fn ($d) => $d->timestamp)
                ->first();

            if (! $lastActivity) {
                $earliestEnrollment = $userParticipants->min('enrolled_at');
                $lastActivity = $earliestEnrollment ? Carbon::parse($earliestEnrollment) : now();
            }

            $daysInactive = $lastActivity->diffInDays(now());
            if ($daysInactive < self::INACTIVE_DAYS) {
                continue;
            }

            $participant = $userParticipants->first();
            $class = $participant->mentorshipClass;

            $items[] = [
                'tier' => 3,
                'label' => 'Follow Up',
                'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee')." — inactive {$daysInactive} days",
                'subtext' => $class?->name ?? 'Class',
                'url' => $class
                    ? MentorshipTrainingResource::getUrl('class-mentees', [
                        'training' => $class->training_id,
                        'class' => $class->id,
                    ])
                    : '#',
                'meta' => ['days_inactive' => $daysInactive],
                'sort_ts' => -$daysInactive,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier4StrugglingMentees($participants, array &$seenUserIds): array
    {
        $items = [];

        foreach ($participants->groupBy('user_id') as $userId => $userParticipants) {
            if (isset($seenUserIds[$userId])) {
                continue;
            }

            $participantIds = $userParticipants->pluck('id');
            $progressRows = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)->get();
            $total = $progressRows->count();

            if ($total === 0) {
                continue;
            }

            $done = $progressRows->whereIn('status', ['completed', 'exempted'])->count();
            $pct = (int) round(($done / $total) * 100);

            if ($pct >= self::STRUGGLING_THRESHOLD_PCT) {
                continue;
            }

            $participant = $userParticipants->first();
            $class = $participant->mentorshipClass;

            $items[] = [
                'tier' => 4,
                'label' => 'Support Mentee',
                'headline' => ($participant->user?->full_name ?? $participant->user?->name ?? 'A mentee')." — {$pct}% complete",
                'subtext' => $class?->name ?? 'Class',
                'url' => $class
                    ? MentorshipTrainingResource::getUrl('class-mentees', [
                        'training' => $class->training_id,
                        'class' => $class->id,
                    ])
                    : '#',
                'meta' => ['completion_pct' => $pct],
                'sort_ts' => $pct,
            ];
            $seenUserIds[$userId] = true;
        }

        return $items;
    }

    private function tier5LowAttendanceClasses($classIds, $participants): array
    {
        $classes = MentorshipClass::whereIn('id', $classIds)->get();
        $items = [];

        foreach ($classes as $class) {
            $enrolled = $participants->where('mentorship_class_id', $class->id)->count();
            if ($enrolled === 0) {
                continue;
            }

            $confirmed = ClassAttendance::where('class_id', $class->id)->count();
            $rate = $confirmed / $enrolled;

            if ($rate >= self::LOW_ATTENDANCE_THRESHOLD) {
                continue;
            }

            $pct = (int) round($rate * 100);

            $items[] = [
                'tier' => 5,
                'label' => 'Review Class',
                'headline' => ($class->name ?? 'A class')." — {$pct}% attendance",
                'subtext' => 'Confirmed attendance below 60%',
                'url' => MentorshipTrainingResource::getUrl('classes', ['record' => $class->training_id]),
                'meta' => ['attendance_rate' => $pct],
                'sort_ts' => $pct,
            ];
        }

        return $items;
    }

    private function isEmonc(?string $programName): bool
    {
        $name = strtolower($programName ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }
}
