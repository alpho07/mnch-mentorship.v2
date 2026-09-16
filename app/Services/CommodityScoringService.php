<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentDepartment;
use App\Models\AssessmentDepartmentScore;
use App\Models\AssessmentCommodityResponse;
use App\Models\CommodityCategory;
use App\Models\Commodity;
use Illuminate\Support\Facades\DB;

class CommodityScoringService {

    /**
     * Recalculate score for a specific department
     */
    public function recalculateDepartmentScore(int $assessmentId, int $departmentId): void {
        $department = AssessmentDepartment::find($departmentId);

        if (!$department) {
            return;
        }

        // Get all categories
        $categories = CommodityCategory::orderBy('order')->get();

        foreach ($categories as $category) {
            $this->recalculateDepartmentCategoryScore($assessmentId, $departmentId, $category->id);
        }

        // Update overall assessment score
        app(DynamicScoringService::class)->recalculateOverallScore($assessmentId);
    }

    /**
     * Recalculate score for a department × category combination
     */
    public function recalculateDepartmentCategoryScore(int $assessmentId, int $departmentId, int $categoryId): void {
        // Get all applicable commodities for this department and category
        $applicableCommodities = Commodity::where('commodity_category_id', $categoryId)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($query) use ($departmentId) {
                    $query->where('assessment_department_id', $departmentId);
                })
                ->pluck('id');

        if ($applicableCommodities->isEmpty()) {
            return;
        }

        // Get responses for these commodities
        $responses = AssessmentCommodityResponse::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->whereIn('commodity_id', $applicableCommodities)
                ->get();

        // Calculate counts
        $totalApplicable = $applicableCommodities->count();
        $availableCount = $responses->where('available', true)->count();

        // Calculate percentage
        $percentage = $totalApplicable > 0 ? ($availableCount / $totalApplicable) * 100 : 0;

        // Determine grade
        $grade = match (true) {
            $percentage >= 80 => 'green',
            $percentage >= 50 => 'yellow',
            default => 'red',
        };

        // Update or create department score
        AssessmentDepartmentScore::updateOrCreate(
                [
                    'assessment_id' => $assessmentId,
                    'assessment_department_id' => $departmentId,
                    'commodity_category_id' => $categoryId,
                ],
                [
                    'available_count' => $availableCount,
                    'total_applicable' => $totalApplicable,
                    'percentage' => round($percentage, 2),
                    'grade' => $percentage > 0 ? $grade : null,
                ]
        );
    }

    /**
     * Get department summary
     */
    public function getDepartmentSummary(int $assessmentId, int $departmentId): array {
        $department = AssessmentDepartment::find($departmentId);

        if (!$department) {
            return [];
        }

        // Get all category scores for this department
        $categoryScores = AssessmentDepartmentScore::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->with('category')
                ->get();

        // Calculate overall department score
        $totalAvailable = $categoryScores->sum('available_count');
        $totalApplicable = $categoryScores->sum('total_applicable');
        $overallPercentage = $totalApplicable > 0 ? ($totalAvailable / $totalApplicable) * 100 : 0;

        $overallGrade = match (true) {
            $overallPercentage >= 80 => 'green',
            $overallPercentage >= 50 => 'yellow',
            default => 'red',
        };

        return [
            'department' => $department,
            'categories' => $categoryScores,
            'overall' => [
                'available_count' => $totalAvailable,
                'total_applicable' => $totalApplicable,
                'percentage' => round($overallPercentage, 2),
                'grade' => $overallPercentage > 0 ? $overallGrade : null,
            ],
        ];
    }

    /**
     * Get category summary for department
     */
    public function getCategorySummary(int $assessmentId, int $departmentId, int $categoryId): array {
        $category = CommodityCategory::find($categoryId);
        $department = AssessmentDepartment::find($departmentId);

        if (!$category || !$department) {
            return [];
        }

        // Get all applicable commodities
        $commodities = Commodity::where('commodity_category_id', $categoryId)
                ->where('is_active', true)
                ->whereHas('applicableDepartments', function ($query) use ($departmentId) {
                    $query->where('assessment_department_id', $departmentId);
                })
                ->orderBy('order')
                ->get();

        // Get responses
        $responses = AssessmentCommodityResponse::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->whereIn('commodity_id', $commodities->pluck('id'))
                ->get()
                ->keyBy('commodity_id');

        // Build commodity list with responses
        $commodityList = $commodities->map(function ($commodity) use ($responses) {
            $response = $responses->get($commodity->id);

            return [
                'commodity' => $commodity,
                'response' => $response,
                'available' => $response ? $response->available : false,
                'notes' => $response ? $response->notes : null,
            ];
        });

        // Get score
        $score = AssessmentDepartmentScore::where('assessment_id', $assessmentId)
                ->where('assessment_department_id', $departmentId)
                ->where('commodity_category_id', $categoryId)
                ->first();

        return [
            'category' => $category,
            'department' => $department,
            'commodities' => $commodityList,
            'score' => $score,
        ];
    }

    /**
     * Get complete health products summary
     */
    public function getHealthProductsSummary(int $assessmentId): array {
        $departments = AssessmentDepartment::active()->ordered()->get();

        $departmentSummaries = [];

        foreach ($departments as $department) {
            $departmentSummaries[] = $this->getDepartmentSummary($assessmentId, $department->id);
        }

        // Calculate overall health products score
        $allScores = AssessmentDepartmentScore::where('assessment_id', $assessmentId)->get();
        $totalAvailable = $allScores->sum('available_count');
        $totalApplicable = $allScores->sum('total_applicable');
        $overallPercentage = $totalApplicable > 0 ? ($totalAvailable / $totalApplicable) * 100 : 0;

        $overallGrade = match (true) {
            $overallPercentage >= 80 => 'green',
            $overallPercentage >= 50 => 'yellow',
            default => 'red',
        };

        return [
            'departments' => $departmentSummaries,
            'overall' => [
                'available_count' => $totalAvailable,
                'total_applicable' => $totalApplicable,
                'percentage' => round($overallPercentage, 2),
                'grade' => $overallPercentage > 0 ? $overallGrade : null,
            ],
        ];
    }

    /**
     * Initialize responses for department (create N/A responses)
     */
    public function initializeDepartmentResponses(int $assessmentId, int $departmentId): void {
        $department = AssessmentDepartment::find($departmentId);

        if (!$department) {
            return;
        }

        // Get all applicable commodities for this department
        $applicableCommodityIds = $department->applicableCommodities()
                ->where('is_active', true)
                ->pluck('commodities.id');

        foreach ($applicableCommodityIds as $commodityId) {
            // Create response if it doesn't exist
            AssessmentCommodityResponse::firstOrCreate(
                    [
                        'assessment_id' => $assessmentId,
                        'commodity_id' => $commodityId,
                        'assessment_department_id' => $departmentId,
                    ],
                    [
                        'available' => false,
                        'score' => 0,
                    ]
            );
        }
    }
}
