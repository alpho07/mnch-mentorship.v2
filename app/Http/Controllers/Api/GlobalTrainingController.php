<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTrainingController extends Controller
{
    /**
     * GET /api/v1/trainings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Training::with(['county', 'facility'])
            ->where('type', 'global_training')
            ->latest('start_date');

        // Admins and above-site roles see all; others see only their registrations
        if (!$user->isAboveSite()) {
            $query->whereHas('participants', fn($q) => $q->where('user_id', $user->id));
        }

        // Filter by updated_at if ?since= is provided (delta sync)
        if ($request->filled('since')) {
            $since = \Carbon\Carbon::parse($request->since);
            $query->withTrashed()->where('updated_at', '>', $since);
            $delta = $query->get()->map(fn(Training $t) => [
                'id'            => $t->id,
                'title'         => $t->title,
                'status'        => $t->status,
                'start_date'    => $t->start_date?->toDateString(),
                'end_date'      => $t->end_date?->toDateString(),
                'county'        => $t->county?->name,
                'facility'      => $t->facility?->name,
                'location_type' => $t->location_type,
                'updated_at'    => $t->updated_at?->toIso8601String(),
                'is_trashed'    => $t->trashed(),
            ]);
            return response()->json(['data' => $delta]);
        }

        $trainings = $query->get()->map(fn(Training $t) => [
            'id'            => $t->id,
            'title'         => $t->title,
            'status'        => $t->status,
            'start_date'    => $t->start_date?->toDateString(),
            'end_date'      => $t->end_date?->toDateString(),
            'county'        => $t->county?->name,
            'facility'      => $t->facility?->name,
            'location_type' => $t->location_type,
            'updated_at'    => $t->updated_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $trainings]);
    }

    /**
     * Abort 403 if the user is not permitted to view this training.
     * Above-site roles see all. Others must be registered participants.
     */
    private function authorizeTrainingAccess(Request $request, Training $training): void
    {
        if ($request->user()->isAboveSite()) return;

        $enrolled = $training->participants()
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_if(!$enrolled, 403, 'You are not enrolled in this training.');
    }

    /**
     * GET /api/v1/trainings/{training}
     */
    public function show(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        $this->authorizeTrainingAccess($request, $training);
        $training->load(['county', 'facility', 'program']);

        return response()->json([
            'data' => [
                'id'               => $training->id,
                'title'            => $training->title,
                'status'           => $training->status,
                'start_date'       => $training->start_date?->toDateString(),
                'end_date'         => $training->end_date?->toDateString(),
                'county'           => $training->county?->name,
                'facility'         => $training->facility?->name,
                'facility_mfl'     => $training->facility?->mfl_code,
                'program'          => $training->program?->name,
                'location_type'    => $training->location_type,
                'online_link'      => $training->online_link,
                'max_participants' => $training->max_participants,
                'description'      => $training->description,
                'notes'            => $training->notes,
                'target_audience'  => $training->target_audience,
                'learning_outcomes'=> $training->learning_outcomes,
            ],
        ]);
    }

    /**
     * POST /api/v1/trainings/{training}/enroll
     */
    public function enroll(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        abort_if(!in_array($training->status, ['upcoming', 'active']), 422, 'Enrollment is not open for this training.');

        $already = $training->participants()->where('user_id', $request->user()->id)->exists();
        abort_if($already, 409, 'You are already enrolled in this training.');

        $participant = $training->participants()->create([
            'user_id'           => $request->user()->id,
            'completion_status' => 'registered',
        ]);

        return response()->json([
            'data' => ['participant_id' => $participant->id, 'status' => $participant->completion_status],
        ], 201);
    }

    /**
     * POST /api/v1/trainings/{training}/attendance
     */
    public function attendance(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);

        $participant = $training->participants()->where('user_id', $request->user()->id)->first();
        abort_if(!$participant, 403, 'You are not enrolled in this training.');

        $participant->update(['completion_status' => 'in_progress']);

        return response()->json(['message' => 'Attendance recorded.']);
    }

    /**
     * GET /api/v1/trainings/{training}/participants
     */
    public function participants(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        $this->authorizeTrainingAccess($request, $training);

        $participants = $training->participants()
            ->with('user')
            ->get()
            ->map(fn($p) => [
                'id'                => $p->id,
                'user_id'           => $p->user_id,
                'name'              => $p->user?->name,
                'completion_status' => $p->completion_status ?? 'registered',
            ]);

        return response()->json(['data' => $participants]);
    }
}
