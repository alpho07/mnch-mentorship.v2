<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentTeamService {
    // =========================================================================
    // ADD MEMBER
    // =========================================================================

    /**
     * Add a user to an assessment team as a regular member.
     *
     * @throws \Exception if the actor is not permitted
     */
    public function addMember(Assessment $assessment, int $userId, int $actorId): void {
        $this->assertCanManageTeam($assessment, $actorId);
        $this->ensureLegacyCreatorIsTeamLead($assessment, $actorId);

        if ($assessment->isTeamMember($userId)) {
            return; // already on team — silent no-op
        }

        $this->ensureHasAssessorRole($userId);

        $assessment->teamMembers()->attach($userId, [
            'role' => 'member',
            'added_by' => $actorId,
            'added_at' => now(),
        ]);
    }

    /**
     * Add multiple users at once.
     */
    public function addMembers(Assessment $assessment, array $userIds, int $actorId): void {
        $this->assertCanManageTeam($assessment, $actorId);
        $this->ensureLegacyCreatorIsTeamLead($assessment, $actorId);

        $existing = $assessment->teamMembers()->pluck('users.id')->toArray();

        foreach ($userIds as $userId) {
            if (!in_array($userId, $existing)) {
                $this->ensureHasAssessorRole($userId);

                $assessment->teamMembers()->attach($userId, [
                    'role' => 'member',
                    'added_by' => $actorId,
                    'added_at' => now(),
                ]);
            }
        }
    }

    // =========================================================================
    // REMOVE MEMBER
    // =========================================================================

    /**
     * Remove a user from the team.
     * You cannot remove the last team lead.
     *
     * @throws \Exception
     */
    public function removeMember(Assessment $assessment, int $userId, int $actorId): void {
        $this->assertCanManageTeam($assessment, $actorId);

        // Prevent removing the last team lead
        if ($assessment->isTeamLead($userId)) {
            $leadCount = $assessment->teamLeads()->count();
            if ($leadCount <= 1) {
                throw new \Exception('Cannot remove the only team lead. Promote another member first.');
            }
        }

        $assessment->teamMembers()->detach($userId);
    }

    // =========================================================================
    // PROMOTE TO TEAM LEAD
    // =========================================================================

    /**
     * Promote a member to team lead.
     * The current team lead is demoted to member (one lead at a time).
     * Superior roles can bypass the single-lead constraint and assign freely.
     *
     * @throws \Exception
     */
    public function promoteToTeamLead(Assessment $assessment, int $userId, int $actorId): void {
        $actor = User::findOrFail($actorId);
        $isSuperior = $actor->hasRole(['super_admin', 'admin', 'division']);

        if (!$isSuperior) {
            // Must be the current team lead to transfer the role
            if (!$assessment->isTeamLead($actorId)) {
                throw new \Exception('Only the current team lead or an administrator can transfer the team lead role.');
            }
        }

        if (!$assessment->isTeamMember($userId)) {
            throw new \Exception('The user must be a team member before being promoted to team lead.');
        }

        DB::transaction(function () use ($assessment, $userId, $actorId, $isSuperior) {
            // Demote all existing team leads to member (unless superior is assigning)
            $assessment->teamLeads()->each(function ($lead) use ($assessment) {
                $assessment->teamMembers()->updateExistingPivot($lead->id, ['role' => 'member']);
            });

            // Promote the target user
            $assessment->teamMembers()->updateExistingPivot($userId, [
                'role' => 'team_lead',
                'added_by' => $actorId,
            ]);
        });
    }

    // =========================================================================
    // DEMOTE TEAM LEAD TO MEMBER
    // =========================================================================

    /**
     * Demote a team lead back to member.
     * Only superior roles can do this unilaterally (without a replacement).
     *
     * @throws \Exception
     */
    public function demoteToMember(Assessment $assessment, int $userId, int $actorId): void {
        $actor = User::findOrFail($actorId);

        if (!$actor->hasRole(['super_admin', 'admin', 'division'])) {
            throw new \Exception('Only administrators can demote a team lead without assigning a replacement. Use "Transfer Lead" instead.');
        }

        $leadCount = $assessment->teamLeads()->count();
        if ($leadCount <= 1 && $assessment->isTeamLead($userId)) {
            throw new \Exception('Cannot demote the only team lead. Assign a replacement first.');
        }

        $assessment->teamMembers()->updateExistingPivot($userId, ['role' => 'member']);
    }

    // =========================================================================
    // LOCK / UNLOCK
    // =========================================================================

    public function lock(Assessment $assessment, int $actorId): void {
        $this->assertCanToggleLock($assessment, $actorId);
        $assessment->lock($actorId);
    }

    public function unlock(Assessment $assessment, int $actorId): void {
        $this->assertCanToggleLock($assessment, $actorId);
        $assessment->unlock();
    }

    // =========================================================================
    // PERMISSION ASSERTIONS
    // =========================================================================

    private function assertCanManageTeam(Assessment $assessment, int $actorId): void {
        if (!$assessment->canManageTeam($actorId)) {
            throw new \Exception('You do not have permission to manage this assessment team.');
        }
    }

    private function assertCanToggleLock(Assessment $assessment, int $actorId): void {
        if (!$assessment->canToggleLock($actorId)) {
            throw new \Exception('Only the team lead or an administrator can lock or unlock this assessment.');
        }
    }

    /**
     * Give the original assessor lead ownership the first time a legacy
     * assessment is shared. New assessments receive this pivot row on
     * create via the Assessment model's `created` event.
     */
    private function ensureLegacyCreatorIsTeamLead(Assessment $assessment, int $actorId): void {
        if ($assessment->teamLeads()->exists()) {
            return;
        }

        if ($assessment->assessor_id !== $actorId && $assessment->created_by !== $actorId) {
            return;
        }

        $assessment->teamMembers()->syncWithoutDetaching([
            $actorId => [
                'role' => 'team_lead',
                'added_by' => $actorId,
                'added_at' => now(),
            ],
        ]);
    }

    /**
     * Anyone added to an assessment team needs to be able to work on it,
     * which requires the 'assessor' role's permissions. Adding it doesn't
     * remove whatever roles the user already had.
     */
    private function ensureHasAssessorRole(int $userId): void {
        $user = User::find($userId);

        if ($user && !$user->hasRole('assessor')) {
            $user->assignRole('assessor');
        }
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    /**
     * Get users eligible to be added to an assessment team.
     * Returns users with the 'Assessor' role, excluding existing team members.
     * Used by the mobile API's fixed "eligible" list.
     */
    public function getEligibleUsers(Assessment $assessment): \Illuminate\Database\Eloquent\Collection {
        $existingIds = $assessment->teamMembers()->pluck('users.id')->toArray();

        return User::role('assessor')
                        ->whereNotIn('id', $existingIds)
                        ->where('status', 'active')
                        ->orderBy('name')
                        ->get();
    }

    /**
     * Search all active users by name/email for the admin "Manage Team"
     * invite field — not restricted to the 'assessor' role, since a team
     * lead may want to invite anyone and have them promoted to assessor.
     */
    public function searchEligibleUsers(Assessment $assessment, string $search): \Illuminate\Support\Collection {
        $existingIds = $assessment->teamMembers()->pluck('users.id')->toArray();

        return User::query()
                        ->whereNotIn('id', $existingIds)
                        ->where('status', 'active')
                        ->where(function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(50)
                        ->get();
    }

    /**
     * Get the team for display, sorted: team_lead first, then members alphabetically.
     */
    public function getTeamForDisplay(Assessment $assessment): \Illuminate\Support\Collection {
        return $assessment->teamMembers()
                        ->withPivot(['role', 'added_by', 'added_at'])
                        ->get()
                        ->sortBy([
                            fn($a, $b) => $a->pivot->role === 'team_lead' ? -1 : 1,
                            fn($a, $b) => strcmp($a->name, $b->name),
        ]);
    }
}
