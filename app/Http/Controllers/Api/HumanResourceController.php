<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\MainCadre;
use App\Models\HumanResourceResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HumanResourceController extends Controller { 

    /**
     * GET /api/v1/assessments/{assessment}/human-resources
     *
     * Returns saved HR responses keyed by cadre_id, plus the cadre schema.
     */
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $cadres = Cache::remember('api.cadres.list', now()->addHours(6), function () {
            return MainCadre::where('is_active', true)
                            ->orderBy('order')
                            ->get(['id', 'name', 'order'])
                            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'order' => $c->order])
                            ->values();
        });

        $responses = HumanResourceResponse::where('assessment_id', $assessment->id)
                ->get()
                ->keyBy('cadre_id');

        $data = $cadres->map(function ($cadre) use ($responses) {
            $r = $responses->get($cadre['id']);
            return [
                'cadre_id' => $cadre['id'],
                'cadre_name' => $cadre['name'],
                'total_in_facility' => $r?->total_in_facility ?? 0,
                'etat_plus' => $r?->etat_plus ?? 0,
                'comprehensive_newborn_care' => $r?->comprehensive_newborn_care ?? 0,
                'imnci' => $r?->imnci ?? 0,
                'type_1_diabetes' => $r?->type_1_diabetes ?? 0,
                'essential_newborn_care' => $r?->essential_newborn_care ?? 0,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/assessments/{assessment}/human-resources
     *
     * Bulk-save HR responses for all cadres.
     *
     * Request body:
     * {
     *   "responses": [
     *     {
     *       "cadre_id": 1,
     *       "etat_plus": 3,
     *       "comprehensive_newborn_care": 2,
     *       "imnci": 1,
     *       "type_1_diabetes": 0,
     *       "essential_newborn_care": 4
     *     }, ...
     *   ]
     * }
     */
    public function store(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be modified.'], 403);
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

        // Validate: sum of training fields must not exceed total_in_facility per cadre
        $trainingFields = ['etat_plus', 'comprehensive_newborn_care', 'imnci', 'type_1_diabetes', 'essential_newborn_care'];
        $violations = [];

        foreach ($request->responses as $entry) {
            $total = (int) ($entry['total_in_facility'] ?? 0);
            if ($total === 0) continue;

            $trainedSum = array_sum(array_map(fn ($f) => (int) ($entry[$f] ?? 0), $trainingFields));

            if ($trainedSum > $total) {
                $cadre = MainCadre::find($entry['cadre_id']);
                $name = $cadre?->name ?? "Cadre #{$entry['cadre_id']}";
                $violations[] = "{$name}: trained {$trainedSum} exceeds total {$total}";
            }
        }

        if (!empty($violations)) {
            return response()->json([
                'message' => 'Trained count exceeds total staff in facility for one or more cadres.',
                'errors' => ['responses' => $violations],
            ], 422);
        }

        foreach ($request->responses as $entry) {
            HumanResourceResponse::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'cadre_id' => $entry['cadre_id'],
                    ],
                    [
                        'total_in_facility' => (int) ($entry['total_in_facility'] ?? 0),
                        'etat_plus' => (int) ($entry['etat_plus'] ?? 0),
                        'comprehensive_newborn_care' => (int) ($entry['comprehensive_newborn_care'] ?? 0),
                        'imnci' => (int) ($entry['imnci'] ?? 0),
                        'type_1_diabetes' => (int) ($entry['type_1_diabetes'] ?? 0),
                        'essential_newborn_care' => (int) ($entry['essential_newborn_care'] ?? 0),
                    ]
            );
        }

        // Mark section progress
        $progress = $assessment->section_progress ?? [];
        $progress['human_resources'] = true;
        $assessment->section_progress = $progress;
        $assessment->save();

        return response()->json(['message' => 'Human resources responses saved.']);
    }
} 
