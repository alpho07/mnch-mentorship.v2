<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

use App\Models\County;
use App\Models\Facility;
use App\Models\User;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\MenteeStatusLog;
use App\Services\Analytics\TrainingInsightsService;

class TrainingExplorerController extends Controller
{
    public function __construct(private TrainingInsightsService $insights) {}

    public function index(Request $request)
    {
        $countyId     = $request->integer('county_id');
        $trainingId   = $request->integer('training_id');
        $facilityId   = $request->integer('facility_id');
        $trainingType = $request->get('training_type'); // 'global_trainings' | 'facility_mentorship' | null

        $dateFrom = $this->tryParseDate($request->get('from'));
        $dateTo   = $this->tryParseDate($request->get('to'));

        $filters = [
            'completion' => $request->get('completion'),
            'status'     => $request->get('status'),
            'search'     => $request->get('search'),
        ];

        $counties = County::query()->orderBy('name')->get(['id','name']);

        $insights = $this->insights->summarize([
            'county_id'      => $countyId,
            'training_id'    => $trainingId,
            'facility_id'    => $facilityId,
            'training_types' => $trainingType ? [$trainingType] : [],
            'filters'        => $filters,
        ]);

        return view('analytics.training-explorer', [
            'counties' => $counties,
            'initial'  => [
                'county_id'    => $countyId,
                'training_id'  => $trainingId,
                'facility_id'  => $facilityId,
                'training_type'=> $trainingType ?: '',
                'from'         => $dateFrom ? $dateFrom->format('Y-m-d') : '',
                'to'           => $dateTo   ? $dateTo->format('Y-m-d')   : '',
            ],
            'insights' => $insights,
            'filters'  => $filters,
        ]);
    }

    public function apiCounties(Request $request)
    {
        $q = County::query()->orderBy('name');
        if ($s = $request->get('search')) $q->where('name','like',"%{$s}%");
        return response()->json($q->get(['id','name']));
    }

    public function apiCountyTrainings(Request $request, County $county)
    {
        [$from, $to] = $this->timeRange($request);
        $trainingType = $request->get('training_type');

        $query = Training::query()
            ->when($trainingType, fn($q)=>$q->where('type',$trainingType))
            ->whereHas('participants.user.facility.subcounty', fn($q) => $q->where('county_id', $county->id))
            ->when($from && $to, fn($q) => $q->whereBetween(DB::raw('DATE(start_date)'), [$from,$to]))
            ->withCount([
                'participants as participants_count' => function ($p) use ($county, $trainingType) {
                    $p->when($trainingType, fn($w)=>$w->whereHas('training', fn($t)=>$t->where('type',$trainingType)))
                      ->whereHas('user.facility.subcounty', fn($w) => $w->where('county_id', $county->id));
                }
            ])
            ->with(['program.module:id,name']) // keep your eager loads
            ->orderByDesc('start_date');

        if ($t = $request->get('search')) {
            $query->where(fn($w) => $w->where('title','like',"%{$t}%")->orWhere('name','like',"%{$t}%"));
        }

        $trainings = $query->limit(200)->get()->map(function ($t) {
            return [
                'id'                 => $t->id,
                'name'               => $t->title ?? $t->name ?? ("Training #{$t->id}"),
                'start_date'         => optional($t->start_date)?->format('Y-m-d'),
                'end_date'           => optional($t->end_date)?->format('Y-m-d'),
                'department'         => '', // preserved from your adjusted file
                'module'             => optional($t->module)->name,
                'participants_count' => (int)($t->participants_count ?? 0),
            ];
        });

        $insights = $this->insights->summarize([
            'county_id'      => $county->id,
            'training_types' => $trainingType ? [$trainingType] : [],
            'filters'        => $this->filters($request),
        ]);

        return response()->json(['items'=>$trainings,'insights'=>$insights]);
    }

