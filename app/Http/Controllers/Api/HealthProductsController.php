<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\AssessmentCommodityResponse;
use App\Services\CommodityScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HealthProductsController extends Controller {

    public function __construct(
            private readonly CommodityScoringService $scoringService
    ) {
        
    }

    // =========================================================================
    // GET /api/v1/assessments/{assessment}/health-products
    //
    // Schema (departments/categories/commodities) is cached globally for 6h.
    // Responses are fetched fresh per-assessment and merged in — never cached.
    // =========================================================================
    public function index(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('view', $assessment);

        // Cache ONLY the schema — always stored as plain PHP arrays, never Collections
        $schema = Cache::remember('api.health_products.schema', now()->addHours(6), function () {
            $result = [];

            $departments = AssessmentDepartment::where('is_active', true)
                    ->orderBy('order')
                    ->get(['id', 'name', 'order']);

            $allCategories = CommodityCategory::orderBy('order')
                    ->get(['id', 'name', 'order']);

            foreach ($departments as $dept) {
                $deptCategories = [];

                foreach ($allCategories as $cat) {
                    $commodities = Commodity::where('commodity_category_id', $cat->id)
                            ->where('is_active', true)
                            ->whereHas('applicableDepartments', function ($q) use ($dept) {
                                $q->where('assessment_department_id', $dept->id);
                            })
                            ->orderBy('order')
                            ->get(['id', 'name', 'description', 'order']);

                    if ($commodities->isEmpty()) {
                        continue;
                    }

                    $commodityList = [];
                    foreach ($commodities as $c) {
                        $commodityList[] = [
                            'commodity_id' => $c->id,
                            'name' => $c->name,
                            'description' => $c->description,
                            'available' => null, // merged per-assessment below
                        ];
                    }

                    $deptCategories[] = [
                        'category_id' => $cat->id,
                        'category_name' => $cat->name,
                        'commodities' => $commodityList,
                    ];
                }

                $result[] = [
                    'department_id' => $dept->id,
                    'department_name' => $dept->name,
                    'categories' => $deptCategories,
                ];
            }

            return $result;
        });

        // Fetch this assessment's responses fresh (never cached)
        $saved = AssessmentCommodityResponse::where('assessment_id', $assessment->id)
                ->get()
                ->keyBy(fn($r) => "{$r->assessment_department_id}_{$r->commodity_id}");

        // Merge availability into the plain-array schema
        $merged = [];
        foreach ($schema as $dept) {
            $deptId = $dept['department_id'];
            $categories = [];

            foreach ($dept['categories'] as $cat) {
                $commodities = [];
                foreach ($cat['commodities'] as $c) {
                    $key = "{$deptId}_{$c['commodity_id']}";
                    $response = $saved->get($key);
                    $c['available'] = $response !== null ? (bool) $response->available : null;
                    $commodities[] = $c;
                }
                $cat['commodities'] = $commodities;
                $categories[] = $cat;
            }

            $dept['categories'] = $categories;
            $merged[] = $dept;
        }

        return response()->json(['data' => $merged]);
    }

    // =========================================================================
    // POST /api/v1/assessments/{assessment}/health-products
    //
    // Accepts either:
    //   - All departments at once (marks section complete)
    //   - A single department via optional "department_id" (Save & Next flow)
    //
    // Body: { "department_id": 1 (optional), "responses": [...] }
    // =========================================================================
    public function store(Request $request, Assessment $assessment): JsonResponse {
        $this->authorize('update', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json(['message' => 'Completed assessments cannot be modified.'], 403);
        }

        $request->validate([
            'department_id' => 'nullable|integer|exists:assessment_departments,id',
            'responses' => 'required|array',
            'responses.*.department_id' => 'required|integer|exists:assessment_departments,id',
            'responses.*.commodity_id' => 'required|integer|exists:commodities,id',
            'responses.*.available' => 'required|boolean',
        ]);

        $byDepartment = collect($request->responses)->groupBy('department_id');

        foreach ($byDepartment as $departmentId => $entries) {
            foreach ($entries as $entry) {
                $available = (bool) $entry['available'];
                AssessmentCommodityResponse::updateOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'assessment_department_id' => $departmentId,
                            'commodity_id' => $entry['commodity_id'],
                        ],
                        [
                            'available' => $available,
                            'score' => $available ? 1 : 0,
                        ]
                );
            }
            $this->scoringService->recalculateDepartmentScore($assessment->id, $departmentId);
        }

        // Only mark section complete when saving all departments at once
        // (per-dept Save & Next sends department_id, so we skip this)
        if (!$request->filled('department_id')) {
            $progress = $assessment->section_progress ?? [];
            $progress['health_products'] = true;
            $assessment->section_progress = $progress;
            $assessment->save();
        }

        Cache::forget("assessment.{$assessment->id}.report");

        return response()->json(['message' => 'Health products responses saved.']);
    }
}
