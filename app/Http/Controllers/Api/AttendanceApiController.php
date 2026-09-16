<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    use AuthorizesClassAccess;
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/v1/modules/{module}/attendance
     */
    public function roster(ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $participants = ClassParticipant::with('user')
            ->where('mentorship_class_id', $module->mentorship_class_id)
            ->get();

        $markedUserIds = ClassAttendance::where('class_module_id', $module->id)
            ->pluck('user_id')
            ->flip();

        $data = $participants->map(fn(ClassParticipant $p) => [
            'participant_id' => $p->id,
            'user_id'        => $p->user_id,
            'name'           => $p->user?->name,
            'status'         => isset($markedUserIds[$p->user_id])
                ? 'present'
                : ($module->status === 'not_started' ? 'not_started' : 'absent'),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/modules/{module}/attendance/{participant}
     * Body: { "status": "present"|"absent" }
     */
    public function mark(Request $request, ClassModule $module, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        // Verify participant belongs to this module's class
        abort_if(
            $participant->mentorship_class_id !== $module->mentorship_class_id,
            422,
            'Participant is not enrolled in this class.'
        );

        $request->validate(['status' => 'required|in:present,absent']);

        $mentee = $participant->user;
        abort_if(!$mentee, 404, 'Participant user not found.');

        $this->attendanceService->markManualModuleAttendance(
            $request->user(),
            $module,
            $mentee,
            $request->status
        );

        return response()->json(['message' => 'Attendance recorded.']);
    }

    /**
     * POST /api/v1/modules/{module}/attendance/bulk
     * Body: { "attendances": [{"participant_id": 1, "status": "present"}, ...] }
     */
    public function bulk(Request $request, ClassModule $module): JsonResponse
    {
        $this->authorizeModuleAccess($module);

        $request->validate([
            'attendances'                  => 'required|array',
            'attendances.*.participant_id' => 'required|integer',
            'attendances.*.status'         => 'required|in:present,absent',
        ]);

        foreach ($request->attendances as $entry) {
            $participant = ClassParticipant::find($entry['participant_id']);
            if (!$participant || !$participant->user) continue;
            $this->attendanceService->markManualModuleAttendance(
                $request->user(),
                $module,
                $participant->user,
                $entry['status']
            );
        }

        return response()->json(['message' => 'Bulk attendance recorded.']);
    }
}
