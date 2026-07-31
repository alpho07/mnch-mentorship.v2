<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MenteeNextActionResolver
{
    public function resolve(User $mentee): array
    {
        $participants = ClassParticipant::with([
            'mentorshipClass.training.program',
            'mentorshipClass.classModules.programModule.quizzes',
            'mentorshipClass.classModules.sessions',
            'moduleProgress',
        ])
            ->where('user_id', $mentee->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        $candidates = collect();

        foreach ($participants as $participant) {
            $class = $participant->mentorshipClass;
            if (! $class) {
                continue;
            }

            $isEmonc = $this->isEmonc($class->training?->program?->name);
            $progressByModule = $participant->moduleProgress->keyBy('class_module_id');

            foreach ($class->classModules as $classModule) {
                if ($classModule->status === 'not_started') {
                    continue;
                }

                $progress = $progressByModule->get($classModule->id);
                if (! $progress) {
                    continue;
                }

                $candidate = $this->evaluateModule($participant, $classModule, $progress, $isEmonc);
                if ($candidate) {
                    $candidates->push($candidate);
                }
            }
        }

        if ($candidates->isEmpty()) {
            return $this->onTrackAction($participants);
        }

        return $candidates->sort(function (array $a, array $b) {
            if ($a['tier'] !== $b['tier']) {
                return $a['tier'] <=> $b['tier'];
            }
            if ($a['completion_fraction'] !== $b['completion_fraction']) {
                return $b['completion_fraction'] <=> $a['completion_fraction'];
            }

            return $b['sort_ts'] <=> $a['sort_ts'];
        })->first();
    }

    private function evaluateModule(
        ClassParticipant $participant,
        ClassModule $classModule,
        $progress,
        bool $isEmonc
    ): ?array {
        $moduleName = $classModule->programModule?->name ?? 'this module';
        $moduleUrl = route('mentee.class.module', [
            'class' => $participant->mentorship_class_id,
            'classModule' => $classModule->id,
        ]);

        if ($isEmonc && $progress->video_review_status === 'failed') {
            return [
                'tier' => 1,
                'label' => 'Review Mentor Feedback',
                'headline' => 'Your hands-on video needs changes',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => ['video_review_notes' => $progress->video_review_notes],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->video_reviewed_at)->timestamp ?? 0,
            ];
        }

        $confirmed = ClassAttendance::where('user_id', $participant->user_id)
            ->where('class_module_id', $classModule->id)
            ->exists();

        if ($classModule->attendance_link_active && $classModule->status === 'in_progress' && ! $confirmed) {
            return [
                'tier' => 2,
                'label' => 'Confirm Attendance',
                'headline' => 'Confirm your attendance',
                'subtext' => $moduleName,
                'url' => route('module.attend', ['token' => $classModule->attendance_token]),
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($classModule->started_at)->timestamp ?? 0,
            ];
        }

        $hasPostTestQuiz = $isEmonc
            && $classModule->programModule
            && $classModule->programModule->quizzes->contains(fn ($q) => $q->isPostTest());

        if ($isEmonc
            && $hasPostTestQuiz
            && $progress->hasSubmittedVideo()
            && $progress->isVideoPassed()
            && is_null($progress->post_test_attempt_id)
        ) {
            return [
                'tier' => 4,
                'label' => 'Take Assessment',
                'headline' => 'Take your post-test',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->video_reviewed_at)->timestamp ?? 0,
            ];
        }

        if ($isEmonc && $progress->status === 'in_progress' && is_null($progress->pre_test_attempt_id)) {
            return [
                'tier' => 5,
                'label' => 'Start Module',
                'headline' => 'Start your pre-test',
                'subtext' => $moduleName,
                'url' => $moduleUrl,
                'meta' => [],
                'completion_fraction' => 0.0,
                'sort_ts' => optional($progress->started_at)->timestamp ?? 0,
            ];
        }

        if ($progress->status === 'in_progress') {
            $fraction = $this->completionFraction($participant, $classModule, $isEmonc);

            return [
                'tier' => 3,
                'label' => 'Continue Learning',
                'headline' => 'Continue your current module',
                'subtext' => $moduleName.' — '.((int) round($fraction * 100)).'% completed',
                'url' => $moduleUrl,
                'meta' => ['completion_percent' => (int) round($fraction * 100)],
                'completion_fraction' => $fraction,
                'sort_ts' => optional($progress->updated_at)->timestamp ?? 0,
            ];
        }

        return null;
    }

    private function completionFraction(ClassParticipant $participant, ClassModule $classModule, bool $isEmonc): float
    {
        if ($isEmonc) {
            $total = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->where('class_participant_id', $participant->id)
                ->count();

            if ($total === 0) {
                return 0.0;
            }

            $done = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->where('class_participant_id', $participant->id)
                ->where('status', 'completed')
                ->count();

            return $done / $total;
        }

        $totalSessions = $classModule->sessions->count();
        if ($totalSessions === 0) {
            return 0.0;
        }

        $attended = $classModule->sessions->filter(
            fn ($session) => $session->attendanceRecords()
                ->where('class_participant_id', $participant->id)
                ->where('status', 'present')
                ->exists()
        )->count();

        return $attended / $totalSessions;
    }

    private function onTrackAction(Collection $participants): array
    {
        $nextSession = null;

        foreach ($participants as $participant) {
            foreach ($participant->mentorshipClass?->classModules ?? [] as $classModule) {
                foreach ($classModule->sessions as $session) {
                    if ($session->status !== 'scheduled' || ! $session->scheduled_date) {
                        continue;
                    }
                    if (Carbon::parse($session->scheduled_date)->isPast()) {
                        continue;
                    }
                    if (! $nextSession || Carbon::parse($session->scheduled_date)->lt(Carbon::parse($nextSession->scheduled_date))) {
                        $nextSession = $session;
                    }
                }
            }
        }

        if ($nextSession) {
            return [
                'tier' => 6,
                'label' => 'View Class',
                'headline' => "You're on track",
                'subtext' => 'Next session: '.Carbon::parse($nextSession->scheduled_date)->format('D, M j')
                    .($nextSession->scheduled_time ? ' at '.Carbon::parse($nextSession->scheduled_time)->format('H:i') : ''),
                'url' => route('mentee.class.progress', ['class' => $nextSession->classModule->mentorship_class_id]),
                'meta' => [],
            ];
        }

        $certifiedParticipant = $participants->first(fn ($p) => $p->isCertified());
        if ($certifiedParticipant) {
            return [
                'tier' => 6,
                'label' => 'Download Certificate',
                'headline' => "You're certified!",
                'subtext' => $certifiedParticipant->mentorshipClass?->name ?? '',
                'url' => route('reports.class.certificate', [
                    $certifiedParticipant->mentorship_class_id,
                    $certifiedParticipant->id,
                ]),
                'meta' => [],
            ];
        }

        return [
            'tier' => 6,
            'label' => 'View Class',
            'headline' => "You're on track",
            'subtext' => 'Nothing needs your attention right now.',
            'url' => route('mentee.class.progress', ['class' => $participants->first()->mentorship_class_id]),
            'meta' => [],
        ];
    }

    private function isEmonc(?string $programName): bool
    {
        $name = strtolower($programName ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }
}
