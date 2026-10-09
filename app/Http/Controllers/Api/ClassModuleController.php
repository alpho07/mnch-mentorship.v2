<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Services\ModuleUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassModuleController extends Controller
{
    use AuthorizesClassAccess;

    /**
     * GET /api/v1/modules/{module}/resources
     *
     * Everything the web "Module Resources" page shows for the module's
     * programme module: introduction, objectives, videos, case scenarios,
     * equipment, debrief, pre/post tests (titles only) and the attached
     * resources, each with a short-lived signed download link.
     */
    public function resources(Request $request, ClassModule $module): JsonResponse
    {
        $user = $request->user();

        // Enrolled mentees can read their module's resources; everyone else needs
        // mentor / co-mentor / admin access to the class.
        $isParticipant = \App\Models\ClassParticipant::where('mentorship_class_id', $module->mentorship_class_id)
            ->where('user_id', $user->id)->exists();

        if (! $isParticipant) {
            $this->authorizeClassAccess($module->mentorshipClass);
        }

        $pm = $module->programModule;

        if (! $pm) {
            return response()->json(['data' => null]);
        }

        $pm->load([
            'contents' => fn ($q) => $q->where('is_active', true)->orderBy('order_sequence'),
            'quizzes.questions.options',
            'resources' => fn ($q) => $q->with(['primaryFile', 'category', 'resourceType']),
        ]);

        $rubric = \App\Models\ModuleRubric::where('program_module_id', $pm->id)
            ->where('is_active', true)->orderBy('order_sequence')->first();

        $content = fn (string $type) => $pm->contents->where('type', $type)->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title,
            'content' => $c->content,
            'video_url' => $c->video_url,
            'embed_url' => $c->isVideo() ? $c->youtubeEmbedUrl() : null,
            'manual_reference_url' => $c->manual_reference_url,
        ])->values();

        // Mentors see the full test (questions, options, answer key, like the web page);
        // mentees only get titles and counts — their quiz flow serves the questions.
        $tests = fn (string $kind) => $pm->quizzes
            ->filter(fn ($q) => $kind === 'pre' ? $q->isPreTest() : $q->isPostTest())
            ->map(fn ($q) => [
                'id' => $q->id,
                'title' => $q->title,
                'question_count' => $q->questions->count(),
                'questions' => $isParticipant ? [] : $q->questions->map(fn ($question) => [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'explanation' => $question->explanation,
                    'options' => $question->options->map(fn ($o) => [
                        'option_text' => $o->option_text,
                        'is_correct' => (bool) $o->is_correct,
                    ])->values(),
                ])->values(),
            ])
            ->values();

        $resources = $pm->resources->map(function ($r) use ($user) {
            $file = $r->primaryFile;
            $canAccess = $r->canUserAccess($user);

            return [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->excerpt,
                'category' => $r->category?->name,
                'type' => $r->resourceType?->slug ?? 'document',
                'type_label' => $r->resourceType?->name,
                'external_url' => filled($r->external_url) ? $r->external_url : null,
                'can_access' => $canAccess,
                'file' => $file && $file->exists() && $canAccess ? [
                    'name' => $file->original_name,
                    'size' => $file->formatted_file_size,
                    'mime' => $file->file_type,
                    'download_url' => \App\Http\Controllers\Api\ResourceFileDownloadController::signedUrl($file, $user),
                ] : null,
            ];
        })->values();

        return response()->json(['data' => [
            'module' => ['id' => $module->id, 'name' => $pm->name, 'description' => $pm->description],
            'introduction' => $content('introduction'),
            'objectives' => array_values($pm->objectives ?? []),
            'pre_tests' => $tests('pre'),
            'videos' => $content('video'),
            'case_scenarios' => $content('case_scenario'),
            'equipment' => array_values($rubric->equipment_supplies ?? []),
            'debrief' => array_values($rubric->debrief_questions ?? []),
            'post_tests' => $tests('post'),
            'resources' => $resources,
        ]]);
    }

    /**
     * GET /api/v1/classes/{class}/modules
     */
    public function index(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $modules = $class->classModules()
            ->with(['programModule', 'sessions'])
            ->orderBy('order_sequence')
            ->get()
            ->map(fn (ClassModule $m) => [
                'id' => $m->id,
                'program_module_id' => $m->program_module_id,
                'name' => $m->programModule?->name ?? 'Module '.$m->order_sequence,
                'status' => $m->status,
                'order_sequence' => $m->order_sequence,
                'session_count' => $m->sessions->count(),
                'started_at' => $m->started_at?->toIso8601String(),
                'completed_at' => $m->completed_at?->toIso8601String(),
                'requires_assessment' => $m->requires_assessment,
            ]);

        return response()->json(['data' => $modules]);
    }

    /**
     * POST /api/v1/classes/{class}/modules
     *
     * If the requested program module has child tracks, one ClassModule is
     * created for each track. Otherwise a single ClassModule is created.
     */
    public function store(Request $request, MentorshipClass $class, ModuleUsageService $service): JsonResponse
    {
        $this->authorizeClassAccess($class);
        abort_if($class->status === 'completed' || $class->status === 'cancelled', 422, 'Cannot add modules to a completed or cancelled class.');

        $request->validate(['program_module_id' => 'required|integer|exists:program_modules,id']);

        $programModule = ProgramModule::with('children')->findOrFail($request->program_module_id);

        // If this is a parent module with tracks, the tracks become the class modules.
        $modulesToCreate = $programModule->children->isNotEmpty()
            ? $programModule->children
            : collect([$programModule]);

        foreach ($modulesToCreate as $moduleToCreate) {
            abort_if(
                $class->classModules()->where('program_module_id', $moduleToCreate->id)->exists(),
                409,
                'One or more resulting modules are already added to the class.'
            );
        }

        $createdModules = [];
        $service->assignModulesToClass(
            $class->training()->first(),
            $class,
            [$request->program_module_id],
            auth()->id(),
            function (ClassModule $classModule) use ($class, &$createdModules) {
                if ($class->status === 'active') {
                    $classModule->start();
                    $classModule->refresh();
                }
                $createdModules[] = $classModule;
            }
        );

        return response()->json([
            'data' => collect($createdModules)->map(fn (ClassModule $module) => [
                'id' => $module->id,
                'program_module_id' => $module->program_module_id,
                'name' => $module->programModule?->name ?? 'Module '.$module->order_sequence,
                'status' => $module->status,
                'order_sequence' => $module->order_sequence,
                'session_count' => $module->sessions()->count(),
                'requires_assessment' => (bool) $module->requires_assessment,
                'started_at' => $module->started_at?->toIso8601String(),
                'completed_at' => $module->completed_at?->toIso8601String(),
            ]),
        ], 201);
    }

    /**
     * DELETE /api/v1/modules/{module}
     */
    public function destroy(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);
        abort_if($module->status !== 'not_started', 422, 'Cannot remove a module that has been started.');

        $module->delete();

        return response()->json(['message' => 'Module removed.']);
    }

    /**
     * POST /api/v1/modules/{module}/start
     */
    public function start(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        if (! $module->canStart()) {
            return response()->json([
                'message' => "Module cannot be started. Current status: {$module->status}.",
            ], 422);
        }

        $module->start();
        $module->refresh();

        return response()->json([
            'message' => 'Module started.',
            'data' => [
                'id' => $module->id,
                'status' => $module->status,
                'started_at' => $module->started_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/modules/{module}/complete
     */
    public function complete(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        if (! $module->canComplete()) {
            return response()->json([
                'message' => "Module cannot be completed. Current status: {$module->status}.",
            ], 422);
        }

        $module->complete();
        $module->refresh();

        return response()->json([
            'message' => 'Module completed.',
            'data' => [
                'id' => $module->id,
                'status' => $module->status,
                'completed_at' => $module->completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/modules/{module}/sessions
     */
    public function sessions(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $sessions = $module->sessions()
            ->with('facilitator:id,name')
            ->orderBy('session_number')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'session_number' => $s->session_number,
                'title' => $s->title,
                'description' => $s->description,
                'status' => $s->status,
                'scheduled_date' => $s->scheduled_date?->toDateString(),
                'scheduled_time' => $s->scheduled_time,
                'actual_date' => $s->actual_date?->toDateString(),
                'actual_time' => $s->actual_time,
                'duration_minutes' => $s->duration_minutes,
                'location' => $s->location,
                'notes' => $s->notes,
                'attendance_taken' => (bool) $s->attendance_taken,
                'facilitator' => $s->facilitator?->name,
            ]);

        return response()->json(['data' => $sessions]);
    }
}
