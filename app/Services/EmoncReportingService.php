<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Support\Collection;

class EmoncReportingService
{
    /**
     * Build an EmONC-specific report for a class.
     *
     * @return array{
     *   mentees: Collection,
     *   modules: Collection,
     *   pending_video_reviews: int,
     *   pending_mentor_approvals: int,
     *   pending_drmh_approvals: int,
     *   certified_count: int,
     * }
     */
    public function buildClassReport(MentorshipClass $class): array
    {
        $class->load([
            'training.program',
            'classModules.programModule.parent',
            'classModules.programModule.activities',
        ]);

        $participants = ClassParticipant::with('user')
            ->where('mentorship_class_id', $class->id)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $participantIds = $participants->pluck('id');
        $moduleIds = $class->classModules->pluck('id');

        $progress = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
            ->whereIn('class_module_id', $moduleIds)
            ->with(['preTestAttempt', 'postTestAttempt'])
            ->get();

        $activityCompletions = ClassModuleActivityParticipant::whereIn('class_module_id', $moduleIds)
            ->whereIn('class_participant_id', $participantIds)
            ->where('status', 'completed')
            ->get();

        $progressByKey = $progress->keyBy(fn ($p) => "{$p->class_participant_id}:{$p->class_module_id}");
        $activityCompletedByKey = $activityCompletions
            ->groupBy(fn ($a) => "{$a->class_participant_id}:{$a->class_module_id}")
            ->map(fn ($group) => $group->pluck('activity_id')->toArray());

        $mentees = $participants->map(function (ClassParticipant $participant) use ($class, $progressByKey, $activityCompletedByKey) {
            $moduleRows = $class->classModules->map(function (ClassModule $classModule) use ($participant, $progressByKey, $activityCompletedByKey) {
                $key = "{$participant->id}:{$classModule->id}";
                $prog = $progressByKey->get($key);
                $activityIds = $classModule->programModule?->activities?->pluck('id')->toArray() ?? [];
                $completedActivityIds = $activityCompletedByKey->get($key, []);
                $activityCompletionPct = count($activityIds) > 0
                    ? round((count(array_intersect($activityIds, $completedActivityIds)) / count($activityIds)) * 100)
                    : 100;

                return [
                    'class_module' => $classModule,
                    'progress_status' => $prog?->status ?? 'not_started',
                    'pre_test_score' => $prog?->preTestAttempt?->score,
                    'post_test_score' => $prog?->postTestAttempt?->score,
                    'video_review_status' => $prog?->video_review_status ?? 'pending',
                    'has_video' => $prog?->hasSubmittedVideo() ?? false,
                    'activities_total' => count($activityIds),
                    'activities_completed' => count(array_intersect($activityIds, $completedActivityIds)),
                    'activity_completion_pct' => $activityCompletionPct,
                ];
            });

            $modulesTotal = $moduleRows->count();
            $modulesCompleted = $moduleRows->whereIn('progress_status', ['completed', 'exempted'])->count();
            $videosPassed = $moduleRows->where('video_review_status', 'passed')->count();
            $videosPending = $moduleRows->where('video_review_status', 'pending')->where('has_video', true)->count();

            return [
                'participant' => $participant,
                'user' => $participant->user,
                'modules' => $moduleRows,
                'modules_total' => $modulesTotal,
                'modules_completed' => $modulesCompleted,
                'completion_pct' => $modulesTotal > 0 ? round(($modulesCompleted / $modulesTotal) * 100) : 0,
                'videos_passed' => $videosPassed,
                'videos_pending' => $videosPending,
                'mentor_approved' => $participant->isMentorApproved(),
                'head_drmh_approved' => $participant->isHeadDrmhApproved(),
                'certified' => $participant->isCertified(),
            ];
        });

        $pendingVideoReviews = $progress
            ->where('video_review_status', 'pending')
            ->where(fn ($p) => $p->hasSubmittedVideo())
            ->count();

        $pendingMentorApprovals = $participants
            ->where('status', 'completed')
            ->whereNull('mentor_approved_at')
            ->count();

        $pendingDrmhApprovals = $participants
            ->where('status', 'completed')
            ->whereNotNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->count();

        $certifiedCount = $participants->whereNotNull('head_drmh_approved_at')->count();

        return [
            'mentees' => $mentees,
            'modules' => $class->classModules,
            'pending_video_reviews' => $pendingVideoReviews,
            'pending_mentor_approvals' => $pendingMentorApprovals,
            'pending_drmh_approvals' => $pendingDrmhApprovals,
            'certified_count' => $certifiedCount,
        ];
    }

