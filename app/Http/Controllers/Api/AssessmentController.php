<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAssessmentRequest;
use App\Http\Requests\Api\UpdateAssessmentRequest;
use App\Http\Resources\Api\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Services\DynamicScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller {

    public function __construct(
            private readonly DynamicScoringService $scoringService
    ) {
        
    }

    /**
     * GET /api/v1/assessments
     *
     * Returns paginated assessments for the authenticated assessor.
     * Supports ?status=completed|in_progress|draft and ?search=
     */
    public function index(Request $request): JsonResponse {
        $user = $request->user();

        $query = Assessment::with([
                    'facility.subcounty.county',
                    'sectionScores.section',
                    'teamMembers',
                ])
                ->latest();

        if ($user->hasRole('super_admin')) {
            // Super admin sees everything including soft-deleted
            $query->withTrashed();
        } elseif (!$user->isAboveSite() && !$user->hasRole('admin')) {
            // Assessors see records they created and assessments shared with
            // them by a team lead.
            $query->where(function ($query) use ($user) {
                $query->where('assessor_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('teamMembers', fn ($team) => $team->where('users.id', $user->id));
            });
        }
        // isAboveSite()/admin roles see all non-deleted

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('facility', fn($q) =>
                            $q->where('name', 'like', "%{$term}%")
                            ->orWhere('mfl_code', 'like', "%{$term}%")
            );
        }

        if ($request->filled('type')) {
            $query->where('assessment_type', $request->type);
        }

        // Delta sync: return all records updated after the given timestamp (no pagination)
        if ($request->filled('since')) {
            $since = \Carbon\Carbon::parse($request->since);
            $query->withTrashed()->where('updated_at', '>', $since);
            $delta = $query->get();
            $data = $delta->map(function (Assessment $a) {
                return [
                    'id'         => $a->id,
                    'updated_at' => $a->updated_at?->toIso8601String(),
                    'is_trashed' => $a->trashed(),
                ];
            });
            return response()->json(['data' => $data]);
        }

        $assessments = $query->paginate($request->input('per_page', 20));

        return response()->json([
                    'data' => AssessmentResource::collection($assessments->items()),
                    'meta' => [
                        'current_page' => $assessments->currentPage(),
                        'last_page' => $assessments->lastPage(),
                        'per_page' => $assessments->perPage(),
                        'total' => $assessments->total(),
                    ],
        ]);
    }

    /**
     * POST /api/v1/assessments
     *
     * Creates a new assessment (Step 1: Facility & Assessor only).
     * Returns the assessment with its generated ID so the mobile app
     * can immediately start saving responses section by section.
     */
    public function store(StoreAssessmentRequest $request): JsonResponse {
        $user = $request->user();

        // Ensure no duplicate in-progress assessment for same facility + type
        $existing = Assessment::where('facility_id', $request->facility_id)
                ->where('assessment_type', $request->assessment_type)
                ->where('assessor_id', $user->id)
                ->where('status', 'in_progress')
                ->first();

        if ($existing) {
            return response()->json([
                        'message' => 'You already have an in-progress assessment for this facility and type.',
                        'assessment' => new AssessmentResource($existing->load(['facility.subcounty.county', 'sectionScores.section'])),
                            ], 409);
        }

        // Build initial section_progress from all active sections
        $sectionProgress = AssessmentSection::active()
                ->ordered()
                ->pluck('code')
                ->mapWithKeys(fn($code) => [$code => false])
                ->toArray();

        $assessment = Assessment::create([
            'facility_id' => $request->facility_id,
            'assessment_type' => $request->assessment_type,
            'assessment_date' => $request->assessment_date,
            'assessor_id' => $user->id,
            'assessor_name' => $user->name,
            'assessor_contact' => $user->email,
            'status' => 'in_progress',
            'section_progress' => $sectionProgress,
        ]);

        return response()->json([
                    'message' => 'Assessment created. Continue by submitting responses for each section.',
                    'assessment' => new AssessmentResource($assessment->load(['facility.subcounty.county', 'teamMembers'])),
                        ], 201);
    }

    /**
     * GET /api/v1/assessments/{assessment}
     */
    public function show(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $assessment->load([
            'facility.subcounty.county',
            'sectionScores.section',
            'questionResponses.question.section',
            'teamMembers',
        ]);

        return response()->json([
                    'data' => new AssessmentResource($assessment),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}
     *
     * Updates editable header fields (date, type).
     * Responses are updated via the /responses endpoint.
     */
    public function update(UpdateAssessmentRequest $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be modified.'], 403);
        }

        $assessment->update($request->validated());

        return response()->json([
                    'message' => 'Assessment updated.',
                    'assessment' => new AssessmentResource($assessment->fresh(['facility.subcounty.county', 'teamMembers'])),
        ]);
    }

    /**
     * DELETE /api/v1/assessments/{assessment}
     *
     * Soft-deletes a draft/in-progress assessment.
     */
    public function destroy(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('delete', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be deleted.'], 403);
        }

        $assessment->delete();

        return response()->json(['message' => 'Assessment deleted.'], 204);
    }

    /**
     * POST /api/v1/assessments/{assessment}/submit
     *
     * Finalises the assessment:
     * 1. Validates all required sections are complete
     * 2. Runs scoring across all sections
     * 3. Sets status → completed
     */
    public function submit(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Assessment already submitted.'], 409);
        }

        // Check all sections are marked done
        $progress = $assessment->section_progress ?? [];
        $incomplete = collect($progress)->filter(fn($done) => $done === false)->keys()->values();

        if ($incomplete->isNotEmpty()) {
            return response()->json([
                        'message' => 'Cannot submit. Some sections are incomplete.',
                        'incomplete_sections' => $incomplete,
                            ], 422);
        }

        DB::transaction(function () use ($assessment) {
            // Run full scoring
            $this->scoringService->recalculateAllSections($assessment->id);

            // Reload to get fresh scores
            $assessment->refresh();

            $assessment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => request()->user()->id,
            ]);
        });

        return response()->json([
                    'message' => 'Assessment submitted successfully.',
                    'assessment' => new AssessmentResource(
                            $assessment->fresh(['facility.subcounty.county', 'sectionScores.section', 'teamMembers'])
                    ),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}/sections/{sectionCode}/progress
     *
     * Marks a section as done/undone.
     * Called by the mobile app after saving a section's responses.
     */
    public function updateSectionProgress(Request $request, Assessment $assessment, string $sectionCode): JsonResponse {
        $this->authorize('update', $assessment);

        $request->validate(['done' => 'required|boolean']);

        $progress = $assessment->section_progress ?? [];
        $progress[$sectionCode] = $request->boolean('done');

        $assessment->update(['section_progress' => $progress]);

        return response()->json([
                    'message' => 'Section progress updated.',
                    'section_progress' => $progress,
        ]);
    }
}
