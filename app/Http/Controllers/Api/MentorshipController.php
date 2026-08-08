<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipClass;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipController extends Controller
{
    /**
     * List facility_mentorship trainings for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Training::query()
            ->where('type', 'facility_mentorship')
            ->where('status', '!=', 'draft')
            ->with([
                'mentor:id,name',
                'facility:id,name,mfl_code',
                'county:id,name',
                'program:id,name',
                'mentorshipClasses' => fn ($q) => $q
                    ->withCount('participants')
                    ->withCount(['classModules as completed_modules_count' => fn ($q) => $q->where('status', 'completed')])
                    ->withCount('classModules as total_modules_count'),
            ])
            ->withCount('mentorshipClasses as class_count');

        if ($user->hasRole('super_admin')) {
            // Super admin sees all mentorships including soft-deleted
            $query->withTrashed();
        } elseif (! $user->isAboveSite()) {
            $query->forMentorOrCoMentor($user->id);
        }

        if ($request->filled('since')) {
            $since = \Carbon\Carbon::parse($request->since);
            $query->withTrashed()->where('updated_at', '>', $since);
        }

        $mentorships = $query->latest()->get();

        $data = $mentorships->map(function (Training $t) {
            $classes = $t->mentorshipClasses;
            $participantCount = $classes->sum('participants_count');
            $totalModules = $classes->sum('total_modules_count');
            $completedModules = $classes->sum('completed_modules_count');
            $progressPct = $totalModules > 0
                ? (int) round($completedModules / $totalModules * 100)
                : 0;

            return [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'is_pilot' => (bool) $t->is_pilot,
                'is_trashed' => $t->deleted_at !== null,
                'class_count' => (int) $t->class_count,
                'participant_count' => $participantCount,
                'progress_percentage' => $progressPct,
                'start_date' => $t->start_date?->toDateString(),
                'end_date' => $t->end_date?->toDateString(),
                'facility' => $t->facility?->name,
                'county' => $t->county?->name,
                'program' => $t->program?->name,
                'program_id' => $t->program_id,
                'mentor_name' => $t->mentor?->name,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Show a single mentorship training with full details.
     */
    public function show(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $training->load([
            'mentor:id,name,email',
            'facility:id,name,mfl_code',
            'county:id,name',
            'program:id,name',
            'mentorshipClasses' => fn ($q) => $q
                ->withCount(['classModules as module_count', 'participants as participant_count']),
        ]);

        $classes = $training->mentorshipClasses->map(fn (MentorshipClass $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'start_date' => $c->start_date?->toDateString(),
            'end_date' => $c->end_date?->toDateString(),
            'module_count' => $c->module_count ?? 0,
            'participant_count' => $c->participant_count ?? 0,
            'progress_percentage' => $c->progress_percentage,
        ]);

        return response()->json([
            'data' => [
                'id' => $training->id,
                'title' => $training->title,
                'description' => $training->description,
                'status' => $training->status,
                'is_pilot' => (bool) $training->is_pilot,
                'start_date' => $training->start_date?->toDateString(),
                'end_date' => $training->end_date?->toDateString(),
                'location_type' => $training->location_type,
                'facility_id' => $training->facility_id,
                'facility' => $training->facility?->name,
                'facility_mfl' => $training->facility?->mfl_code,
                'county_id' => $training->county_id,
                'county' => $training->county?->name,
                'program' => $training->program?->name,
                'program_id' => $training->program_id,
                'max_participants' => $training->max_participants,
                'mentor_name' => $training->mentor?->name,
                'mentor_email' => $training->mentor?->email,
                'notes' => $training->notes,
                'class_count' => $training->mentorshipClasses->count(),
                'classes' => $classes,
            ],
        ]);
    }

    public function classes(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $classes = $training->mentorshipClasses()
            ->withCount(['classModules', 'participants'])
            ->get()
            ->map(fn (MentorshipClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'start_date' => $c->start_date?->toDateString(),
                'end_date' => $c->end_date?->toDateString(),
                'module_count' => $c->class_modules_count,
                'participant_count' => $c->participants_count,
                'progress_percentage' => $c->progress_percentage,
            ]);

        return response()->json(['data' => $classes]);
    }

    public function classDetail(Request $request, Training $training, MentorshipClass $class): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        abort_if($class->training_id !== $training->id, 404);

        $class->load([
            'classModules.programModule',
            'classModules.sessions',
            'participants.user:id,name,email',
        ]);

        $modules = $class->classModules->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->programModule?->name ?? 'Module '.$m->order_sequence,
            'status' => $m->status,
            'order_sequence' => $m->order_sequence,
            'session_count' => $m->sessions->count(),
            'started_at' => $m->started_at?->toIso8601String(),
            'completed_at' => $m->completed_at?->toIso8601String(),
            'requires_assessment' => (bool) $m->requires_assessment,
            'notes' => $m->notes,
        ]);

        $mentees = $class->participants->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->user?->name ?? 'Unknown',
            'email' => $p->user?->email,
        ]);

        return response()->json([
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'status' => $class->status,
                'start_date' => $class->start_date?->toDateString(),
                'end_date' => $class->end_date?->toDateString(),
                'notes' => $class->notes,
                'progress_percentage' => $class->progress_percentage,
                'participant_count' => $class->participants->count(),
                'modules' => $modules,
                'mentees' => $mentees,
            ],
        ]);
    }

    public function createClass(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        $class = $training->mentorshipClasses()->create([
            ...$data,
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'enrollment_token' => \Illuminate\Support\Str::random(32),
        ]);

        return response()->json([
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'status' => $class->status,
                'start_date' => $class->start_date?->toDateString(),
                'end_date' => $class->end_date?->toDateString(),
                'notes' => $class->notes,
                'module_count' => 0,
                'participant_count' => 0,
                'progress_percentage' => 0,
            ],
        ], 201);
    }

    public function updateClass(Request $request, Training $training, MentorshipClass $class): JsonResponse
    {
        $this->authoriseMentorship($request, $training);
        abort_if($class->training_id !== $training->id, 404);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        $class->update($data);
        $class->refresh();

        return response()->json([
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'status' => $class->status,
                'start_date' => $class->start_date?->toDateString(),
                'end_date' => $class->end_date?->toDateString(),
                'notes' => $class->notes,
            ],
        ]);
    }

    public function deleteClass(Request $request, Training $training, MentorshipClass $class): JsonResponse
    {
        $this->authoriseMentorship($request, $training);
        abort_if($class->training_id !== $training->id, 404);
        abort_if(! $class->canBeDeleted(), 422, 'Cannot delete a class that has completed sessions.');

        $class->delete();

        return response()->json(['message' => 'Class deleted.']);
    }

    private function authoriseMentorship(Request $request, Training $training): void
    {
        abort_if($training->type !== 'facility_mentorship', 404);
        $user = $request->user();
        if ($user->isAboveSite()) {
            return;
        }
        $isMentor = $training->mentor_id === $user->id;
        $isCoMentor = $training->acceptedCoMentors()->where('user_id', $user->id)->exists();
        abort_if(! $isMentor && ! $isCoMentor, 403, 'Not authorised for this mentorship.');
    }
}
