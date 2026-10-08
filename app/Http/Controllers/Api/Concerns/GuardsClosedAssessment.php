<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Assessment;
use Illuminate\Http\JsonResponse;

/**
 * Shared "is this assessment still editable?" gate for every API write.
 * A submitted assessment is completed AND locked (see Assessment::lock());
 * it stays read-only until an admin reopens it via POST /assessments/{id}/reopen.
 */
trait GuardsClosedAssessment
{
    /**
     * Returns a 403 response when the assessment can't be edited, null when it can.
     */
    protected function rejectIfClosed(Assessment $assessment): ?JsonResponse
    {
        if ($assessment->isOpenForEditing()) {
            return null;
        }

        return response()->json([
            'message' => $assessment->status === 'completed'
                ? 'Completed assessments cannot be modified.'
                : 'This assessment is locked and cannot be modified.',
            'code' => 'assessment_closed',
            'status' => $assessment->status,
            'is_locked' => (bool) $assessment->is_locked,
        ], 403);
    }
}
