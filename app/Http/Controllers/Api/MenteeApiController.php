<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ProgramModuleQuiz;
use App\Models\QuizAttempt;
use App\Services\AttendanceService;
use App\Services\EmoncNotificationService;
use App\Services\QuizAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MenteeApiController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/v1/me/classes
     * Returns all classes the authenticated user is enrolled in.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $participations = ClassParticipant::with([
            'mentorshipClass.training.facility:id,name',
            'mentorshipClass.training.mentor:id,name',
            'mentorshipClass.classModules',
        ])
            ->where('user_id', $userId)
            ->get();

        $data = $participations->map(fn (ClassParticipant $p) => [
            'id' => $p->mentorshipClass->id,
            'name' => $p->mentorshipClass->name,
            'status' => $p->mentorshipClass->status,
            'training_title' => $p->mentorshipClass->training?->title,
            'facility' => $p->mentorshipClass->training?->facility?->name,
            'mentor_name' => $p->mentorshipClass->training?->mentor?->name,
            'progress_percentage' => $p->mentorshipClass->progress_percentage,
            'module_count' => $p->mentorshipClass->module_count,
            'participant_id' => $p->id,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/me/classes/{class}
     * Class detail with module list and attendance flags for the authenticated mentee.
     */
    public function show(Request $request, MentorshipClass $class): JsonResponse
    {
        $userId = $request->user()->id;

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $confirmedModuleIds = ClassAttendance::where('class_id', $class->id)
            ->where('user_id', $userId)
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->flip();

        $progress = $participant->moduleProgress()
            ->with('classModule.programModule')
            ->get();

        $modules = $class->classModules()
            ->with('programModule')
            ->orderBy('order_sequence')
            ->get()
            ->map(fn (ClassModule $m) => [
                'id' => $m->id,
                'name' => $m->programModule?->name ?? 'Module '.$m->order_sequence,
                'status' => $m->status,
                'order_sequence' => $m->order_sequence,
                'attended' => isset($confirmedModuleIds[$m->id]),
                'progress_status' => $progress->firstWhere('class_module_id', $m->id)?->status ?? 'not_started',
            ]);

        return response()->json([
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'status' => $class->status,
                'participant_id' => $participant->id,
                'progress_percentage' => $class->progress_percentage,
                'modules' => $modules,
            ],
        ]);
    }

    /**
     * POST /api/v1/me/classes/{class}/modules/{module}/attend
     * Mentee self-confirms attendance for a module.
     */
    public function attend(Request $request, MentorshipClass $class, ClassModule $module): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $result = $this->attendanceService->confirmModuleAttendance($request->user(), $module);

        $status = $result['success'] ? 200 : 422;

        return response()->json(['message' => $result['message']], $status);
    }

    /**
     * GET /api/v1/me/classes/{class}/modules/{module}
     * Full module detail for the authenticated mentee including content, quizzes, video, activities.
     */
    public function moduleDetail(Request $request, MentorshipClass $class, ClassModule $module): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $progress = MenteeModuleProgress::firstOrCreate(
            [
                'class_participant_id' => $participant->id,
                'class_module_id' => $module->id,
            ],
            ['status' => 'not_started']
        );

        $programModule = $module->programModule?->load(['contents', 'quizzes.questions.options', 'activities']);

        $quizService = app(QuizAttemptService::class);
        $preTestQuizzes = $programModule?->quizzes?->filter(fn ($q) => $q->isPreTest()) ?? collect();
        $postTestQuizzes = $programModule?->quizzes?->filter(fn ($q) => $q->isPostTest()) ?? collect();
        $preTest = $this->buildQuizStatus($preTestQuizzes, $quizService, $progress, 'pre_test_attempt_id');
        $postTest = $this->buildQuizStatus($postTestQuizzes, $quizService, $progress, 'post_test_attempt_id');

        $isEmonc = $this->isEmonc($class);
        $activityIds = $programModule?->activities?->pluck('id')->toArray() ?? [];
        $completedActivityIds = $isEmonc
            ? ClassModuleActivityParticipant::where('class_module_id', $module->id)
                ->where('class_participant_id', $participant->id)
                ->where('status', 'completed')
                ->pluck('activity_id')
                ->toArray()
            : [];

        $contents = $programModule?->contents ?? collect();

        return response()->json([
            'data' => [
                'id' => $module->id,
                'name' => $programModule?->name ?? 'Module',
                'parent_name' => $programModule?->parent?->name,
                'status' => $module->status,
                'progress_status' => $progress->status,
                'is_emonc' => $isEmonc,
                'content_unlocked' => ! $isEmonc || $preTest['completed'] || $progress->status === 'completed' || $progress->status === 'exempted',
                'pre_test' => $preTest,
                'post_test' => $postTest,
                'video' => [
                    'submitted' => $progress->hasSubmittedVideo(),
                    'url' => $progress->hands_on_video_url,
                    'review_status' => $progress->video_review_status,
                    'review_notes' => $progress->video_review_notes,
                ],
                'activities' => $programModule?->activities?->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'completed' => in_array($a->id, $completedActivityIds),
                ])->values(),
                'contents' => [
                    'introductions' => $contents->where('type', 'introduction')->values(),
                    'videos' => $contents->where('type', 'video')->values()->map(fn ($c) => [
                        'id' => $c->id,
                        'title' => $c->title,
                        'video_url' => $c->video_url,
                        'youtube_embed_url' => $c->youtubeEmbedUrl(),
                        'content' => $c->content,
                    ]),
                    'case_scenarios' => $contents->where('type', 'case_scenario')->values(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/me/classes/{class}/modules/{module}/quiz/{quiz}/start
     */
    public function startQuiz(Request $request, MentorshipClass $class, ClassModule $module, ProgramModuleQuiz $quiz): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $attemptType = $request->input('attempt_type', 'pre_test');
        $service = app(QuizAttemptService::class);

        try {
            $attempt = $service->startAttempt($quiz, $request->user(), $attemptType);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'quiz_id' => $quiz->id,
                'attempt_type' => $attemptType,
                'questions' => $quiz->questions()->where('is_active', true)->with('options')->get()->map(fn ($q) => [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id,
                        'option_text' => $o->option_text,
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * POST /api/v1/me/quiz-attempts/{attempt}/submit
     */
    public function submitQuiz(Request $request, QuizAttempt $attempt): JsonResponse
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $service = app(QuizAttemptService::class);

        try {
            $attempt = $service->submitAttempt($attempt, $request->input('responses', []));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $progress = MenteeModuleProgress::where('class_participant_id', function ($q) use ($attempt) {
            $q->select('class_participants.id')
                ->from('class_participants')
                ->where('class_participants.user_id', $attempt->user_id)
                ->whereColumn('class_participants.id', 'mentee_module_progress.class_participant_id')
                ->limit(1);
        })
            ->where('class_module_id', function ($q) {
                $q->select('class_modules.id')
                    ->from('class_modules')
                    ->whereColumn('class_modules.id', 'mentee_module_progress.class_module_id')
                    ->limit(1);
            })
            ->first();

        if ($progress) {
            if ($attempt->attempt_type === 'pre_test') {
                $progress->update(['pre_test_attempt_id' => $attempt->id]);
            } elseif ($attempt->attempt_type === 'post_test') {
                $progress->update(['post_test_attempt_id' => $attempt->id]);
            }
        }

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'score' => $attempt->score,
                'passed' => $attempt->isPassed(),
                'correct_answers' => $attempt->correct_answers,
                'total_questions' => $attempt->total_questions,
            ],
        ]);
    }

    /**
     * POST /api/v1/me/classes/{class}/modules/{module}/video
     */
    public function uploadVideo(Request $request, MentorshipClass $class, ClassModule $module): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $progress = MenteeModuleProgress::firstOrCreate(
            [
                'class_participant_id' => $participant->id,
                'class_module_id' => $module->id,
            ],
            ['status' => 'not_started']
        );

        $inputType = $request->input('input_type', 'file');

        if ($inputType === 'link') {
            $request->validate(['video_link' => ['required', 'url', 'max:2048']]);

            $this->deleteStoredVideo($progress);
            $progress->update([
                'hands_on_video_path' => null,
                'hands_on_video_url' => $request->input('video_link'),
            ]);
        } else {
            $request->validate(['video' => ['required', 'file', 'mimetypes:video/*', 'max:51200']]);

            $this->deleteStoredVideo($progress);
            $path = $request->file('video')->store(
                "mentee-videos/{$participant->id}/{$module->id}",
                'public'
            );
            $progress->update([
                'hands_on_video_path' => $path,
                'hands_on_video_url' => Storage::disk('public')->url($path),
            ]);
        }

        app(EmoncNotificationService::class)->videoSubmitted($progress->fresh());

        return response()->json([
            'message' => 'Video submitted successfully.',
            'data' => [
                'url' => $progress->hands_on_video_url,
                'review_status' => $progress->video_review_status,
            ],
        ]);
    }

    private function deleteStoredVideo(MenteeModuleProgress $progress): void
    {
        if ($progress->hands_on_video_path) {
            Storage::disk('public')->delete($progress->hands_on_video_path);
        }
    }

    private function buildQuizStatus($quizzes, QuizAttemptService $service, MenteeModuleProgress $progress, string $attemptIdColumn): array
    {
        if ($quizzes->isEmpty()) {
            return ['exists' => false, 'passed' => true, 'completed' => true, 'attempt' => null];
        }

        $quiz = $quizzes->first();
        $latestCompleted = $service->getLatestAttempts($progress->classParticipant?->user ?? auth()->user(), $quiz);
        $key = $attemptIdColumn === 'pre_test_attempt_id' ? 'pre_test' : 'post_test';
        $latest = $latestCompleted[$key];

        if ($latest) {
            return [
                'exists' => true,
                'passed' => $latest->isPassed(),
                'completed' => true,
                'score' => $latest->score,
                'attempt_id' => $latest->id,
            ];
        }

        return [
            'exists' => true,
            'passed' => false,
            'completed' => false,
            'score' => null,
            'attempt_id' => null,
        ];
    }

    private function isEmonc(MentorshipClass $class): bool
    {
        $program = $class->training?->program;

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
}
