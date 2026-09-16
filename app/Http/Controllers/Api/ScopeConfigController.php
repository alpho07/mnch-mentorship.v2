<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacilityAssessment;
use App\Models\MentorshipClass;
use App\Models\Scope;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScopeConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $scopes = Scope::forUser($user);

        $result = $scopes->map(fn (Scope $scope) => [
            'id'       => $scope->slug,
            'label'    => $scope->label,
            'icon'     => $scope->icon,
            'color'    => $scope->color,
            'gradient' => $scope->gradient,
            'tabs'     => $scope->tabs,
            'summary'  => $this->summary($scope->slug, $user),
        ]);

        return response()->json(['scopes' => $result->values()]);
    }

    private function summary(string $slug, $user): array
    {
        try {
            return match ($slug) {
                'assessments' => [
                    'in_progress' => FacilityAssessment::where('created_by', $user->id)
                        ->where('status', 'in_progress')->count(),
                    'completed' => FacilityAssessment::where('created_by', $user->id)
                        ->where('status', 'completed')->count(),
                ],
                'mentorships' => [
                    'active_classes' => MentorshipClass::whereHas(
                        'training',
                        fn ($q) => $q->where('user_id', $user->id)
                    )->where('status', 'active')->count(),
                ],
                'trainings' => [
                    'upcoming' => Training::where('type', 'global_training')
                        ->where('start_date', '>=', now())
                        ->count(),
                ],
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }
}
