<?php

namespace App\Http\Controllers;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Models\RubricAssessment;
use App\Services\EmoncNotificationService;
use App\Services\QuizAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MenteeClassProgressController extends Controller
{
    public function show(int $classId)
    {
        $class = MentorshipClass::with([
            'training' => fn ($q) => $q->withTrashed(),
            'classModules.programModule',
            'classModules.sessions',
        ])
            ->findOrFail($classId);

        $training = $class->training;
        abort_if(is_null($training), 404, 'Mentorship program not found.');

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Module progress — load classModule so we get attendance_token & attendance_link_active
        $moduleProgress = MenteeModuleProgress::where('class_participant_id', $participant->id)
            ->with([
                'classModule.programModule' => fn ($q) => $q->with('quizzes'),
            ])
            ->orderBy('created_at')
            ->get();

        // Which class_module_ids has this user already confirmed attendance for?
        $confirmedModuleIds = ClassAttendance::where('class_id', $class->id)
            ->where('user_id', Auth::id())
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->toArray();

        // ── Module stats ───────────────────────────────────────────────────
        $totalCount = $moduleProgress->count();
        $completedCount = $moduleProgress->whereIn('status', ['completed', 'exempted'])->count();
        $exemptedCount = $moduleProgress->where('status', 'exempted')->count();
        $inProgressCount = $moduleProgress->where('status', 'in_progress')->count();
        $notStartedCount = $moduleProgress->where('status', 'not_started')->count();
        $completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

        // ── Attendance stats ───────────────────────────────────────────────
        $attendanceCount = count($confirmedModuleIds);
        $totalSessions = $class->classModules->where('status', 'in_progress')->count() + $class->classModules->where('status', 'completed')->count();
        $attendanceRate = $totalSessions > 0 ? round(($attendanceCount / $totalSessions) * 100) : 0;

        // ── Assessment stats ───────────────────────────────────────────────
        $avgAssessmentScore = $moduleProgress->whereNotNull('assessment_score')->avg('assessment_score');
        $assessedModules = $moduleProgress->where('assessment_status', 'passed')->count();
        $failedModules = $moduleProgress->where('assessment_status', 'failed')->count();
        $pendingModules = $moduleProgress
            ->where('assessment_status', 'pending')
            ->where('status', 'completed')
            ->count();

        $programName = strtolower($training->program?->name ?? '');
        $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');

        // ── Next actionable step for EmONC classes ───────────────────────────
        $nextUp = null;
        if ($isEmonc) {
            foreach ($moduleProgress as $progress) {
                $classModule = $progress->classModule;
                if (! $classModule) {
                    continue;
                }

                if (in_array($progress->status, ['completed', 'exempted'])) {
                    continue;
                }

                // Only surface modules the mentee has actually attended
                if ($progress->status === 'not_started') {
                    continue;
                }

                $quizzes = $classModule->programModule?->quizzes ?? collect();
                $hasPreTest = $quizzes->contains(fn ($q) => $q->isPreTest());
                $hasPostTest = $quizzes->contains(fn ($q) => $q->isPostTest());

                $preTestAttempted = ! $hasPreTest || $progress->pre_test_attempt_id !== null;
                $postTestCompleted = ! $hasPostTest || $progress->post_test_attempt_id !== null;
                $hasSubmittedVideo = $progress->hasSubmittedVideo();
                $videoPassed = $progress->video_review_status === 'passed';

                $steps = [];
                if (! $preTestAttempted) {
                    $steps[] = 'Start Pre-test';
                }
                if ($preTestAttempted && ! $hasSubmittedVideo) {
                    $steps[] = 'Submit Hands-on Video';
                }
                if ($preTestAttempted && $hasSubmittedVideo && ! $videoPassed) {
                    $steps[] = 'Awaiting Mentor Video Review';
                }
                if ($preTestAttempted && $videoPassed && ! $postTestCompleted) {
                    $steps[] = 'Take Post-test';
                }

                if (! empty($steps)) {
                    $nextUp = [
                        'module_id' => $classModule->id,
                        'module_name' => $classModule->programModule?->name ?? 'Module',
                        'steps' => $steps,
                        'first_step' => $steps[0],
                    ];
                    break;
                }
            }
        }

        return view('mentee.class-progress', compact(
            'participant',
            'class',
            'training',
            'moduleProgress',
            'confirmedModuleIds',
            'totalCount',
            'completedCount',
            'exemptedCount',
            'inProgressCount',
            'notStartedCount',
            'completionRate',
            'attendanceCount',
            'totalSessions',
            'attendanceRate',
            'avgAssessmentScore',
            'assessedModules',
            'failedModules',
            'pendingModules',
            'isEmonc',
            'nextUp',
        ));
    }

    public function module(int $classId, int $classModuleId)
    {
        $class = MentorshipClass::with('training')->findOrFail($classId);
        $classModule = ClassModule::with([
            'programModule' => fn ($q) => $q->with(['parent', 'contents', 'quizzes.questions.options', 'activities', 'resources']),
        ])->findOrFail($classModuleId);

        abort_if($classModule->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Gate 1: Mentor must have started this module before a mentee can access it
        if (! in_array($classModule->status, ['in_progress', 'completed'])) {
            return redirect()
                ->route('mentee.class.progress', ['class' => $classId])
                ->with('info', 'This module hasn\'t been opened yet. Your mentor will start it when the session begins.');
        }

        $progress = MenteeModuleProgress::firstOrCreate(
            [
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
            ],
            ['status' => 'not_started']
        );

        // Gate 2: Mentee must confirm attendance before accessing module content
        if ($progress->status === 'not_started') {
            $attendanceOpen = $classModule->attendance_link_active && $classModule->attendance_token;

            return redirect()
                ->route('mentee.class.progress', ['class' => $classId])
                ->with(
                    'info',
                    $attendanceOpen
                        ? 'Please confirm your attendance for this module first, then you can continue.'
                        : 'Waiting for your mentor to open the attendance link before you can start this module.'
                );
        }

        $program = $class->training->program;
        $isEmonc = $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');

        $activityIds = $classModule->programModule?->activities?->pluck('id')->toArray() ?? [];
        $enrolledActivityIds = [];
        if ($isEmonc && ! empty($activityIds)) {
            $enrolledActivityIds = ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
                ->where('class_participant_id', $participant->id)
                ->whereIn('activity_id', $activityIds)
                ->pluck('activity_id')
                ->toArray();
        }

        $quizService = app(QuizAttemptService::class);
        $preTestQuizzes = $classModule->programModule?->quizzes?->filter(fn ($q) => $q->isPreTest()) ?? collect();
        $postTestQuizzes = $classModule->programModule?->quizzes?->filter(fn ($q) => $q->isPostTest()) ?? collect();

        $preTestStatus = $this->buildQuizStatus($preTestQuizzes, $quizService, $progress, 'pre_test_attempt_id');
        $postTestStatus = $this->buildQuizStatus($postTestQuizzes, $quizService, $progress, 'post_test_attempt_id');

        $preTestAttempted = $preTestStatus['completed'] ?? false;
        $postTestCompleted = $postTestStatus['completed'] ?? false;

        $canAccessContent = ! $isEmonc || $preTestAttempted || $progress->status === 'completed' || $progress->status === 'exempted';
        $hasSubmittedVideo = $progress->hasSubmittedVideo();
        $canTakePostTest = $canAccessContent && $progress->isVideoPassed();

        $contents = $classModule->programModule?->contents ?? collect();
        $introductions = $contents->where('type', 'introduction');
        $videos = $contents->where('type', 'video');
        $caseScenarios = $contents->where('type', 'case_scenario');
        $caseScenarioProgressions = $contents->where('type', 'case_scenario_progression');
        $expectedLearningOutcomes = $contents->where('type', 'expected_learning_outcome');

        // "Sessions" list — a parent module with tracks (e.g. PPH) is never itself assignable
        // to a class (EmoncModulePicker only lets mentors add individual tracks), so this list
        // lives on each TRACK's own page instead: siblings under the same parent that are also
        // in THIS class, so a mentee doing one PPH track can navigate to the other sessions.
        $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV'];
        $sessions = collect();
        if ($classModule->programModule?->isTrack()) {
            $siblingProgramModuleIds = $classModule->programModule->parent
                ?->children()->orderBy('order_sequence')->pluck('id') ?? collect();

            $siblingClassModules = ClassModule::where('mentorship_class_id', $class->id)
                ->whereIn('program_module_id', $siblingProgramModuleIds)
                ->with('programModule')
                ->get()
                ->sortBy(fn ($cm) => $siblingProgramModuleIds->search($cm->program_module_id))
                ->values();

            $siblingProgressByClassModuleId = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->whereIn('class_module_id', $siblingClassModules->pluck('id'))
                ->pluck('status', 'class_module_id');

            $sessions = $siblingClassModules->map(function ($siblingCm, $i) use ($romanNumerals, $classModule, $siblingProgressByClassModuleId, $class) {
                $track = $siblingCm->programModule;

                return [
                    'label' => 'Session '.($romanNumerals[$i] ?? ($i + 1)).': '.preg_replace('/^Track\s*\d+:\s*/i', '', $track->name),
                    'isCurrent' => $siblingCm->id === $classModule->id,
                    'status' => $siblingProgressByClassModuleId[$siblingCm->id] ?? 'not_started',
                    'url' => route('mentee.class.module', [$class->id, $siblingCm->id]),
                ];
            });
        }

        $objectives = $classModule->programModule?->objectives ?? [];
        $workplan = $classModule->programModule?->content ?? [];

        $moduleRubric = ModuleRubric::where('program_module_id', $classModule->program_module_id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->first();

        // Determine if the module has any usable content at all
        $hasIntroContent = (bool) $classModule->programModule?->description || $introductions->isNotEmpty();
        $hasActivities = ! empty($enrolledActivityIds);
        $hasAnyContent = $hasIntroContent
            || $preTestStatus['exists']
            || $postTestStatus['exists']
            || $caseScenarios->isNotEmpty()
            || $hasActivities;

        // Eager-load training relations needed for the side panels
        $class->load('training.program', 'training.mentor', 'training.facility');
        $mentee = Auth::user()->load('facility.subcounty.county', 'cadre');

        // Completed activity IDs for the right-panel score card
        $completedActivityIds = \App\Models\ClassModuleActivityParticipant::where('class_module_id', $classModule->id)
            ->where('class_participant_id', $participant->id)
            ->where('status', 'completed')
            ->pluck('activity_id')
            ->toArray();

        // Rubric assessment: latest result for this mentee on this module
        $rubricAssessment = RubricAssessment::whereHas(
            'rubric',
            fn ($q) => $q->where('program_module_id', $classModule->program_module_id)
        )
            ->where('mentee_id', Auth::id())
            ->with(['rubric', 'mentor'])
            ->latest('assessed_at')
            ->first();

        return view('mentee.module-detail', compact(
            'class',
            'classModule',
            'participant',
            'progress',
            'isEmonc',
            'enrolledActivityIds',
            'completedActivityIds',
            'preTestStatus',
            'postTestStatus',
            'canAccessContent',
            'canTakePostTest',
            'hasSubmittedVideo',
            'introductions',
            'videos',
            'caseScenarios',
            'caseScenarioProgressions',
            'expectedLearningOutcomes',
            'sessions',
            'objectives',
            'workplan',
            'moduleRubric',
            'hasIntroContent',
            'hasActivities',
            'hasAnyContent',
            'mentee',
            'rubricAssessment',
        ));
    }

    private function buildQuizStatus($quizzes, QuizAttemptService $service, MenteeModuleProgress $progress, string $attemptIdColumn): array
    {
        if ($quizzes->isEmpty()) {
            return ['exists' => false, 'passed' => true, 'completed' => true, 'attempt' => null, 'quiz' => null];
        }

        $quiz = $quizzes->first();

        // Scope strictly to the attempt stored on this progress record.
        // Never query globally by user+quiz — the same ProgramModule/quiz is reused across
        // different classes, so a global lookup bleeds results from other enrollments.
        $attemptId = $progress->$attemptIdColumn;
        if ($attemptId) {
            $attempt = QuizAttempt::with(['responses.option'])->find($attemptId);

            // Validate it belongs to this module's quiz (heals any previously contaminated records)
            if ($attempt && $attempt->completed_at && $attempt->program_module_quiz_id === $quiz->id) {
                return [
                    'exists' => true,
                    'passed' => $attempt->isPassed(),
                    'completed' => true,
                    'attempt' => $attempt,
                    'quiz' => $quiz,
                ];
            }

            // Attempt is contaminated (from another class) or incomplete — clear it
            $progress->update([$attemptIdColumn => null]);
        }

        return [
            'exists' => true,
            'passed' => false,
            'completed' => false,
            'attempt' => null,
            'quiz' => $quiz,
        ];
    }

    public function startQuiz(int $classId, int $classModuleId, ProgramModuleQuiz $quiz)
    {
        $class = MentorshipClass::findOrFail($classId);
        $classModule = ClassModule::findOrFail($classModuleId);
        abort_if($classModule->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $service = app(QuizAttemptService::class);
        $attemptType = request()->input('attempt_type', 'pre_test');

        if ($attemptType === 'post_test') {
            $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->where('class_module_id', $classModule->id)
                ->first();

            if (! $progress || ! $progress->isVideoPassed()) {
                return back()->with('error', 'Your hands-on video must be reviewed and marked as passed by your mentor before you can take the post-test.');
            }
        }

        try {
            $attempt = $service->startAttempt($quiz, Auth::user(), $attemptType);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('mentee.class.module', [$class->id, $classModule->id])
            ->with('quiz_attempt_id', $attempt->id);
    }

    public function submitQuiz(int $classId, int $classModuleId, QuizAttempt $attempt)
    {
        $class = MentorshipClass::findOrFail($classId);
        $classModule = ClassModule::findOrFail($classModuleId);
        abort_if($classModule->mentorship_class_id !== $class->id, 404);
        abort_if($attempt->user_id !== Auth::id(), 403);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $progress = MenteeModuleProgress::firstOrCreate(
            [
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
            ],
            ['status' => 'not_started']
        );

        $responses = request()->input('responses', []);
        $service = app(QuizAttemptService::class);

        try {
            $attempt = $service->submitAttempt($attempt, $responses);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($attempt->attempt_type === 'pre_test') {
            $progress->update(['pre_test_attempt_id' => $attempt->id]);
        } elseif ($attempt->attempt_type === 'post_test') {
            $progress->update(['post_test_attempt_id' => $attempt->id]);
        }

        // Notify mentor for EmONC classes
        $class->load('training.program');
        $programName = strtolower($class->training?->program?->name ?? '');
        if (str_contains($programName, 'maternal') && str_contains($programName, 'emonc')) {
            app(\App\Services\EmoncNotificationService::class)->quizSubmitted($progress->fresh(), $attempt);
        }

        return redirect()->route('mentee.class.module', [$class->id, $classModule->id])
            ->with('success', "Quiz submitted. Score: {$attempt->score}%");
    }

    public function uploadHandsOnVideo(Request $request, int $classId, int $classModuleId)
    {
        $class = MentorshipClass::findOrFail($classId);
        $classModule = ClassModule::findOrFail($classModuleId);
        abort_if($classModule->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $progress = MenteeModuleProgress::firstOrCreate(
            [
                'class_participant_id' => $participant->id,
                'class_module_id' => $classModule->id,
            ],
            ['status' => 'not_started']
        );

        $inputType = $request->input('video_input_type', 'file');

        if ($inputType === 'link') {
            $request->validate([
                'hands_on_video_link' => ['required', 'url', 'max:2048'],
            ]);

            $this->deleteStoredVideo($progress);

            $progress->update([
                'hands_on_video_path' => null,
                'hands_on_video_url' => $request->input('hands_on_video_link'),
            ]);

            return back()->with('success', 'Hands-on video link saved successfully.');
        }

        $request->validate([
            'hands_on_video' => ['required', 'file', 'mimes:mp4,mov,avi,mkv,webm,m4v,3gp,ogg', 'max:51200'],
        ]);

        $this->deleteStoredVideo($progress);

        $path = $request->file('hands_on_video')->store(
            "mentee-videos/{$participant->id}/{$classModule->id}",
            'public'
        );

        $progress->update([
            'hands_on_video_path' => $path,
            'hands_on_video_url' => Storage::disk('public')->url($path),
        ]);

        app(EmoncNotificationService::class)->videoSubmitted($progress->fresh());

        return back()->with('success', 'Hands-on video uploaded successfully.');
    }

    private function deleteStoredVideo(MenteeModuleProgress $progress): void
    {
        if ($progress->hands_on_video_path) {
            Storage::disk('public')->delete($progress->hands_on_video_path);
        }
    }
}
