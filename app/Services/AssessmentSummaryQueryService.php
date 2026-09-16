<?php

namespace App\Services;

use App\Http\Controllers\AssessmentExecutiveDashboardController;
use App\Models\Assessment;
use App\Models\Facility;
use App\Models\User;

/**
 * Read-only assessment queries for MNCHGPT — reuses
 * AssessmentResource::getEloquentQuery()'s exact scoping rule (assessor
 * sees only their own assessments; super_admin/admin/division see all).
 */
class AssessmentSummaryQueryService
{
    private function scopedQuery(User $user)
    {
        $query = Assessment::query();

        if (! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where('assessor_id', $user->id);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(User $user, ?string $status = null): array
    {
        $query = $this->scopedQuery($user);

        if ($status) {
            return ['count' => (clone $query)->where('status', $status)->count()];
        }

        return [
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];
    }

    /**
     * @return array<int, array{facility: string, overall_percentage: float, overall_grade: string}>
     */
    public function readinessScores(User $user, ?string $facilityName = null, ?float $belowPercentage = null): array
    {
        $query = $this->scopedQuery($user)
            ->where('status', 'completed')
            ->with('facility');

        if ($facilityName) {
            $query->whereHas('facility', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($facilityName).'%']));
        }

        if ($belowPercentage !== null) {
            $query->where('overall_percentage', '<', $belowPercentage);
        }

        return $query->get()
            ->map(fn (Assessment $a) => [
                'facility' => $a->facility?->name ?? 'Unknown',
                'overall_percentage' => (float) $a->overall_percentage,
                'overall_grade' => (string) $a->overall_grade,
            ])
            ->values()
            ->all();
    }

    public function facilityExecutiveSummary(User $user, string $facilityName): ?array
    {
        $facility = Facility::whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($facilityName).'%'])->first();

        if (! $facility) {
            return null;
        }

        $assessment = $this->scopedQuery($user)
            ->where('facility_id', $facility->id)
            ->where('status', 'completed')
            ->latest('assessment_date')
            ->first();

        if (! $assessment) {
            return null;
        }

        $controller = app(AssessmentExecutiveDashboardController::class);
        $reflection = new \ReflectionMethod($controller, 'buildDashboardData');
        $reflection->setAccessible(true);
        $data = $reflection->invoke($controller, $assessment);

        return [
            'facility' => $facility->name,
            'insights' => array_map(fn ($i) => $i['text'], $data['insights']),
        ];
    }
}
