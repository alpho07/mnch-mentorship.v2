<?php

namespace App\Filament\Pages;

use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Services\MenteeNextActionResolver;
use Carbon\Carbon;
use Filament\Pages\Page;

class MenteeDashboard extends Page
{
    protected static string $view = 'filament.pages.mentee-dashboard';

    protected static ?string $slug = 'mentee-dashboard';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Mentee Dashboard';

    protected static ?int $navigationSort = 3;

    private const ALLOWED_ROLES = ['super_admin', 'admin', 'division', 'national', 'mentee'];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_MenteeDashboard');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_MenteeDashboard');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check()) {
            return null;
        }
        $user = auth()->user();
        if ($user->hasRole('mentee')) {
            // For mentees: count their active enrollments
            $count = \App\Models\ClassParticipant::where('user_id', $user->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->count();
        } else {
            // For senior roles: count all active mentees system-wide
            $count = \App\Models\ClassParticipant::whereIn('status', ['enrolled', 'active'])
                ->distinct('user_id')
                ->count('user_id');
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    // ── Public state (Livewire-reactive) ─────────────────────────────────────
    public array $profile = [];

    public array $globalStats = [];

    public array $enrollments = [];

    public array $recommendations = [];

    public array $suggestedModules = [];

    public array $activityFeed = [];

    public array $nextAction = [];

    public function mount(): void
    {
        $this->loadDashboard();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core loader
    // ─────────────────────────────────────────────────────────────────────────

    private function loadDashboard(): void
    {
        $user = auth()->user();

        // ── 1. Profile ───────────────────────────────────────────────────────
        $this->profile = [
            'id' => $user->id,
            'name' => $user->full_name ?? $user->name ?? 'Mentee',
            'email' => $user->email,
            'phone' => $user->phone,
            'initials' => $this->initials($user->full_name ?? $user->name ?? ''),
            'cadre' => $user->cadre?->name ?? '—',
            'department' => $user->department?->name ?? '—',
            'facility' => $user->facility?->name ?? '—',
            'county' => $user->facility?->subcounty?->county?->name ?? '—',
            'status' => $user->status ?? 'active',
            'joined' => $user->created_at ? Carbon::parse($user->created_at)->format('M Y') : '—',
        ];

        // ── 2. Load participants with all relations ───────────────────────────
        $participants = ClassParticipant::with([
            'mentorshipClass.training.facility',
            'mentorshipClass.training.program',
            'mentorshipClass.training.mentor',
            'mentorshipClass.classModules' => fn ($q) => $q->orderBy('order_sequence'),
            'mentorshipClass.classModules.programModule',
            'mentorshipClass.classModules.sessions' => fn ($q) => $q->orderBy('session_number'),
            'moduleProgress.classModule.programModule',
            'moduleProgress.preTestAttempt',
            'moduleProgress.postTestAttempt',
        ])
            ->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->orderByDesc('enrolled_at')
            ->get();

        if ($participants->isEmpty()) {
            $this->globalStats = $this->emptyGlobalStats();
            $this->enrollments = [];
            $this->activityFeed = [];
            $this->nextAction = [];

            return;
        }

        $classIds = $participants->pluck('mentorship_class_id');

        // ── 3. Confirmed attendance keyed by class_module_id ─────────────────
        $confirmedModuleIds = ClassAttendance::where('user_id', $user->id)
            ->whereIn('class_id', $classIds)
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->flip()
            ->toArray();

        // ── 4. Build per-enrollment data ─────────────────────────────────────
        $allModules = collect();
        $globalCompleted = 0;
        $globalInProgress = 0;
        $globalNotStarted = 0;
        $globalExempted = 0;
        $globalPendingAttend = 0;
        $globalAttended = 0;
        $completedClasses = 0;
        $activeClasses = 0;

        $this->enrollments = $participants->map(function (ClassParticipant $p) use (
            $confirmedModuleIds, &$globalCompleted, &$globalInProgress, &$globalNotStarted,
            &$globalExempted, &$globalPendingAttend, &$globalAttended,
            &$completedClasses, &$activeClasses, &$allModules
        ) {
            $class = $p->mentorshipClass;
            $training = $class?->training;

            // Progress keyed by class_module_id for O(1) lookup
            $progressMap = $p->moduleProgress->keyBy('class_module_id');

            $modules = ($class?->classModules ?? collect())->map(function (ClassModule $m) use (
                $confirmedModuleIds, $progressMap
            ) {
                $prog = $progressMap->get($m->id);
                $progStatus = $prog?->status ?? 'not_started';
                $confirmed = isset($confirmedModuleIds[$m->id]);

                $sessions = $m->sessions->map(fn ($s) => [
                    'number' => $s->session_number,
                    'title' => $s->title,
                    'duration' => $s->duration_minutes,
                    'date' => $s->scheduled_date ? Carbon::parse($s->scheduled_date)->format('d M Y') : null,
                    'time' => $s->scheduled_time ? Carbon::parse($s->scheduled_time)->format('H:i') : null,
                    'status' => $s->status ?? 'scheduled',
                ])->values()->toArray();

                return [
                    'id' => $m->id,
                    'name' => $m->programModule?->name ?? 'Unnamed Module',
                    'description' => $m->programModule?->description ?? '',
                    'module_status' => $m->status, // class_module status
                    'progress_status' => $progStatus, // mentee progress
                    'confirmed' => $confirmed,
                    'attendance_open' => (bool) $m->attendance_link_active && $m->status === 'in_progress',
                    'attendance_token' => $m->attendance_token,
                    'started_at' => $m->started_at ? Carbon::parse($m->started_at)->format('d M Y') : null,
                    'completed_at' => $prog?->completed_at ? Carbon::parse($prog->completed_at)->format('d M Y') : null,
                    'exempted' => $progStatus === 'exempted',
                    'prev_class' => (bool) $prog?->completed_in_previous_class,
                    'recommendation' => $prog?->mentor_recommendation,
                    'rec_at' => $prog?->recommendation_written_at ? Carbon::parse($prog->recommendation_written_at)->format('d M Y') : null,
                    'video_review_status' => $prog?->video_review_status,
                    'video_review_notes' => $prog?->video_review_notes,
                    'video_reviewed_at' => $prog?->video_reviewed_at ? Carbon::parse($prog->video_reviewed_at)->format('d M Y') : null,
                    'assessment_score' => $prog?->assessment_score,
                    'pre_test_score'   => $prog?->preTestAttempt?->score,
                    'post_test_score'  => $prog?->postTestAttempt?->score,
                    'sessions' => $sessions,
                    'order' => $m->order_sequence ?? 0,
                ];
            })->sortBy('order')->values()->toArray();

            // Per-class aggregates
            $mods = collect($modules);
            $done = $mods->whereIn('progress_status', ['completed', 'exempted'])->count();
            $inProg = $mods->where('progress_status', 'in_progress')->count();
            $notStart = $mods->where('progress_status', 'not_started')->count();
            $exempted = $mods->where('exempted', true)->count();
            $pending = $mods->where('attendance_open', true)->where('confirmed', false)->count();
            $attended = $mods->where('confirmed', true)->count();
            $total = $mods->count();
            $pct = $total > 0 ? round(($done / $total) * 100) : 0;
            $hasRecs = $mods->filter(fn ($m) => ! empty($m['recommendation']))->count();

            // Accumulate globals
            $globalCompleted += $done;
            $globalInProgress += $inProg;
            $globalNotStarted += $notStart;
            $globalExempted += $exempted;
            $globalPendingAttend += $pending;
            $globalAttended += $attended;
            $p->status === 'completed' ? $completedClasses++ : null;
            in_array($p->status, ['enrolled', 'active']) ? $activeClasses++ : null;

            $allModules = $allModules->merge($modules);

            $programName = strtolower($training?->program?->name ?? '');
            $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');

            return [
                'participant_id' => $p->id,
                'class_id' => $class?->id,
                'class_name' => $class?->name ?? 'Unnamed Class',
                'class_status' => $class?->status ?? 'draft',
                'p_status' => $p->status,
                'training_id' => $training?->id,
                'training_name' => $training?->title ?? '—',
                'program_name' => $training?->program?->name ?? '—',
                'is_emonc' => $isEmonc,
                'facility_name' => $training?->facility?->name ?? '—',
                'mentor_name' => $training?->mentor?->full_name ?? $training?->mentor?->name ?? '—',
                'enrolled_at' => $p->enrolled_at ? Carbon::parse($p->enrolled_at)->format('d M Y') : '—',
                'completed_at' => $p->completed_at ? Carbon::parse($p->completed_at)->format('d M Y') : null,
                'start_date' => $class?->start_date ? Carbon::parse($class->start_date)->format('d M Y') : null,
                'end_date' => $class?->end_date ? Carbon::parse($class->end_date)->format('d M Y') : null,
                'total_modules' => $total,
                'completed_modules' => $done,
                'in_progress_modules' => $inProg,
                'not_started_modules' => $notStart,
                'exempted_modules' => $exempted,
                'pending_attendance' => $pending,
                'attended_modules' => $attended,
                'progress_pct' => $pct,
                'recommendations' => $hasRecs,
                'is_certified'    => $p->isCertified(),
                'mentor_approved' => (bool) $p->mentor_approved_at,
                'cert_url'        => $p->isCertified() ? route('reports.class.certificate', [$class?->id, $p->id]) : null,
                'modules' => $modules,
            ];
        })->toArray();

        // ── 5. Global stats ──────────────────────────────────────────────────
        $totalModules = $allModules->count();
        $attendanceRate = $totalModules > 0 ? round(($globalAttended / $totalModules) * 100) : 0;
        $completionRate = $totalModules > 0 ? round(($globalCompleted / $totalModules) * 100) : 0;
        $totalClasses = $participants->count();

        $this->globalStats = [
            'total_classes' => $totalClasses,
            'active_classes' => $activeClasses,
            'completed_classes' => $completedClasses,
            'total_modules' => $totalModules,
            'completed_modules' => $globalCompleted,
            'in_progress_modules' => $globalInProgress,
            'not_started_modules' => $globalNotStarted,
            'exempted_modules' => $globalExempted,
            'pending_attendance' => $globalPendingAttend,
            'attended_modules' => $globalAttended,
            'attendance_rate' => $attendanceRate,
            'completion_rate' => $completionRate,
            // Executive-grade derived metrics
            'presence_status' => $this->presenceStatus($attendanceRate),
            'learning_velocity' => $this->learningVelocity($participants),
            'streak_days' => $this->currentStreak($confirmedModuleIds, $classIds->toArray()),
        ];

        // ── 6. Mentor recommendations + failed video-review feedback ─────────
        $mentorRecommendations = $allModules
            ->filter(fn ($m) => ! empty($m['recommendation']))
            ->map(fn ($m) => [
                'name' => $m['name'],
                'text' => $m['recommendation'],
                'at' => $m['rec_at'],
                'type' => 'recommendation',
            ]);

        $videoFeedback = $allModules
            ->filter(fn ($m) => ($m['video_review_status'] ?? null) === 'failed' && ! empty($m['video_review_notes']))
            ->map(fn ($m) => [
                'name' => $m['name'],
                'text' => $m['video_review_notes'],
                'at' => $m['video_reviewed_at'],
                'type' => 'video_review',
            ]);

        $this->recommendations = $mentorRecommendations
            ->merge($videoFeedback)
            ->sortByDesc('at')
            ->take(5)
            ->values()
            ->toArray();

        // ── 7. Suggested (open attendance, not yet confirmed) ────────────────
        $this->suggestedModules = $allModules
            ->filter(fn ($m) => $m['attendance_open'] && ! $m['confirmed'])
            ->take(3)
            ->values()
            ->toArray();

        // ── 8. Activity feed ─────────────────────────────────────────────────
        $this->activityFeed = $this->buildActivityFeed($participants, $confirmedModuleIds);

        // ── 9. Next-best-action ──────────────────────────────────────────────
        $this->nextAction = app(MenteeNextActionResolver::class)->resolve($user);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $i = strtoupper(substr($parts[0] ?? 'M', 0, 1));
        if (count($parts) > 1) {
            $i .= strtoupper(substr(end($parts), 0, 1));
        }

        return $i ?: 'M';
    }

    private function presenceStatus(int $rate): array
    {
        if ($rate >= 80) {
            return ['label' => 'Excellent', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'];
        }
        if ($rate >= 60) {
            return ['label' => 'Good', 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'];
        }
        if ($rate >= 40) {
            return ['label' => 'Fair', 'color' => '#ea580c', 'bg' => '#fff7ed', 'border' => '#fed7aa'];
        }

        return ['label' => 'Needs Improvement', 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca'];
    }

    private function learningVelocity($participants): string
    {
        // modules completed in last 30 days
        $recent = MenteeModuleProgress::whereIn(
            'class_participant_id',
            $participants->pluck('id')
        )
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        if ($recent >= 5) {
            return 'Fast';
        }
        if ($recent >= 2) {
            return 'Steady';
        }
        if ($recent >= 1) {
            return 'Slow';
        }

        return 'Inactive';
    }

    private function currentStreak(array $confirmedModuleIds, array $classIds): int
    {
        // Consecutive days with attendance activity
        $dates = ClassAttendance::where('user_id', auth()->id())
            ->whereIn('class_id', $classIds)
            ->whereNotNull('marked_at')
            ->orderByDesc('marked_at')
            ->pluck('marked_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 1;
        for ($i = 0; $i < $dates->count() - 1; $i++) {
            $diff = Carbon::parse($dates[$i])->diffInDays(Carbon::parse($dates[$i + 1]));
            if ($diff === 1) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function buildActivityFeed($participants, array $confirmedModuleIds): array
    {
        $feed = [];

        // Recent attendance confirmations
        $recent = ClassAttendance::where('user_id', auth()->id())
            ->whereIn('class_id', $participants->pluck('mentorship_class_id'))
            ->with('classModule.programModule')
            ->whereNotNull('class_module_id')
            ->orderByDesc('marked_at')
            ->take(10)
            ->get();

        foreach ($recent as $att) {
            $feed[] = [
                'type' => 'attendance',
                'icon' => '✅',
                'color' => 'green',
                'text' => 'Confirmed attendance for <strong>'.($att->classModule?->programModule?->name ?? 'a module').'</strong>',
                'time' => $att->marked_at ? Carbon::parse($att->marked_at)->diffForHumans() : '—',
                'ts' => $att->marked_at,
            ];
        }

        // Recent completions
        $completions = MenteeModuleProgress::whereIn('class_participant_id', $participants->pluck('id'))
            ->where('status', 'completed')
            ->with('classModule.programModule')
            ->orderByDesc('completed_at')
            ->take(5)
            ->get();

        foreach ($completions as $prog) {
            $feed[] = [
                'type' => 'completion',
                'icon' => '🏆',
                'color' => 'blue',
                'text' => 'Completed module <strong>'.($prog->classModule?->programModule?->name ?? 'a module').'</strong>',
                'time' => $prog->completed_at ? Carbon::parse($prog->completed_at)->diffForHumans() : '—',
                'ts' => $prog->completed_at,
            ];
        }

        // Recommendations received
        $recs = MenteeModuleProgress::whereIn('class_participant_id', $participants->pluck('id'))
            ->whereNotNull('mentor_recommendation')
            ->with('classModule.programModule')
            ->orderByDesc('recommendation_written_at')
            ->take(3)
            ->get();

        foreach ($recs as $rec) {
            $feed[] = [
                'type' => 'recommendation',
                'icon' => '📋',
                'color' => 'purple',
                'text' => 'Received mentor feedback for <strong>'.($rec->classModule?->programModule?->name ?? 'a module').'</strong>',
                'time' => $rec->recommendation_written_at ? Carbon::parse($rec->recommendation_written_at)->diffForHumans() : '—',
                'ts' => $rec->recommendation_written_at,
            ];
        }

        // Class enrollments
        foreach ($participants as $p) {
            $feed[] = [
                'type' => 'enrollment',
                'icon' => '📚',
                'color' => 'indigo',
                'text' => 'Enrolled in <strong>'.($p->mentorshipClass?->name ?? 'a class').'</strong>',
                'time' => $p->enrolled_at ? Carbon::parse($p->enrolled_at)->diffForHumans() : '—',
                'ts' => $p->enrolled_at,
            ];
        }

        // Sort by timestamp desc, return top 12
        usort($feed, fn ($a, $b) => strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')));

        return array_slice($feed, 0, 12);
    }

    private function emptyGlobalStats(): array
    {
        return [
            'total_classes' => 0, 'active_classes' => 0, 'completed_classes' => 0,
            'total_modules' => 0, 'completed_modules' => 0, 'in_progress_modules' => 0,
            'not_started_modules' => 0, 'exempted_modules' => 0, 'pending_attendance' => 0,
            'attended_modules' => 0, 'attendance_rate' => 0, 'completion_rate' => 0,
            'presence_status' => ['label' => 'No Data', 'color' => '#64748b', 'bg' => '#f8fafc', 'border' => '#e2e8f0'],
            'learning_velocity' => 'Inactive',
            'streak_days' => 0,
        ];
    }

    public function clearPendingEnrollment(): void
    {
        session()->forget('enrollment_intent');
    }
}
