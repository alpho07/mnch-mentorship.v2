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

    /**
     * DELETE /api/v1/assessments/{assessment}/team/{user}
     * Remove a member. The only team lead can't be removed (transfer lead first).
     */
    public function destroy(Request $request, Assessment $assessment, \App\Models\User $user, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        if (! $assessment->isTeamMember($user->id)) {
            return response()->json(['message' => 'That user is not on this assessment team.'], 404);
        }

        try {
            $teamService->removeMember($assessment, $user->id, $request->user()->id);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Team member removed.',
            ...$this->teamPayload($assessment->fresh(), $teamService, $request->user()->id),
        ]);
    }

    /**
     * PUT /api/v1/assessments/{assessment}/team/{user}/role
     * Body: { "role": "team_lead" | "member" }
     *
     * team_lead = transfer the lead role to this member (current lead becomes
     * a member). member = demote a lead (administrators only, needs another lead).
     */
    public function updateRole(Request $request, Assessment $assessment, \App\Models\User $user, AssessmentTeamService $teamService): JsonResponse {
        abort_unless($assessment->canManageTeam($request->user()->id), 403);

        $data = $request->validate(['role' => ['required', 'in:team_lead,member']]);

        try {
            $data['role'] === 'team_lead'
                ? $teamService->promoteToTeamLead($assessment, $user->id, $request->user()->id)
                : $teamService->demoteToMember($assessment, $user->id, $request->user()->id);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $data['role'] === 'team_lead' ? "{$user->name} is now the team lead." : "{$user->name} is now a team member.",
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
            'is_locked' => (bool) $assessment->is_locked,
            'status' => $assessment->status,
        ];
    }
}
