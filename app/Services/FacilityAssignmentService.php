<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\IndicatorReportType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacilityAssignmentService {
    // ──────────────────────────────────────────────────────────────────────────
    // Facility resolution
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the facility a given user should report for.
     * - If user has facility_id → use that directly.
     * - If super_admin with no facility → return null (they must choose).
     */
    public function resolveUserFacility(User $user): ?Facility {
        if ($user->facility_id) {
            return $user->facility()->with('subcounty.county')->first();
        }

        return null;
    }

    /**
     * Determine whether the current user can change the facility selection.
     * Only super_admin with no locked assignment can change freely.
     */
    public function canChangeFacility(User $user, ?FacilityIndicatorAssignment $assignment): bool {
        if ($user->hasRole('super_admin')) {
            return true; // super_admin can always change
        }

        // Non-super_admin users: cannot change if they have a facility_id
        if ($user->facility_id) {
            return false;
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Assignment management
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get or create the assignment record for a facility.
     */
    public function getOrCreateAssignment(int $facilityId): FacilityIndicatorAssignment {
        return FacilityIndicatorAssignment::firstOrCreate(
                        ['facility_id' => $facilityId],
                        [
                            'enabled_report_types' => [],
                            'is_locked' => false,
                        ]
                );
    }

    /**
     * Save (or update) assignment settings.
     * Cannot be called on a locked assignment unless by super_admin.
     *
     * @param int   $facilityId
     * @param array $enabledReportTypeIds   array of IndicatorReportType IDs
     * @param User  $actor                  the user performing the action
     *
     * @throws \RuntimeException if locked and actor is not super_admin
     */
    public function save(int $facilityId, array $enabledReportTypeIds, User $actor): FacilityIndicatorAssignment {
        $assignment = $this->getOrCreateAssignment($facilityId);

        if ($assignment->is_locked && !$actor->hasRole('super_admin')) {
            throw new \RuntimeException('Facility assignment is locked. Only a super administrator can modify it.');
        }

        $assignment->update([
            'enabled_report_types' => array_values($enabledReportTypeIds),
            'last_updated_at' => now(),
            'last_updated_by' => $actor->id,
        ]);

        Log::info('Facility indicator assignment updated', [
            'facility_id' => $facilityId,
            'enabled_report_types' => $enabledReportTypeIds,
            'actor_id' => $actor->id,
        ]);

        return $assignment->fresh();
    }

    /**
     * Confirm and lock the assignment.
     * Locking prevents future changes by non-super_admin users.
     */
    public function lock(int $facilityId, array $enabledReportTypeIds, User $actor): FacilityIndicatorAssignment {
        return DB::transaction(function () use ($facilityId, $enabledReportTypeIds, $actor) {
                    $assignment = $this->save($facilityId, $enabledReportTypeIds, $actor);

                    $assignment->update([
                        'is_locked' => true,
                        'locked_at' => now(),
                        'locked_by' => $actor->id,
                    ]);

                    Log::info('Facility indicator assignment locked', [
                        'facility_id' => $facilityId,
                        'actor_id' => $actor->id,
                    ]);

                    return $assignment->fresh();
                });
    }

    /**
     * Unlock an assignment (super_admin only).
     */
    public function unlock(int $facilityId, User $actor): FacilityIndicatorAssignment {
        if (!$actor->hasRole('super_admin')) {
            throw new \RuntimeException('Only a super administrator can unlock a facility assignment.');
        }

        $assignment = $this->getOrCreateAssignment($facilityId);
        $assignment->unlock($actor->id);

        Log::info('Facility indicator assignment unlocked', [
            'facility_id' => $facilityId,
            'actor_id' => $actor->id,
        ]);

        return $assignment->fresh();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Status helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns a structured status for the facility setup page.
     */
    public function getStatus(User $user, ?int $facilityId): array {
        if (!$facilityId) {
            return [
                'configured' => false,
                'locked' => false,
                'assignment' => null,
                'facility' => null,
                'report_types' => [],
            ];
        }

        $facility = Facility::with('subcounty.county')->find($facilityId);
        $assignment = FacilityIndicatorAssignment::where('facility_id', $facilityId)->first();

        $enabledTypes = $assignment ? IndicatorReportType::whereIn('id', $assignment->enabled_report_types ?? [])->get() : collect();

        return [
            'configured' => $assignment !== null && !empty($assignment->enabled_report_types),
            'locked' => $assignment?->is_locked ?? false,
            'assignment' => $assignment,
            'facility' => $facility,
            'report_types' => $enabledTypes,
        ];
    }

    /**
     * Check whether a facility is fully configured and ready for reporting.
     */
    public function isReadyForReporting(int $facilityId): bool {
        $assignment = FacilityIndicatorAssignment::where('facility_id', $facilityId)->first();

        return $assignment && $assignment->is_locked && !empty($assignment->enabled_report_types);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // List helpers (for admin overviews)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Get all facilities with their assignment status (for admin dashboard).
     */
    public function getAllFacilitiesWithStatus() {
        return Facility::with(['subcounty.county'])
                        ->withCount(['indicatorAssignment as has_assignment' => function ($q) {
                                $q->where('is_locked', true);
                            }])
                        ->leftJoin('facility_indicator_assignments', 'facilities.id', '=', 'facility_indicator_assignments.facility_id')
                        ->select('facilities.*', 'facility_indicator_assignments.is_locked', 'facility_indicator_assignments.enabled_report_types')
                        ->orderBy('facilities.name')
                        ->get();
    }
}