    public function apiTrainingFacilities(Request $request, County $county, Training $training)
    {
        // since a specific training is selected, its type is implied by the row; no extra filter needed
        [$from, $to] = $this->timeRange($request);

        $facilities = Facility::query()
            ->whereHas('subcounty', fn($q) => $q->where('county_id', $county->id))
            ->whereHas('users.trainingParticipations', function ($q) use ($training, $from, $to) {
                $q->where('training_id', $training->id);
                if ($from && $to) $q->whereBetween(DB::raw('DATE(start_date)'), [$from,$to]);
            })
            ->withCount(['users as participants_count' => function ($q) use ($training) {
                $q->whereHas('trainingParticipations', fn($w)=>$w->where('training_id',$training->id));
            }])
            ->orderBy('name')
            ->limit(300)
            ->get(['id','name'])
            ->map(fn($f)=>[
                'id'=>$f->id,
                'name'=>$f->name ?? ("Facility #{$f->id}"),
                'participants_count'=>(int)($f->participants_count ?? 0),
            ]);

        $insights = $this->insights->summarize([
            'county_id'   => $county->id,
            'training_id' => $training->id,
            'filters'     => $this->filters($request),
        ]);

        return response()->json(['items'=>$facilities,'insights'=>$insights]);
    }

    public function apiFacilityParticipants(Request $request, $trainingId, Facility $facility)
    {
        [$from, $to] = $this->timeRange($request);

        $participants = TrainingParticipant::query()
            ->where('training_id', $trainingId)
            ->whereHas('user', fn($q)=>$q->where('facility_id',$facility->id))
            ->with([
                'user:id,name,email,facility_id,department_id,cadre_id',
                'user.facility:id,name,subcounty_id',
                'user.facility.subcounty:id,county_id',
                'user.facility.subcounty.county:id,name',
                'training:id,title,type',
            ])
            ->when($from && $to, fn($q)=>$q->whereBetween(DB::raw('DATE(registration_date)'),[$from,$to]))
            ->when($request->get('completion'), fn($q,$v)=>$q->where('completion_status',$v))
            ->when($request->get('status'), function ($q,$v) {
                if ($v==='passed') $q->where('outcome_id',1);
                elseif ($v==='failed') $q->where('outcome_id',2);
                elseif ($v==='not_assessed') $q->whereNull('outcome_id');
            })
            ->when($request->get('search'), function ($q,$t){
                $q->whereHas('user', fn($w)=>$w->where('name','like',"%{$t}%")->orWhere('email','like',"%{$t}%"));
            })
            ->orderByDesc('completion_status')
            ->limit(500)
            ->get()
            ->map(function ($p){
                return [
                    'participant_id'  => $p->id,
                    'user_id'         => $p->user_id,
                    'name'            => $p->user?->name ?? "User #{$p->user_id}",
                    'email'           => $p->user?->email,
                    'facility'        => $p->user?->facility?->name,
                    'county'          => $p->user?->facility?->subcounty?->county?->name,
                    'completion'      => $p->completion_status,
                    'completion_date' => optional($p->completion_date)?->format('Y-m-d'),
                    'outcome_id'      => $p->outcome_id,
                ];
            });

        $insights = $this->insights->summarize([
            'training_id' => (int)$trainingId,
            'facility_id' => $facility->id,
            'filters'     => $this->filters($request),
        ]);

        return response()->json(['items'=>$participants,'insights'=>$insights]);
    }

