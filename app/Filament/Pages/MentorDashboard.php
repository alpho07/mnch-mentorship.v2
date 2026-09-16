<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassAttendance;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipCoMentor;
use App\Models\Training;
use App\Services\EmoncReportingService;
use App\Services\MentorPriorityQueueResolver;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorDashboard extends Page
{
    protected static string $view = 'filament.pages.mentor-dashboard';

    protected static ?string $slug = 'mentor-dashboard';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Mentor Dashboard';

    protected static ?int $navigationSort = 2;

    private const MENTOR_ROLES = [
        'facility_mentor', 'facility_mentor_lead',
        'county_mentor_lead', 'subcounty_mentor_lead',
        'spoke_mentor', 'spoke_mentor_lead',
        'national_mentor_lead', 'national_mentor',
        'co_mentor', 'co-mentor',
    ];

    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_MentorDashboard');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_MentorDashboard');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check()) {
            return null;
        }
        $user = auth()->user();
        $count = Training::where('type', 'facility_mentorship')
            ->when(! $user->hasRole(self::SENIOR_ROLES), fn ($q) => $q->forMentorOrCoMentor($user->id))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    // ─── Loaded state ────────────────────────────────────────────────────────
    public array $kpis = [];

    public array $mentorshipItems = [];   // current-page mentorship breakdown

    public int $mentorshipsTotal = 0;

    public int $mentorshipsPage = 1;

    public int $mentorshipsPerPage = 10;

    public array $activityFeed = [];   // recent recommendations + confirmations

    public array $insights = [];   // derived flags for decision-making

    public array $priorityQueue = [];   // ranked cross-tier action queue

    public array $pendingVideoReviews = [];   // pending hands-on video reviews

    public array $allMentorships = [];   // full unfiltered list (for client-side sort/filter)

    public string $mdSort = 'created_at';

    public string $mdDir = 'desc';

    public string $mdStatus = '';

    public string $mdProgram = '';

    public string $mdSearch = '';

    public function mount(): void
    {
        $this->loadDashboard();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core loader
    // ─────────────────────────────────────────────────────────────────────────

    private function loadDashboard(): void
    {
        $userId = auth()->id();
        $trainingIds = $this->getMyTrainingIds($userId);

        if (empty($trainingIds)) {
            $this->kpis = $this->emptyKpis();
            $this->mentorshipItems = [];
            $this->mentorshipsTotal = 0;
            $this->mentorshipsPage = 1;
            $this->insights = [
                'mentees_needing_attention' => 0,
                'low_attendance_classes'    => 0,
                'stalled_modules'           => 0,
                'recs_coverage'             => 100,
            ];
            $this->priorityQueue = [];

            return;
        }

        // Pull full data in one batch per entity type
        $trainings = Training::whereIn('id', $trainingIds)
            ->with(['facility', 'program'])
            ->get()
            ->keyBy('id');

        $classes = MentorshipClass::whereIn('training_id', $trainingIds)
            ->with(['classModules.programModule', 'classModules.attendanceRecords'])
            ->get();

        $classIds = $classes->pluck('id');
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $participants = ClassParticipant::with('user')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $progress = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->get();

        $attendances = ClassAttendance::whereIn('class_id', $classIds)
            ->whereNotNull('class_module_id')
            ->get();

        // ── KPIs ──────────────────────────────────────────────────────────────
        $totalMentees = $participants->pluck('user_id')->unique()->count();
        $totalModules = $classes->sum(fn ($c) => $c->classModules->count());
        $completedModules = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
        $totalEnrollments = $participants->count();
        $confirmedAtt = $attendances->count();

        // Attendance rate: confirmations / (modules_in_progress_or_completed * enrolled_per_class)
        $possibleAttendances = 0;
        foreach ($classes as $class) {
            $enrolled = $participants->where('mentorship_class_id', $class->id)->count();
            $activeModules = $class->classModules->whereIn('status', ['in_progress', 'completed'])->count();
            $possibleAttendances += $enrolled * $activeModules;
        }
        $attendanceRate = $possibleAttendances > 0
            ? round(($confirmedAtt / $possibleAttendances) * 100, 1)
            : 0;

        // Completion rate: per mentee, how many modules completed vs total
        $progressByParticipant = $progress->groupBy('class_participant_id');
        $completionRates = [];
        foreach ($participants as $p) {
            $myProgress = $progressByParticipant->get($p->id, collect());
            $total = $myProgress->count();
            if ($total === 0) {
                continue;
            }
            $done = $myProgress->where('status', 'completed')->count();
            $completionRates[] = round(($done / $total) * 100);
        }
        $avgCompletion = count($completionRates) > 0
            ? round(array_sum($completionRates) / count($completionRates), 1)
            : 0;

        $activeClasses = $classes->where('status', 'active')->count();
        $completedClasses = $classes->where('status', 'completed')->count();
        $recCount = $progress->whereNotNull('mentor_recommendation')->count();

        $pending = app(EmoncReportingService::class)->pendingItemsForUser($userId, $trainingIds);
        $this->pendingVideoReviews = app(EmoncReportingService::class)->pendingVideoReviewItemsForUser($userId, $trainingIds);

        $this->kpis = [
            'active_mentorships' => $trainings->count(),
            'active_classes' => $activeClasses,
            'completed_classes' => $completedClasses,
            'total_mentees' => $totalMentees,
            'total_enrollments' => $totalEnrollments,
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'attendance_rate' => $attendanceRate,
            'avg_completion' => $avgCompletion,
            'recommendations' => $recCount,
            'module_completion_rate' => $totalModules > 0
                ? round(($completedModules / $totalModules) * 100, 1)
                : 0,
            'pending_video_reviews' => $pending['video_reviews'],
            'pending_mentor_approvals' => $pending['mentor_approvals'],
            'pending_drmh_approvals' => $pending['drmh_approvals'],
        ];

        // ── Per-Mentorship Breakdown ──────────────────────────────────────────
        $mentorships = [];
        foreach ($trainingIds as $tid) {
            $training = $trainings->get($tid);
            if (! $training) {
                continue;
            }

            $myClasses = $classes->where('training_id', $tid);
            $myClassIds = $myClasses->pluck('id');
            $myParticipants = $participants->whereIn('mentorship_class_id', $myClassIds->toArray());
            $myModuleIds = $myClasses->flatMap(fn ($c) => $c->classModules->pluck('id'));
            $myProgress = $progress->whereIn('class_module_id', $myModuleIds->toArray());
            $myAttendances = $attendances->whereIn('class_id', $myClassIds->toArray());

            $mModTotal = $myClasses->sum(fn ($c) => $c->classModules->count());
            $mModDone = $myClasses->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());
            $mMentees = $myParticipants->pluck('user_id')->unique()->count();
            $mRecs = $myProgress->whereNotNull('mentor_recommendation')->count();

            // Module progress distribution
            $notStarted = $myProgress->where('status', 'not_started')->count();
            $inProgress = $myProgress->where('status', 'in_progress')->count();
            $completed = $myProgress->where('status', 'completed')->count();
            $total = $notStarted + $inProgress + $completed;

            $mentorships[] = [
                'id' => $tid,
                'title' => $training->title ?? 'Unnamed Mentorship',
                'facility' => $training->facility?->name ?? '—',
                'program_name' => $training->program?->name ?? '—',
                'status' => $training->status,
                'start_date' => $training->start_date,
                'end_date' => $training->end_date,
                'created_at' => $training->created_at?->toDateTimeString(),
                'classes_count' => $myClasses->count(),
                'active_classes' => $myClasses->where('status', 'active')->count(),
                'mentees' => $mMentees,
                'modules_total' => $mModTotal,
                'modules_done' => $mModDone,
                'module_pct' => $mModTotal > 0 ? round(($mModDone / $mModTotal) * 100) : 0,
                'recommendations' => $mRecs,
                'dist_not_started' => $total > 0 ? round(($notStarted / $total) * 100) : 0,
                'dist_in_progress' => $total > 0 ? round(($inProgress / $total) * 100) : 0,
                'dist_completed' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'url' => MentorshipTrainingResource::getUrl('classes', ['record' => $tid]),
            ];
        }

        // Filter by program query param for Mentorships group links
        $programFilter = request('program');
        if ($programFilter && is_string($programFilter)) {
            $filter = strtolower($programFilter);
            $mentorships = collect($mentorships)
                ->filter(function ($m) use ($filter) {
                    $programName = strtolower($m['program_name'] ?? '');

                    return match ($filter) {
                        'newborn' => $programName === 'newborn care',
                        'infant'  => $programName === 'infant and child care',
                        'emonc'   => str_contains($programName, 'emonc') || str_contains($programName, 'maternal'),
                        default   => str_contains($programName, $filter),
                    };
                })
                ->values()
                ->toArray();
        }

        $this->allMentorships = $mentorships;
        $this->mentorshipsPage = 1;
        $this->applyMentorshipFilters();

        // ── Mentees needing attention (no full roster) ────────────────────────
        $progressByParticipant = $progress->groupBy('class_participant_id');
        $needsAttentionCount = 0;
        $seenUserIds = [];
        foreach ($participants as $p) {
            if (isset($seenUserIds[$p->user_id])) {
                continue;
            }
            $seenUserIds[$p->user_id] = true;
            $myParticipantIds = $participants->where('user_id', $p->user_id)->pluck('id')->toArray();
            $myProg = $progress->whereIn('class_participant_id', $myParticipantIds);
            $myModTotal = $myProg->count();
            if ($myModTotal === 0) {
                continue;
            }
            $myModDone = $myProg->where('status', 'completed')->count();
            $pct = round(($myModDone / $myModTotal) * 100);
            if ($pct < 40) {
                $needsAttentionCount++;
            }
        }

        // ── Activity Feed ─────────────────────────────────────────────────────
        $recentRecs = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->whereNotNull('mentor_recommendation')
            ->whereNotNull('recommendation_written_at')
            ->with(['classParticipant.user', 'classModule.programModule'])
            ->orderByDesc('recommendation_written_at')
            ->limit(10)
            ->get();

        $this->activityFeed = $recentRecs->map(fn ($r) => [
            'type' => 'recommendation',
            'mentee' => $r->classParticipant?->user?->name ?? '—',
            'module' => $r->classModule?->programModule?->name ?? '—',
            'excerpt' => \Illuminate\Support\Str::limit($r->mentor_recommendation, 80),
            'at' => $r->recommendation_written_at,
        ])->toArray();

        // ── Insights ──────────────────────────────────────────────────────────
        $this->insights = [
            'mentees_needing_attention' => $needsAttentionCount,
            'low_attendance_classes' => $classes->filter(function ($c) use ($attendances, $participants) {
                $enrolled = $participants->where('mentorship_class_id', $c->id)->count();
                $confirmed = $attendances->where('class_id', $c->id)->count();
                if ($enrolled === 0) {
                    return false;
                }

                return ($confirmed / $enrolled) < 0.6;
            })->count(),
            'stalled_modules' => $classes->sum(fn ($c) => $c->classModules->where('status', 'not_started')->count()
            ),
            'recs_coverage' => $totalEnrollments > 0
                ? round(($recCount / max($totalEnrollments, 1)) * 100)
                : 0,
        ];

        // ── Priority queue ───────────────────────────────────────────────────
        $this->priorityQueue = app(MentorPriorityQueueResolver::class)->resolve(auth()->user(), $trainingIds);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sort / filter actions
    // ─────────────────────────────────────────────────────────────────────────

    public function setSort(string $field): void
    {
        if ($this->mdSort === $field) {
            $this->mdDir = $this->mdDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->mdSort = $field;
            $this->mdDir = 'asc';
        }
        $this->mentorshipsPage = 1;
        $this->applyMentorshipFilters();
    }

    public function updatedMdSearch(): void
    {
        $this->mentorshipsPage = 1;
        $this->applyMentorshipFilters();
    }

    public function updatedMdStatus(): void
    {
        $this->mentorshipsPage = 1;
        $this->applyMentorshipFilters();
    }

    public function updatedMdProgram(): void
    {
        $this->mentorshipsPage = 1;
        $this->applyMentorshipFilters();
    }

    public function setPage(int $page): void
    {
        $this->mentorshipsPage = max(1, $page);
        $this->applyMentorshipFilters();
    }

    private function applyMentorshipFilters(): void
    {
        $items = collect($this->allMentorships);

        if ($this->mdSearch !== '') {
            $needle = strtolower($this->mdSearch);
            $items = $items->filter(fn ($m) => str_contains(strtolower($m['title']), $needle)
                || str_contains(strtolower($m['facility']), $needle)
            );
        }

        if ($this->mdStatus !== '') {
            $items = $items->filter(fn ($m) => $m['status'] === $this->mdStatus);
        }

        if ($this->mdProgram !== '') {
            $items = $items->filter(fn ($m) => strtolower($m['program_name'] ?? '') === strtolower($this->mdProgram));
        }

        $sortKey = in_array($this->mdSort, ['title', 'facility', 'module_pct', 'mentees', 'created_at'])
            ? $this->mdSort : 'created_at';

        $sorted = $this->mdDir === 'asc'
            ? $items->sortBy(fn ($m) => $m[$sortKey] ?? '')
            : $items->sortByDesc(fn ($m) => $m[$sortKey] ?? '');

        $all = $sorted->values();
        $total = $all->count();
        $page = max(1, $this->mentorshipsPage);
        $perPage = $this->mentorshipsPerPage;

        $this->mentorshipItems = $all->slice(($page - 1) * $perPage, $perPage)->values()->toArray();
        $this->mentorshipsTotal = $total;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // View data
    // ─────────────────────────────────────────────────────────────────────────

    protected function getViewData(): array
    {
        $programOptions = collect($this->allMentorships)
            ->pluck('program_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return [
            'mentorships' => new LengthAwarePaginator(
                $this->mentorshipItems,
                $this->mentorshipsTotal,
                $this->mentorshipsPerPage,
                $this->mentorshipsPage,
                ['path' => request()->url(), 'query' => request()->query()]
            ),
            'programOptions' => $programOptions,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getMyTrainingIds(int $userId): array
    {
        // Senior roles see all live (non-pilot) facility mentorships as a summary view
        if (auth()->user()->hasRole(self::SENIOR_ROLES)) {
            return Training::where('type', 'facility_mentorship')
                ->where('is_pilot', false)
                ->pluck('id')
                ->toArray();
        }

        $asLead = Training::where('mentor_id', $userId)
            ->where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->pluck('id');

        $asCoMentor = MentorshipCoMentor::where('user_id', $userId)
            ->where('status', 'accepted')
            ->pluck('training_id');

        // Filter co-mentor training IDs to also exclude pilots
        $asCoMentor = Training::whereIn('id', $asCoMentor)
            ->where('is_pilot', false)
            ->pluck('id');

        $trainingIds = $asLead->merge($asCoMentor)->unique()->values();

        $programIds = auth()->user()->allowedProgramIds();
        if ($programIds !== null) {
            $trainingIds = Training::whereIn('id', $trainingIds)
                ->whereIn('program_id', $programIds)
                ->pluck('id');
        }

        return $trainingIds->toArray();
    }

    private function emptyKpis(): array
    {
        return [
            'active_mentorships' => 0, 'active_classes' => 0,
            'completed_classes' => 0, 'total_mentees' => 0,
            'total_enrollments' => 0, 'total_modules' => 0,
            'completed_modules' => 0, 'attendance_rate' => 0,
            'avg_completion' => 0, 'recommendations' => 0,
            'module_completion_rate' => 0,
            'pending_video_reviews' => 0,
            'pending_mentor_approvals' => 0,
            'pending_drmh_approvals' => 0,
        ];
    }
}
