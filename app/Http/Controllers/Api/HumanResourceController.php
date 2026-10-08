<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GuardsClosedAssessment;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\HumanResourceResponse;
use App\Models\MainCadre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Human-resources section. Mirrors the web EditHumanResources page: cadres
 * come from the assessment's OWN template, the assessor can exclude cadres
 * that don't apply ("Manage Cadres"), and per-cadre N/A training columns are
 * honoured.
 */
class HumanResourceController extends Controller {

    use GuardsClosedAssessment;

    /**
     * Cadres available on this assessment's template. Legacy rows with no
     * template keep the previous behaviour (every active cadre).
     */
    private function templateCadres(Assessment $assessment) {
        return MainCadre::where('is_active', true)
            ->when($assessment->assessment_type_id, fn ($q, $typeId) => $q->where('assessment_type_id', $typeId))
            ->orderBy('order')
            ->get();
    }

    /**
     * "Others" is excluded by default until the assessor customises the
     * selection (excluded_cadre_ids stays null until then) — same as web.
     */
    private function excludedIds(Assessment $assessment, $cadres): array {
        return $assessment->excluded_cadre_ids
            ?? $cadres->where('name', 'Others')->pluck('id')->all();
    }

    /**
     * GET /api/v1/assessments/{assessment}/human-resources
     *
     * Returns the visible cadres with saved values, plus every cadre on the
     * template with an `included` flag so the app can offer "Manage Cadres".
     */
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $cadres = $this->templateCadres($assessment);
        $excluded = $this->excludedIds($assessment, $cadres);

        $responses = HumanResourceResponse::where('assessment_id', $assessment->id)
                ->get()
                ->keyBy('cadre_id');

        $data = $cadres->reject(fn ($c) => in_array($c->id, $excluded, true))->map(function (MainCadre $cadre) use ($responses) {
            $r = $responses->get($cadre->id);
            $na = fn (string $col) => $cadre->isColumnNotApplicable($col);

            return [
                'cadre_id' => $cadre->id,
                'cadre_name' => $cadre->name,
                'total_in_facility' => $na('total_in_facility') ? null : ($r?->total_in_facility ?? 0),
                'etat_plus' => $na('etat_plus') ? null : ($r?->etat_plus ?? 0),
                'comprehensive_newborn_care' => $na('comprehensive_newborn_care') ? null : ($r?->comprehensive_newborn_care ?? 0),
                'imnci' => $na('imnci') ? null : ($r?->imnci ?? 0),
                'type_1_diabetes' => $na('type_1_diabetes') ? null : ($r?->type_1_diabetes ?? 0),
                'essential_newborn_care' => $na('essential_newborn_care') ? null : ($r?->essential_newborn_care ?? 0),
                'hides_total_in_facility' => $cadre->hidesTotalInFacility(),
                'na_training_columns' => $cadre->na_training_columns ?? [],
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'cadres' => $cadres->map(fn (MainCadre $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'included' => ! in_array($c->id, $excluded, true),
            ])->values(),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}/human-resources/cadres
     *
     * "Manage Cadres": which cadres are present at this facility. Data already
     * entered for an excluded cadre is preserved (only hidden).
     *
     * Body: { "included_cadre_ids": [1, 2, 3] }
     */
    public function manageCadres(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($closed = $this->rejectIfClosed($assessment)) {
            return $closed;
        }

        $request->validate([
            'included_cadre_ids' => 'present|array',
            'included_cadre_ids.*' => 'integer',
        ]);

        $allIds = $this->templateCadres($assessment)->pluck('id')->all();
        $included = array_map('intval', $request->input('included_cadre_ids', []));

        // Stored as an explicit array even when empty: null means "never customised".
        $assessment->update(['excluded_cadre_ids' => array_values(array_diff($allIds, $included))]);

        return $this->index($request, $assessment->fresh());
    }

    /**
     * POST /api/v1/assessments/{assessment}/human-resources
     *
     * Bulk-save HR responses.
     *
     * Request body:
     * {
     *   "responses": [
     *     { "cadre_id": 1, "total_in_facility": 5, "etat_plus": 3, "comprehensive_newborn_care": 2,
     *       "imnci": 1, "type_1_diabetes": 0, "essential_newborn_care": 4 }, ...
     *   ]
     * }
     */
    public function store(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($closed = $this->rejectIfClosed($assessment)) {
            return $closed;
        }

        $request->validate([
            'responses' => 'required|array',
            'responses.*.cadre_id' => 'required|integer|exists:assessment_cadres,id',
            'responses.*.total_in_facility' => 'nullable|integer|min:0',
            'responses.*.etat_plus' => 'nullable|integer|min:0',
            'responses.*.comprehensive_newborn_care' => 'nullable|integer|min:0',
            'responses.*.imnci' => 'nullable|integer|min:0',
            'responses.*.type_1_diabetes' => 'nullable|integer|min:0',
            'responses.*.essential_newborn_care' => 'nullable|integer|min:0',
        ]);

        // Cadres must belong to this assessment's template.
        $cadres = $this->templateCadres($assessment)->keyBy('id');
        $foreign = collect($request->responses)->pluck('cadre_id')->unique()->reject(fn ($id) => $cadres->has((int) $id))->values();

        if ($foreign->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more cadres do not belong to this assessment\'s template.',
                'errors' => ['responses' => $foreign->map(fn ($id) => "Cadre #{$id} is not part of this template")->all()],
            ], 422);
        }

        // Validate: sum of training fields must not exceed total_in_facility per cadre
        $trainingFields = MainCadre::TRAINING_COLUMNS;
        $violations = [];

        foreach ($request->responses as $entry) {
            $cadre = $cadres->get((int) $entry['cadre_id']);

            // Rows like ToTs report training counts without a staff total.
            if ($cadre->hidesTotalInFacility()) {
                continue;
            }

            $total = (int) ($entry['total_in_facility'] ?? 0);
            if ($total === 0) continue;

            $trainedSum = array_sum(array_map(fn ($f) => (int) ($entry[$f] ?? 0), $trainingFields));

            if ($trainedSum > $total) {
                $violations[] = "{$cadre->name}: trained {$trainedSum} exceeds total {$total}";
            }
        }

        if (!empty($violations)) {
            return response()->json([
                'message' => 'Trained count exceeds total staff in facility for one or more cadres.',
                'errors' => ['responses' => $violations],
            ], 422);
        }

        foreach ($request->responses as $entry) {
            $cadre = $cadres->get((int) $entry['cadre_id']);
            $value = fn (string $col) => $cadre->isColumnNotApplicable($col) ? null : (int) ($entry[$col] ?? 0);

            HumanResourceResponse::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'cadre_id' => $cadre->id,
                    ],
                    [
                        'total_in_facility' => $value('total_in_facility'),
                        'etat_plus' => $value('etat_plus'),
                        'comprehensive_newborn_care' => $value('comprehensive_newborn_care'),
                        'imnci' => $value('imnci'),
                        'type_1_diabetes' => $value('type_1_diabetes'),
                        'essential_newborn_care' => $value('essential_newborn_care'),
                    ]
            );
        }

        // Mark the template's own human-resources section done (its code isn't
        // always the literal 'human_resources').
        $section = $assessment->templateSections()->where('is_active', true)->get()
            ->first(fn (AssessmentSection $s) => $s->resolvedKind() === 'human_resources');

        $progress = $assessment->section_progress ?? [];
        $progress[$section?->code ?? 'human_resources'] = true;
        $assessment->section_progress = $progress;
        $assessment->save();

        return response()->json(['message' => 'Human resources responses saved.']);
    }
}