    /**
     * Pending items across all mentorships visible to a user.
     */
    public function pendingItemsForUser(int $userId, array $trainingIds): array
    {
        if (empty($trainingIds)) {
            return [
                'video_reviews' => 0,
                'mentor_approvals' => 0,
                'drmh_approvals' => 0,
            ];
        }

        $classIds = MentorshipClass::whereIn('training_id', $trainingIds)->pluck('id');
        $participantIds = ClassParticipant::whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->pluck('id');

        $videoReviews = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
            ->whereIn('class_module_id', function ($q) use ($classIds) {
                $q->select('id')
                    ->from('class_modules')
                    ->whereIn('mentorship_class_id', $classIds);
            })
            ->where('video_review_status', 'pending')
            ->where(function ($q) {
                $q->whereNotNull('hands_on_video_url')
                    ->orWhereNotNull('hands_on_video_path');
            })
            ->count();

        $mentorApprovals = ClassParticipant::whereIn('mentorship_class_id', $classIds)
            ->where('status', 'completed')
            ->whereNull('mentor_approved_at')
            ->count();

        $drmApprovals = ClassParticipant::whereIn('mentorship_class_id', $classIds)
            ->where('status', 'completed')
            ->whereNotNull('mentor_approved_at')
            ->whereNull('head_drmh_approved_at')
            ->count();

        return [
            'video_reviews' => $videoReviews,
            'mentor_approvals' => $mentorApprovals,
            'drmh_approvals' => $drmApprovals,
        ];
    }

    /**
     * Pending video review items across all mentorships visible to a user.
     *
     * @return array<int, array{
     *   progress: MenteeModuleProgress,
     *   participant: ClassParticipant,
     *   mentee: User|null,
     *   class: MentorshipClass,
     *   module: ClassModule,
     *   programModule: ProgramModule|null,
     *   training: Training|null,
     *   url: string,
     * }>
     */
    public function pendingVideoReviewItemsForUser(int $userId, array $trainingIds): array
    {
        if (empty($trainingIds)) {
            return [];
        }

        $classIds = MentorshipClass::whereIn('training_id', $trainingIds)->pluck('id');
        $participantIds = ClassParticipant::whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->pluck('id');

        $progress = MenteeModuleProgress::with([
            'classParticipant.user',
            'classModule.programModule',
        ])
            ->whereIn('class_participant_id', $participantIds)
            ->whereIn('class_module_id', function ($q) use ($classIds) {
                $q->select('id')
                    ->from('class_modules')
                    ->whereIn('mentorship_class_id', $classIds);
            })
            ->where('video_review_status', 'pending')
            ->where(function ($q) {
                $q->whereNotNull('hands_on_video_url')
                    ->orWhereNotNull('hands_on_video_path');
            })
            ->get();

        // Eager load classes and trainings
        $moduleIds = $progress->pluck('class_module_id')->unique();
        $modules = ClassModule::with('programModule', 'mentorshipClass.training')
            ->whereIn('id', $moduleIds)
            ->get()
            ->keyBy('id');

        return $progress->map(function (MenteeModuleProgress $item) use ($modules) {
            $module = $modules->get($item->class_module_id);
            $class = $module?->mentorshipClass;
            $participant = $item->classParticipant;
            $mentee = $participant?->user;

            return [
                'progress' => $item,
                'participant' => $participant,
                'mentee' => $mentee,
                'class' => $class,
                'module' => $module,
                'programModule' => $module?->programModule,
                'training' => $class?->training,
                'url' => $class && $module
                    ? \App\Filament\Resources\MentorshipTrainingResource::getUrl('module-mentees', [
                        'training' => $class->training_id,
                        'class' => $class->id,
                        'module' => $module->id,
                    ])
                    : null,
            ];
        })
            ->filter(fn ($item) => $item['url'] !== null)
            ->values()
            ->toArray();
    }
}
