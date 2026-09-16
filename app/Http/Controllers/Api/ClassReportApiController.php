<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Http\JsonResponse;

class ClassReportApiController extends Controller
{
    use AuthorizesClassAccess;

    /**
     * GET /api/v1/classes/{class}/report
     */
    public function show(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $class->load([
            'training.program',
            'training.facility',
            'training.mentor',
            'classModules.programModule',
            'participants.user.cadre',
        ]);

        $modules = $class->classModules->sortBy('order_sequence')->values();

        $mentees = $class->participants
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->sortBy(fn($p) => $p->user?->name ?? '')
            ->map(function (ClassParticipant $participant) use ($modules) {
                $progress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                    ->whereIn('class_module_id', $modules->pluck('id'))
                    ->get()
                    ->keyBy('class_module_id');

                $attended = $progress->whereIn('status', ['in_progress', 'completed', 'exempted'])->count();
                $total    = $modules->count();
                $rate     = $total > 0 ? round(($attended / $total) * 100) : 0;

                $moduleProgress = [];
                foreach ($modules as $mod) {
                    $moduleProgress[(string) $mod->id] = $progress[$mod->id]?->status ?? 'not_started';
                }

                return [
                    'participant_id'  => $participant->id,
                    'name'            => $participant->user?->name ?? 'Unknown',
                    'email'           => $participant->user?->email,
                    'cadre'           => $participant->user?->cadre?->name,
                    'attended'        => $attended,
                    'total_modules'   => $total,
                    'attendance_pct'  => $rate,
                    'class_complete'  => $participant->status === 'completed',
                    'module_progress' => $moduleProgress,
                ];
            })
            ->values();

        $avgAttendance   = $mentees->avg('attendance_pct') ?? 0;
        $modulesCompleted = $modules->where('status', 'completed')->count();

        return response()->json([
            'data' => [
                'class' => [
                    'id'         => $class->id,
                    'name'       => $class->name,
                    'status'     => $class->status,
                    'start_date' => $class->start_date?->toDateString(),
                    'end_date'   => $class->end_date?->toDateString(),
                    'training'   => $class->training?->title,
                    'program'    => $class->training?->program?->name,
                    'facility'   => $class->training?->facility?->name,
                    'mentor'     => $class->training?->mentor?->name,
                ],
                'summary' => [
                    'enrolled'           => $mentees->count(),
                    'modules_total'      => $modules->count(),
                    'modules_completed'  => $modulesCompleted,
                    'avg_attendance_pct' => round($avgAttendance, 1),
                ],
                'modules' => $modules->map(fn($m) => [
                    'id'             => $m->id,
                    'name'           => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
                    'status'         => $m->status,
                    'order_sequence' => $m->order_sequence,
                ])->values(),
                'mentees' => $mentees,
            ],
        ]);
    }
}
