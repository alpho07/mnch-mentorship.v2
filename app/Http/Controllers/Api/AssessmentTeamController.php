<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\AssessmentTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentTeamController extends Controller {

    public function show(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        $this->authorize('view', $assessment);

        return response()->json($this->teamPayload($assessment, $teamService, $request->user()->id));
    }

    public function eligible(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        return response()->json(['data' => $teamService->getEligibleUsers($assessment)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'facility_name' => $user->facility?->name,
        ])->values()]);
    }

    public function store(Request $request, Assessment $assessment, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $teamService->addMembers($assessment, $data['member_ids'], $request->user()->id);

        return response()->json([
            'message' => 'Team members added successfully.',
            ...$this->teamPayload($assessment->fresh(), $teamService, $request->user()->id),
        ]);
    }

    private function teamPayload(Assessment $assessment, AssessmentTeamService $teamService, int $userId): array {
        $members = $teamService->getTeamForDisplay($assessment)->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role,
        ])->values();

        return [
            'lead_assessor' => $members->firstWhere('role', 'team_lead') ?? [
                'id' => $assessment->assessor_id,
                'name' => $assessment->assessor_name,
                'email' => $assessment->assessor_contact,
                'role' => 'team_lead',
            ],
            'team_members' => $members->where('role', 'member')->values(),
            'can_manage_team' => $assessment->canManageTeam($userId),
        ];
    }
}