    public function apiParticipantProfile(User $user, Request $request)
    {
        $lastTrainingDate = $user->trainingParticipations()
            ->join('trainings','trainings.id','=','training_participants.training_id')
            ->orderByDesc('trainings.start_date')
            ->value('trainings.start_date');

        $assessedCount = $user->trainingParticipations()->whereNotNull('outcome_id')->count();
        $passedCount   = $user->trainingParticipations()->where('outcome_id',1)->count();
        $scoreProxy    = $assessedCount ? round(($passedCount/$assessedCount)*100,1) : 0;

        $profile = [
            'id'          => $user->id,
            'name'        => $user->name ?? "User #{$user->id}",
            'email'       => $user->email,
            'facility'    => optional($user->facility)->name,
            'county'      => optional($user->facility?->subcounty?->county)->name,
            'cadre'       => optional($user->cadre)->name,
            'grade'       => optional($user->grade)->name,
            'department'  => optional($user->department)->name,
            'last_training_date' => $lastTrainingDate ? Carbon::parse($lastTrainingDate)->format('Y-m-d') : null,
            'score'       => $scoreProxy,
        ];

        $history = $user->trainingParticipations()
            ->with(['training:id,title,start_date,end_date,type'])
            ->orderByDesc('registration_date')
            ->limit(200)
            ->get()
            ->map(fn($tp)=>[
                'training'   => $tp->training?->title ?? $tp->training?->name ?? "Training #{$tp->training_id}",
                'start_date' => optional($tp->training?->start_date)?->format('Y-m-d'),
                'end_date'   => optional($tp->training?->end_date)?->format('Y-m-d'),
                'completion' => $tp->completion_status,
                'outcome_id' => $tp->outcome_id,
            ]);

        $stats = [
            'total_trainings'     => $history->count(),
            'completed_trainings' => $history->where('completion','completed')->count(),
            'passed_trainings'    => $history->where('outcome_id',1)->count(),
            'assessed_trainings'  => $history->whereNotNull('outcome_id')->count(),
            'completion_rate'     => 0,
            'pass_rate'           => 0,
            'average_score'       => $scoreProxy,
        ];
        if ($stats['total_trainings'] > 0) {
            $stats['completion_rate'] = round(($stats['completed_trainings'] / max(1,$stats['total_trainings']))*100,1);
            $stats['pass_rate']       = round(($stats['passed_trainings'] / max(1,$stats['assessed_trainings']))*100,1);
        }

        $ai = $this->insights->summarizeParticipant($user, $stats);

        return response()->json(['profile'=>$profile,'history'=>$history,'stats'=>$stats,'ai'=>$ai]);
    }

    public function apiAttritionLogs(User $user)
    {
        $logs = MenteeStatusLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($l)=>[
                'id'            => $l->id,
                'period_months' => (int)($l->period_months ?? 0),
                'status'        => $l->status,
                'changes'       => $this->ensureArray($l->changes),
                'notes'         => $l->notes,
                'recorded_at'   => optional($l->created_at)?->format('Y-m-d H:i'),
                'recorded_by'   => $l->recorded_by ?? null,
            ]);

        return response()->json(['items'=>$logs]);
    }

    public function storeAttritionLog(User $user, Request $request)
    {
        $data = $request->validate([
            'period_months' => 'required|in:3,6,9',
            'status'        => 'required|in:active,moved,changed_cadre,changed_department,changed_facility,retired',
            'changes'       => 'array',
            'notes'         => 'nullable|string|max:2000',
        ]);

        $log = new MenteeStatusLog();
        $log->user_id       = $user->id;
        $log->period_months = (int)$data['period_months'];
        $log->status        = $data['status'];
        $log->changes       = $data['changes'] ?? [];
        $log->notes         = $data['notes'] ?? null;
        $log->recorded_by   = auth()->id();
        $log->save();

        return response()->json(['ok'=>true, 'id'=>$log->id]);
    }

    // -------------- helpers --------------

    private function tryParseDate(?string $value): ?Carbon
    {
        if (!$value) return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    private function timeRange(Request $request): array
    {
        $from = $this->tryParseDate($request->get('from'));
        $to   = $this->tryParseDate($request->get('to'));
        return [$from?->format('Y-m-d'), $to?->format('Y-m-d')];
    }

    private function filters(Request $request): array
    {
        return [
            'completion' => $request->get('completion'),
            'status'     => $request->get('status'),
            'search'     => $request->get('search'),
        ];
    }

    private function ensureArray($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) {
            try { $decoded = json_decode($value, true); return is_array($decoded) ? $decoded : []; }
            catch (\Throwable) { return []; }
        }
        return [];
    }
}