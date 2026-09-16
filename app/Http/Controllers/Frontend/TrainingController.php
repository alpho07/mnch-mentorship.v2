<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $moh = Training::query()
            ->where('type', 'global_training')
            ->latest('start_date')
            ->limit(6)
            ->get();

        $mentorship = Training::query()
            ->where('type', 'facility_mentorship')
            ->latest('start_date')
            ->limit(6)
            ->get();

        return view('training.index', compact('moh', 'mentorship'));
    }

    public function moh(Request $request)
    {
        [$statusFilter, $query] = $this->baseTrainingQuery('global_training', $request);
        $trainings = $query->paginate(12)->withQueryString();

        return view('training.moh', [
            'trainings' => $trainings,
            'status' => $statusFilter,
        ]);
    }

    public function mentorship(Request $request)
    {
        [$statusFilter, $query] = $this->baseTrainingQuery('facility_mentorship', $request);
        $trainings = $query->paginate(12)->withQueryString();

        return view('training.mentorship', [
            'trainings' => $trainings,
            'status' => $statusFilter,
        ]);
    }

    public function show(Training $training, Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', '');
        $attendance = (string) $request->get('attendance', '');

        $participants = TrainingParticipant::query()
            ->with(['user', 'outcome'])
            ->where('training_id', $training->id)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('user', function ($uq) use ($q) {
                    $uq->where('first_name', 'like', "%{$q}%")
                       ->orWhere('last_name', 'like', "%{$q}%")
                       ->orWhere('middle_name', 'like', "%{$q}%")
                       ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($qq) => $qq->where('completion_status', $status))
            ->when($attendance !== '', fn ($qq) => $qq->where('attendance_status', $attendance))
            ->orderBy('registration_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'total'       => (clone $participants)->total(),
            'completed'   => $this->countWhere($training->id, 'completion_status', 'completed'),
            'in_progress' => $this->countWhere($training->id, 'completion_status', 'in_progress'),
        ];

        $completionStats = method_exists($training, 'getCompletionStats')
            ? $training->getCompletionStats()
            : [
                'total' => $counts['total'],
                'completed' => $counts['completed'],
                'in_progress' => $counts['in_progress'],
                'completion_rate' => ($counts['total'] > 0)
                    ? round(($counts['completed'] / $counts['total']) * 100, 2)
                    : 0,
            ];

        $advanced = ['pass_rate' => null, 'average_score' => null];
        if (method_exists($training, 'getMenteeAssessmentSummary')) {
            $summary = $training->getMenteeAssessmentSummary();
            $advanced['pass_rate']     = $summary['pass_rate']     ?? null;
            $advanced['average_score'] = $summary['average_score'] ?? null;
        }

        return view('training.show', [
            'training'        => $training,
            'participants'    => $participants,
            'completionStats' => $completionStats,
            'advanced'        => $advanced,
            'q'               => $q,
            'status'          => $status,
            'attendance'      => $attendance,
        ]);
    }

    protected function countWhere(int $trainingId, string $col, string $val): int
    {
        return TrainingParticipant::query()
            ->where('training_id', $trainingId)
            ->where($col, $val)
            ->count();
    }

    protected function baseTrainingQuery(string $type, Request $request): array
    {
        $status = $request->string('status')->toString() ?: 'upcoming';
        $query = Training::query()->where('type', $type);
        $now = Carbon::now();

        switch ($status) {
            case 'ongoing':
                $query->where(function ($q) use ($now) {
                    $q->where(function ($q2) use ($now) {
                        $q2->whereDate('start_date', '<=', $now->toDateString())
                           ->whereDate('end_date', '>=', $now->toDateString());
                    })->orWhere('status', 'ongoing');
                });
                break;
            case 'completed':
                $query->where(function ($q) use ($now) {
                    $q->where('status', 'completed')
                      ->orWhereDate('end_date', '<', $now->toDateString());
                });
                break;
            case 'all':
                break;
            case 'upcoming':
            default:
                $query->where(function ($q) use ($now) {
                    $q->whereDate('start_date', '>', $now->toDateString())
                      ->orWhereIn('status', ['upcoming', 'scheduled']);
                });
                break;
        }

        $query->orderBy('start_date', $status === 'upcoming' ? 'asc' : 'desc');

        return [$status, $query];
    }
}
