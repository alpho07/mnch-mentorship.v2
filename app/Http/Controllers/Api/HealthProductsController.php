<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GuardsClosedAssessment;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentCommodityResponse;
use App\Models\AssessmentDepartment;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Services\CommodityMatrixProgressService;
use App\Services\CommodityScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Health-products (commodity matrix) section. Everything is scoped to the
 * assessment's OWN template — departments, categories and commodities are
 * template-specific — and visibility follows the same conditional rules the
 * web EditHealthProducts page uses (via CommodityMatrixProgressService).
 */
class HealthProductsController extends Controller {

    use GuardsClosedAssessment;

    public function __construct(
            private readonly CommodityScoringService $scoringService,
            private readonly CommodityMatrixProgressService $matrixProgress
    ) {

    }

    // =========================================================================
    // GET /api/v1/assessments/{assessment}/health-products
    //
    // Departments / categories / commodities that apply to THIS assessment,
    // merged with its saved answers. Answers are never cached.
    //
    // Per commodity: available (bool|null), not_applicable (bool), quantity (int|null)
    // =========================================================================
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        $departments = $this->matrixProgress->visibleDepartments($assessment);
        $expected = $this->matrixProgress->expectedCommodityIdsByDepartment($assessment);

        $categories = CommodityCategory::where('assessment_type_id', $assessment->assessment_type_id)
                ->orderBy('order')
                ->get(['id', 'name', 'order']);

        $commodityIds = collect($expected)->flatten()->unique()->values();
        $commodities = Commodity::whereIn('id', $commodityIds)
                ->where('is_active', true)
                ->orderBy('order')
                ->get(['id', 'name', 'description', 'order', 'commodity_category_id'])
                ->groupBy('commodity_category_id');

        $saved = AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->get()
                ->keyBy(fn ($r) => "{$r->assessment_department_id}_{$r->commodity_id}");

        $data = $departments->map(function (AssessmentDepartment $dept) use ($categories, $commodities, $expected, $saved) {
            $allowed = array_flip($expected[$dept->id] ?? []);
            $deptCategories = [];

            foreach ($categories as $cat) {
                $rows = ($commodities->get($cat->id) ?? collect())
                        ->filter(fn ($c) => isset($allowed[$c->id]))
                        ->map(function ($c) use ($dept, $saved) {
                            $r = $saved->get("{$dept->id}_{$c->id}");

                            return [
                                'commodity_id' => $c->id,
                                'name' => $c->name,
                                'description' => $c->description,
                                'available' => $r === null ? null : (bool) $r->available,
                                'not_applicable' => $r !== null && (bool) $r->not_applicable,
                                'quantity' => $r?->quantity,
                            ];
                        })->values();

                if ($rows->isEmpty()) {
                    continue;
                }

                $deptCategories[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'commodities' => $rows->all(),
                ];
            }

            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'slug' => $dept->slug,
                'categories' => $deptCategories,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    // =========================================================================
    // POST /api/v1/assessments/{assessment}/health-products
    //
    // Accepts either:
    //   - All departments at once
    //   - A single department via optional "department_id" (Save & Next flow)
    //
    // Each entry: { department_id, commodity_id, available: bool }
    //          or { department_id, commodity_id, not_applicable: true }
    //   optional quantity (only kept when available) — same as the web form.
    //
    // The section is marked complete only once every visible department has
    // every visible applicable commodity answered.
    // =========================================================================
    public function store(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($closed = $this->rejectIfClosed($assessment)) {
            return $closed;
        }

        $request->validate([
            'department_id' => 'nullable|integer|exists:assessment_departments,id',
            'responses' => 'required|array',
            'responses.*.department_id' => 'required|integer|exists:assessment_departments,id',
            'responses.*.commodity_id' => 'required|integer|exists:commodities,id',
            'responses.*.available' => 'nullable|boolean',
            'responses.*.not_applicable' => 'nullable|boolean',
            'responses.*.quantity' => 'nullable|integer|min:0',
        ]);

        $entries = collect($request->responses);

        // Every entry must say either "available" or "not applicable".
        $undecided = $entries->filter(fn ($e) => ! array_key_exists('available', $e) && empty($e['not_applicable']));
        if ($undecided->isNotEmpty()) {
            return response()->json([
                'message' => 'Each response needs "available" or "not_applicable".',
                'errors' => ['responses' => ['Each response needs "available" or "not_applicable".']],
            ], 422);
        }

        // Departments and commodities must belong to this assessment's template.
        $templateId = $assessment->assessment_type_id;
        $deptIds = AssessmentDepartment::where('assessment_type_id', $templateId)->pluck('id')->all();
        $categoryIds = CommodityCategory::where('assessment_type_id', $templateId)->pluck('id');
        $commodityIds = Commodity::whereIn('commodity_category_id', $categoryIds)->pluck('id')->all();

        $foreign = $entries->filter(fn ($e) => ! in_array((int) $e['department_id'], $deptIds, true)
                || ! in_array((int) $e['commodity_id'], $commodityIds, true));

        if ($foreign->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more departments or commodities do not belong to this assessment\'s template.',
                'errors' => ['responses' => ['Department or commodity is not part of this template.']],
            ], 422);
        }

        $now = now();
        $rows = $entries->map(function ($e) use ($assessment, $now) {
            $isNa = ! empty($e['not_applicable']);
            $available = $isNa ? false : (bool) $e['available'];
            $quantity = $available ? ($e['quantity'] ?? null) : null;

            return [
                'assessment_id' => $assessment->id,
                'assessment_department_id' => (int) $e['department_id'],
                'commodity_id' => (int) $e['commodity_id'],
                'available' => $available,
                'not_applicable' => $isNa,
                'quantity' => $quantity !== null && $quantity !== '' ? (int) $quantity : null,
                'score' => ($isNa || ! $available) ? 0 : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        AssessmentCommodityResponse::upsert(
            $rows,
            ['assessment_id', 'commodity_id', 'assessment_department_id'],
            ['available', 'not_applicable', 'quantity', 'score', 'updated_at']
        );

        foreach ($entries->pluck('department_id')->unique() as $departmentId) {
            $this->scoringService->recalculateDepartmentScore($assessment->id, (int) $departmentId);
        }

        // Section completion is derived from the responses now on record,
        // for every commodity_matrix section on this assessment's template.
        $this->matrixProgress->forget($assessment);
        $this->matrixProgress->sync($assessment);

        Cache::forget("assessment.{$assessment->id}.report");

        return response()->json(['message' => 'Health products responses saved.']);
    }
}
