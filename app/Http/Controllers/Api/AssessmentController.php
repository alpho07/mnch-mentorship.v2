<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAssessmentRequest;
use App\Http\Requests\Api\UpdateAssessmentRequest;
use App\Http\Resources\Api\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use App\Services\AssessmentSchemaService;
use App\Services\AssessmentTeamService;
use App\Services\CommodityMatrixProgressService;
use App\Http\Controllers\Api\Concerns\GuardsClosedAssessment;
use App\Services\DynamicScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller {

    use GuardsClosedAssessment;

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
                    'assessmentType',
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

        if ($request->filled('template_id')) {
            $query->where('assessment_type_id', $request->template_id);
        }

        if ($request->filled('round')) {
            $query->where('round', $request->round);
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
     * Creates an assessment on a chosen template + round, mirroring the web
     * create form. Returns the assessment so the app can start saving
     * responses section by section.
     *
     * Legacy clients (no assessment_type_id) get the default template and
     * their `assessment_type` value is used as the round.
     */
    public function store(StoreAssessmentRequest $request, AssessmentSchemaService $schemas, AssessmentTeamService $teamService): JsonResponse {
        $user = $request->user();

        $template = $request->filled('assessment_type_id')
            ? AssessmentType::active()->find($request->assessment_type_id)
            : $schemas->defaultTemplate();

        if (! $template) {
            return response()->json([
                'message' => 'The selected assessment template is not available.',
                'errors' => ['assessment_type_id' => ['The selected assessment template is not available.']],
            ], 422);
        }

        $round = $request->input('round') ?? $request->input('assessment_type') ?? 'baseline';
        $roundLabel = $round === 'other' ? $request->input('round_label') : null;

        // One assessment per facility, per template, per round ("other"
        // rounds are told apart by label) — same rule as the web form.
        $existing = $this->findDuplicate($request->facility_id, $template->id, $round, $roundLabel);

        if ($existing) {
            $payload = ['message' => 'An assessment for this facility, template and round already exists.'];

            if ($this->canSee($user, $existing)) {
                $payload['assessment'] = new AssessmentResource(
                    $existing->load(['facility.subcounty.county', 'sectionScores.section', 'teamMembers', 'assessmentType'])
                );
            }

            return response()->json($payload, 409);
        }

        $assessment = Assessment::create([
            'facility_id' => $request->facility_id,
            'assessment_type_id' => $template->id,
            // Legacy enum column only knows baseline/midline/endline.
            'assessment_type' => in_array($round, ['baseline', 'midline', 'endline'], true) ? $round : 'baseline',
            'round' => $round,
            'round_label' => $roundLabel,
            'assessment_date' => $request->assessment_date,
            'assessor_id' => $user->id,
            'assessor_name' => $user->name,
            'assessor_contact' => $user->email,
            'created_by' => $user->id,
            'status' => 'in_progress',
            'section_progress' => $this->initialProgress($template),
        ]);

        if ($request->filled('member_ids')) {
            $teamService->addMembers($assessment, $request->input('member_ids'), $user->id);
        }

        return response()->json([
                    'message' => 'Assessment created. Continue by submitting responses for each section.',
                    'assessment' => new AssessmentResource($assessment->load(['facility.subcounty.county', 'teamMembers', 'assessmentType'])),
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
            'assessmentType',
        ]);

        return response()->json([
                    'data' => new AssessmentResource($assessment),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}
     *
     * Updates header fields (date, round). The template can only be changed
     * while the assessment has no answers yet — otherwise saved responses
     * would no longer match the template's questions.
     */
    public function update(UpdateAssessmentRequest $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($closed = $this->rejectIfClosed($assessment)) {
            return $closed;
        }

        $data = $request->validated();

        if (isset($data['assessment_type_id']) && (int) $data['assessment_type_id'] !== (int) $assessment->assessment_type_id) {
            if ($this->hasAnswers($assessment)) {
                return response()->json([
                    'message' => 'The template cannot be changed once responses have been saved.',
                    'errors' => ['assessment_type_id' => ['The template cannot be changed once responses have been saved.']],
                ], 422);
            }

            $template = AssessmentType::active()->find($data['assessment_type_id']);

            if (! $template) {
                return response()->json(['message' => 'The selected assessment template is not available.'], 422);
            }

            $data['section_progress'] = $this->initialProgress($template);
        }

        // Legacy `assessment_type` doubles as the round for older clients.
        if (! isset($data['round']) && isset($data['assessment_type'])) {
            $data['round'] = $data['assessment_type'];
        }

        if (isset($data['round'])) {
            $data['assessment_type'] = in_array($data['round'], ['baseline', 'midline', 'endline'], true) ? $data['round'] : 'baseline';
            $data['round_label'] = $data['round'] === 'other' ? ($data['round_label'] ?? $assessment->round_label) : null;
        }

        $templateId = (int) ($data['assessment_type_id'] ?? $assessment->assessment_type_id);
        $round = $data['round'] ?? $assessment->round;
        $label = $round === 'other' ? ($data['round_label'] ?? $assessment->round_label) : null;

        if ($round && $this->findDuplicate($assessment->facility_id, $templateId, $round, $label, $assessment->id)) {
            return response()->json([
                'message' => 'An assessment for this facility, template and round already exists.',
            ], 409);
        }

        $assessment->update($data);

        return response()->json([
                    'message' => 'Assessment updated.',
                    'assessment' => new AssessmentResource($assessment->fresh(['facility.subcounty.county', 'teamMembers', 'assessmentType'])),
        ]);
    }

    /**
     * DELETE /api/v1/assessments/{assessment}
     *
     * Soft-deletes an open (draft/in-progress, unlocked) assessment.
     */
    public function destroy(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('delete', $assessment);

        if ($assessment->status === 'completed' || $assessment->is_locked) {
            return response()->json(['message' => 'Completed assessments cannot be deleted.'], 403);
        }

        $assessment->delete();

        return response()->json(['message' => 'Assessment deleted.'], 204);
    }

    /**
     * POST /api/v1/assessments/{assessment}/submit
     *
     * Closes the assessment — same as the web "Mark as Complete":
     * 1. Every real section of the assessment's own template must be done
     * 2. Scores the template's sections
     * 3. Sets status → completed AND locks it (read-only until reopened)
     */
    public function submit(Request $request, Assessment $assessment, CommodityMatrixProgressService $matrixProgress): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed' || $assessment->is_locked) {
            return response()->json(['message' => 'Assessment already submitted.'], 409);
        }

        // Refresh derived (commodity matrix) flags before the gate reads
        // them — same as the web dashboard does.
        $matrixProgress->sync($assessment);
        $assessment->refresh();

        if (! $assessment->allSectionsComplete()) {
            return response()->json([
                        'message' => 'Cannot submit. Some sections are incomplete.',
                        'incomplete_sections' => $this->incompleteSections($assessment),
                            ], 422);
        }

        DB::transaction(function () use ($assessment, $request) {
            // Score only this assessment's own template
            $this->scoringService->recalculateAllSections($assessment->id);

            $assessment->refresh();

            $assessment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
            ]);
            $assessment->lock($request->user()->id);
        });

        return response()->json([
                    'message' => 'Assessment submitted successfully.',
                    'assessment' => new AssessmentResource(
                            $assessment->fresh(['facility.subcounty.county', 'sectionScores.section', 'teamMembers', 'assessmentType'])
                    ),
        ]);
    }

    /**
     * POST /api/v1/assessments/{assessment}/reopen
     *
     * Reopens a closed assessment for editing. Allowed for the assessment's
     * team lead and for administrators (Assessment::canToggleLock).
     */
    public function reopen(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        if (! $assessment->canToggleLock($request->user()->id)) {
            return response()->json(['message' => 'Only the team lead or an administrator can reopen this assessment.'], 403);
        }

        if ($assessment->status !== 'completed' && ! $assessment->is_locked) {
            return response()->json(['message' => 'This assessment is not closed.'], 409);
        }

        $assessment->update(['status' => 'in_progress']);
        $assessment->unlock();

        return response()->json([
                    'message' => 'Assessment reopened.',
                    'assessment' => new AssessmentResource(
                            $assessment->fresh(['facility.subcounty.county', 'sectionScores.section', 'teamMembers', 'assessmentType'])
                    ),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}/sections/{sectionCode}/progress
     *
     * Marks a section as done/undone. The code is resolved against the
     * assessment's own template (codes are only unique per template).
     */
    public function updateSectionProgress(Request $request, Assessment $assessment, string $sectionCode): JsonResponse {
        $this->authorize('update', $assessment);

        if ($closed = $this->rejectIfClosed($assessment)) {
            return $closed;
        }

        $request->validate(['done' => 'required|boolean']);

        $section = $assessment->templateSections()->where('code', $sectionCode)->first();

        if (! $section) {
            return response()->json(['message' => "Section '{$sectionCode}' does not belong to this assessment's template."], 422);
        }

        $progress = $assessment->section_progress ?? [];
        $progress[$sectionCode] = $request->boolean('done');

        $assessment->update(['section_progress' => $progress]);

        return response()->json([
                    'message' => 'Section progress updated.',
                    'section_progress' => $progress,
        ]);
    }

    // ------------------------------------------------------------------

    /** Progress map for a template: its real (non-informational) active sections, all pending. */
    private function initialProgress(AssessmentType $template): array {
        return $template->sections()->active()->ordered()->get()
            ->filter(fn (AssessmentSection $s) => $s->resolvedKind() !== 'informational')
            ->mapWithKeys(fn (AssessmentSection $s) => [$s->code => false])
            ->all();
    }

    private function findDuplicate(int $facilityId, int $templateId, string $round, ?string $label, ?int $exceptId = null): ?Assessment {
        return Assessment::where('facility_id', $facilityId)
            ->where('assessment_type_id', $templateId)
            ->where('round', $round)
            ->when($round === 'other', fn ($q) => $q->where('round_label', $label))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->first();
    }

    private function canSee($user, Assessment $assessment): bool {
        return $user->hasRole(['admin', 'super_admin'])
            || $user->isAboveSite()
            || $assessment->assessor_id === $user->id
            || $assessment->created_by === $user->id
            || $assessment->isTeamMember($user->id);
    }

    private function hasAnswers(Assessment $assessment): bool {
        return $assessment->questionResponses()->exists()
            || $assessment->humanResourceResponses()->exists()
            || $assessment->commodityResponses()->exists();
    }

    /** Real sections of the template that are not yet marked done. */
    private function incompleteSections(Assessment $assessment): array {
        $progress = $assessment->section_progress ?? [];

        return $assessment->templateSections()->active()->ordered()->get()
            ->filter(fn (AssessmentSection $s) => $s->resolvedKind() !== 'informational')
            ->reject(fn (AssessmentSection $s) => ($progress[$s->code] ?? false) === true)
            ->pluck('code')
            ->values()
            ->all();
    }
}
