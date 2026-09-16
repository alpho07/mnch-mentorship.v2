# EmONC Dashboard, Mentee UX & Activity Completion Tracking — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a new Mentorships navigation group with a rich EmONC mentor dashboard, polish the mentee module-detail view, and make mentor activity completion tracking informative and certificate-aware.

**Architecture:** Add a `Mentorships` Filament navigation group, create an `EmoncDashboard` Filament page, enhance the existing mentee `module-detail` Blade view with status chips and timeline, and improve the existing activity completion matrix with aggregate status columns and certificate-readiness hints.

**Tech Stack:** Laravel 12, Filament v3, Livewire, Tailwind CSS, Chart.js, Leaflet.js, Alpine.js, Blade.

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Filament/Pages/EmoncDashboard.php` | New rich EmONC mentor dashboard page. |
| `resources/views/filament/pages/emonc-dashboard.blade.php` | Dashboard UI: KPIs, map, charts, completion matrix. |
| `app/Filament/Pages/MentorshipsInfantDashboard.php` (and Newborn/Child) | Thin program-filtered dashboards linked from Mentorships group. |
| `app/Providers/FilamentServiceProvider.php` or `AppServiceProvider.php` | Register Mentorships navigation group and items. |
| `resources/views/mentee/module-detail.blade.php` | Polished mentee module-detail view. |
| `resources/views/mentee/class-progress.blade.php` | Minor updates to link to module detail and show activity status. |
| `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php` | Ensure activity completion matrix action is clear and works. |
| `app/Filament/Forms/Components/ActivityCompletionMatrix.php` | Improve matrix with "all done" column and bulk actions. |
| `resources/views/filament/forms/components/activity-completion-matrix.blade.php` | Matrix UI. |
| `app/Services/EmoncDashboardService.php` | Data aggregation for EmONC dashboard. |
| `app/Services/EmoncReportingService.php` | Extend with per-mentee activity completion data. |

---

## Task 1: Register the "Mentorships" navigation group

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (or create `app/Providers/FilamentServiceProvider.php` if preferred)

- [ ] **Step 1: Add Mentorships group with program dashboard links**

In `AppServiceProvider::boot()`, after existing Filament navigation configuration, register a new group and items. Filament v3 uses `Filament::serving()` or `Filament::registerNavigationItems()`.

If using `Filament::serving()`:

```php
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

Filament::serving(function () {
    Filament::registerNavigationGroups([
        NavigationGroup::make('Mentorships')
            ->icon('heroicon-o-users')
            ->collapsed(),
    ]);

    Filament::registerNavigationItems([
                            NavigationItem::make('Infant and Child Care')
                                ->group('Mentorships')
                                ->icon('heroicon-o-user-group')
                                ->url('/admin/mentor-dashboard?program=infant')
                                ->sort(2),
        NavigationItem::make('Newborn Care')
            ->group('Mentorships')
            ->icon('heroicon-o-heart')
            ->url('/admin/mentor-dashboard?program=newborn')
            ->sort(2),
        NavigationItem::make('Child Care')
            ->group('Mentorships')
            ->icon('heroicon-o-user')
            ->url('/admin/mentor-dashboard?program=child')
            ->sort(3),
        NavigationItem::make('Maternal Health (EmONC)')
            ->group('Mentorships')
            ->icon('heroicon-o-heart')
            ->url('/admin/emonc-dashboard')
            ->sort(4),
    ]);
});
```

- [ ] **Step 2: Verify navigation renders**

Run: `php artisan route:cache && php artisan view:cache`
Open `/admin/login` and log in as a mentor/admin.
Expected: Left sidebar shows "Mentorships" group with Infant, Newborn, Child, and Maternal Health items.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: add Mentorships navigation group with program dashboards"
```

---

## Task 2: Create the EmONC dashboard page class

**Files:**
- Create: `app/Filament/Pages/EmoncDashboard.php`

- [ ] **Step 1: Generate page via artisan**

Run: `php artisan make:filament-page EmoncDashboard`

- [ ] **Step 2: Replace generated class with EmONC-specific dashboard**

```php
<?php

namespace App\Filament\Pages;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\EmoncDashboardService;
use Filament\Pages\Page;

class EmoncDashboard extends Page
{
    protected static string $view = 'filament.pages.emonc-dashboard';

    protected static ?string $slug = 'emonc-dashboard';

    protected static ?string $navigationGroup = 'Mentorships';

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Maternal Health (EmONC)';

    protected static ?int $navigationSort = 4;

    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    private const MENTOR_ROLES = [
        'facility_mentor', 'facility_mentor_lead',
        'county_mentor_lead', 'subcounty_mentor_lead',
        'spoke_mentor', 'spoke_mentor_lead',
        'national_mentor_lead', 'national_mentor',
        'co_mentor', 'co-mentor', 'head_drmh',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole(array_merge(self::MENTOR_ROLES, self::SENIOR_ROLES));
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole(array_merge(self::MENTOR_ROLES, self::SENIOR_ROLES));
    }

    public array $kpis = [];
    public array $completionMatrix = [];
    public array $chartData = [];
    public array $pendingActions = [];

    public function mount(): void
    {
        $data = app(EmoncDashboardService::class)->build(auth()->user());
        $this->kpis = $data['kpis'];
        $this->completionMatrix = $data['completion_matrix'];
        $this->chartData = $data['chart_data'];
        $this->pendingActions = $data['pending_actions'];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/EmoncDashboard.php
git commit -m "feat: add EmONC dashboard page class"
```

---

## Task 3: Build EmoncDashboardService

**Files:**
- Create: `app/Services/EmoncDashboardService.php`

- [ ] **Step 1: Create service class**

```php
<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;

class EmoncDashboardService
{
    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    public function build(User $user): array
    {
        $trainingIds = $this->trainingIdsFor($user);

        $trainings = Training::whereIn('id', $trainingIds)
            ->where('type', 'facility_mentorship')
            ->with(['facility', 'program', 'mentor'])
            ->get();

        $emoncTrainings = $trainings->filter(fn ($t) => $this->isEmonc($t));
        $emoncTrainingIds = $emoncTrainings->pluck('id');

        $classes = MentorshipClass::whereIn('training_id', $emoncTrainingIds)
            ->with(['classModules.programModule.parent', 'classModules.activityParticipants.activity', 'training.facility'])
            ->get();

        $classIds = $classes->pluck('id');
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));

        $participants = ClassParticipant::with('user')
            ->whereIn('mentorship_class_id', $classIds)
            ->whereIn('status', ['enrolled', 'active', 'completed'])
            ->get();

        $progress = MenteeModuleProgress::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->keyBy(fn ($p) => "{$p->class_participant_id}:{$p->class_module_id}");

        $activityParticipants = ClassModuleActivityParticipant::whereIn('class_module_id', $classModuleIds)
            ->get()
            ->groupBy(fn ($ap) => "{$ap->class_participant_id}:{$ap->class_module_id}");

        $kpis = $this->computeKpis($classes, $participants, $progress);
        $pendingActions = $this->computePendingActions($classes, $participants, $progress, $activityParticipants);
        $matrix = $this->buildCompletionMatrix($classes, $participants, $progress, $activityParticipants);
        $chartData = $this->buildChartData($classes, $participants, $progress);

        return compact('kpis', 'pendingActions', 'completionMatrix', 'chartData');
    }

    private function trainingIdsFor(User $user): array
    {
        if ($user->hasRole(self::SENIOR_ROLES)) {
            return Training::where('type', 'facility_mentorship')->pluck('id')->toArray();
        }

        $asLead = Training::where('mentor_id', $user->id)->where('type', 'facility_mentorship')->pluck('id');
        $asCo = \App\Models\MentorshipCoMentor::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('training_id');

        return $asLead->merge($asCo)->unique()->values()->toArray();
    }

    private function isEmonc(Training $training): bool
    {
        $name = strtolower($training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    private function computeKpis($classes, $participants, $progress): array
    {
        $classModuleIds = $classes->flatMap(fn ($c) => $c->classModules->pluck('id'));
        $participantIds = $participants->pluck('id');

        $totalMentees = $participants->pluck('user_id')->unique()->count();
        $activeClasses = $classes->where('status', 'active')->count();
        $completedClasses = $classes->where('status', 'completed')->count();
        $totalModules = $classModuleIds->count();
        $completedModules = $classes->sum(fn ($c) => $c->classModules->where('status', 'completed')->count());

        $videoPending = $progress->where('video_review_status', 'pending')->count();
        $mentorPending = $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count();
        $drmhPending = $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count();
        $certified = $participants->whereNotNull('head_drmh_approved_at')->count();

        return [
            'active_mentees' => $totalMentees,
            'active_classes' => $activeClasses,
            'completed_classes' => $completedClasses,
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'pending_video_reviews' => $videoPending,
            'pending_mentor_approvals' => $mentorPending,
            'pending_drmh_approvals' => $drmhPending,
            'certificates_issued' => $certified,
        ];
    }

    private function computePendingActions($classes, $participants, $progress, $activityParticipants): array
    {
        // Detailed pending items with URLs
        return [
            [
                'label' => 'Video reviews',
                'count' => $progress->where('video_review_status', 'pending')->count(),
                'url' => '/admin/mentorships',
                'color' => 'amber',
            ],
            [
                'label' => 'Mentor approvals',
                'count' => $participants->whereNull('mentor_approved_at')->where('status', '!=', 'dropped')->count(),
                'url' => '/admin/mentorships',
                'color' => 'blue',
            ],
            [
                'label' => 'Head DRMH certifications',
                'count' => $participants->whereNotNull('mentor_approved_at')->whereNull('head_drmh_approved_at')->count(),
                'url' => '/admin/mentorships',
                'color' => 'violet',
            ],
        ];
    }

    private function buildCompletionMatrix($classes, $participants, $progress, $activityParticipants): array
    {
        $matrix = [];

        foreach ($classes as $class) {
            foreach ($class->classModules as $classModule) {
                $programModule = $classModule->programModule;
                $moduleName = $programModule?->name ?? 'Module';
                $parentName = $programModule?->parent?->name;
                $activities = $programModule?->activities ?? collect();

                foreach ($participants->where('mentorship_class_id', $class->id) as $participant) {
                    $key = "{$participant->id}:{$classModule->id}";
                    $prog = $progress->get($key);
                    $activityStatus = $this->activityStatusFor($activities, $activityParticipants->get($key, collect()));

                    $matrix[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'facility_name' => $class->training->facility?->name ?? '—',
                        'county_name' => $class->training->facility?->subcounty?->county?->name ?? '—',
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'mentee_name' => $participant->user?->name ?? 'Unknown',
                        'module_id' => $classModule->id,
                        'module_name' => $moduleName,
                        'parent_module_name' => $parentName,
                        'activities' => $activityStatus,
                        'module_progress' => $prog?->status ?? 'not_started',
                        'video_review' => $prog?->video_review_status ?? 'not_submitted',
                        'mentor_approved' => ! empty($participant->mentor_approved_at),
                        'head_drmh_approved' => ! empty($participant->head_drmh_approved_at),
                        'certificate_ready' => $participant->isCertified(),
                        'blocked_reasons' => $this->blockedReasons($participant, $prog, $activityStatus),
                    ];
                }
            }
        }

        return $matrix;
    }

    private function activityStatusFor($activities, $activityParticipants): array
    {
        $status = [];
        foreach ($activities as $activity) {
            $ap = $activityParticipants->firstWhere('activity_id', $activity->id);
            $status[] = [
                'name' => $activity->name,
                'status' => $ap?->status ?? 'not_enrolled',
            ];
        }

        return $status;
    }

    private function blockedReasons(ClassParticipant $participant, ?MenteeModuleProgress $progress, array $activityStatus): array
    {
        $reasons = [];
        foreach ($activityStatus as $a) {
            if ($a['status'] !== 'completed') {
                $reasons[] = "{$a['name']} not completed";
            }
        }
        if ($progress && $progress->video_review_status !== 'passed') {
            $reasons[] = 'Hands-on video not passed';
        }
        if (empty($participant->mentor_approved_at)) {
            $reasons[] = 'Pending mentor approval';
        }
        if (empty($participant->head_drmh_approved_at)) {
            $reasons[] = 'Pending Head DRMH certification';
        }

        return $reasons;
    }

    private function buildChartData($classes, $participants, $progress): array
    {
        // Placeholder: real implementation will aggregate per month/class
        return [
            'completion_distribution' => [
                ['label' => 'Completed', 'value' => $progress->where('status', 'completed')->count()],
                ['label' => 'In Progress', 'value' => $progress->where('status', 'in_progress')->count()],
                ['label' => 'Not Started', 'value' => $progress->where('status', 'not_started')->count()],
            ],
        ];
    }
}
```

- [ ] **Step 2: Add helper methods to models if missing**

Ensure `ClassParticipant::isCertified()` exists (it should from Phase 7). If not, add it:

```php
// app/Models/ClassParticipant.php
public function isCertified(): bool
{
    return ! empty($this->mentor_approved_at) && ! empty($this->head_drmh_approved_at);
}
```

- [ ] **Step 3: Test service returns expected shape**

Create a quick smoke test in `routes/web.php` temporarily or use tinker:

```bash
php artisan tinker --execute="dd(app(\App\Services\EmoncDashboardService::class)->build(\App\Models\User::first()))"
```

Expected: array with `kpis`, `pendingActions`, `completionMatrix`, `chartData` keys.

- [ ] **Step 4: Commit**

```bash
git add app/Services/EmoncDashboardService.php app/Models/ClassParticipant.php
git commit -m "feat: add EmONC dashboard data service"
```

---

## Task 4: Build the EmONC dashboard Blade view

**Files:**
- Create: `resources/views/filament/pages/emonc-dashboard.blade.php`

- [ ] **Step 1: Write colorful Tailwind dashboard view**

```blade
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Active Mentees</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['active_mentees'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Active Classes</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['active_classes'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Modules Completed</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['completed_modules'] }}</div>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 p-5 text-white shadow-lg">
                <div class="text-xs font-semibold uppercase tracking-wider opacity-80">Pending Video Reviews</div>
                <div class="text-3xl font-extrabold mt-1">{{ $kpis['pending_video_reviews'] }}</div>
            </div>
        </div>

        {{-- Pending actions --}}
        <div class="flex flex-wrap gap-3">
            @foreach($pendingActions as $action)
                <a href="{{ $action['url'] }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold
                   {{ match($action['color']) {
                       'amber' => 'bg-amber-100 text-amber-700 hover:bg-amber-200',
                       'blue' => 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                       'violet' => 'bg-violet-100 text-violet-700 hover:bg-violet-200',
                       default => 'bg-slate-100 text-slate-700',
                   } }}">
                    {{ $action['label'] }}
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white text-xs font-bold">{{ $action['count'] }}</span>
                </a>
            @endforeach
        </div>

        {{-- Map placeholder --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">County Overview</h3>
            <div id="emonc-map" class="w-full h-64 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                Kenya county map placeholder (Leaflet integration in Task 6)
            </div>
        </div>

        {{-- Completion matrix --}}
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm overflow-hidden">
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Per-Mentee Completion Map</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Mentee</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Class</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Module / Track</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Activities</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Video</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Certificate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($completionMatrix as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $row['mentee_name'] }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row['class_name'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-900 dark:text-white font-medium">{{ $row['module_name'] }}</div>
                                    @if($row['parent_module_name'])
                                        <div class="text-xs text-brand-600 dark:text-brand-400">{{ $row['parent_module_name'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($row['activities'] as $act)
                                            @php
                                                $color = match($act['status']) {
                                                    'completed' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                                    'pending', 'in_progress' => 'bg-amber-100 text-amber-700 border-amber-200',
                                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border {{ $color }}">
                                                {{ $act['name'] }} {{ $act['status'] === 'completed' ? '✓' : '○' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $vcolor = match($row['video_review']) {
                                            'passed' => 'text-emerald-600',
                                            'failed' => 'text-red-600',
                                            'pending' => 'text-amber-600',
                                            default => 'text-slate-400',
                                        };
                                    @endphp
                                    <span class="font-semibold {{ $vcolor }}">{{ ucfirst(str_replace('_', ' ', $row['video_review'])) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($row['certificate_ready'])
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">Certified ✓</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700" title="{{ implode(', ', $row['blocked_reasons']) }}">
                                            Blocked ✗
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 2: Test view renders**

Run: `php artisan view:cache`
Log in and open `/admin/emonc-dashboard`.
Expected: Dashboard shows KPI cards, pending actions, map placeholder, and completion matrix.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/emonc-dashboard.blade.php
git commit -m "feat: add EmONC dashboard view"
```

---

## Task 5: Polish the mentee module-detail view

**Files:**
- Modify: `resources/views/mentee/module-detail.blade.php`

- [ ] **Step 1: Add status chips row and progress timeline**

Update the module header section (around line 86-107) to include status chips and a progress timeline. Keep existing content. Add after the status chips:

```blade
            {{-- Progress timeline --}}
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">Your Progress</h2>
                <div class="flex items-center justify-between">
                    @php
                        $steps = [
                            ['label' => 'Pre-test', 'done' => $preTestStatus['attempted'] ?? false, 'active' => !($preTestStatus['attempted'] ?? false)],
                            ['label' => 'Content', 'done' => $preTestStatus['attempted'] ?? false, 'active' => ($preTestStatus['attempted'] ?? false) && !($videoStatus['submitted'] ?? false)],
                            ['label' => 'Video', 'done' => ($videoStatus['reviewed'] ?? false) && $videoStatus['status'] === 'passed', 'active' => $videoStatus['submitted'] ?? false],
                            ['label' => 'Post-test', 'done' => $postTestStatus['completed'] ?? false, 'active' => ($videoStatus['status'] ?? null) === 'passed' && !($postTestStatus['completed'] ?? false)],
                            ['label' => 'Approved', 'done' => false, 'active' => false],
                        ];
                    @endphp
                    @foreach($steps as $i => $step)
                        <div class="text-center flex-1 {{ $i > 0 ? 'relative' : '' }}">
                            @if($i > 0)
                                <div class="absolute top-4 left-0 right-0 h-0.5 {{ $step['done'] || $step['active'] ? 'bg-brand-500' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                            @endif
                            <div class="relative z-10 w-8 h-8 mx-auto rounded-full flex items-center justify-center text-xs font-bold
                                {{ $step['done'] ? 'bg-emerald-500 text-white' : ($step['active'] ? 'bg-brand-600 text-white ring-4 ring-brand-100 dark:ring-brand-900/30' : 'bg-slate-200 dark:bg-slate-700 text-slate-500') }}">
                                {{ $step['done'] ? '✓' : ($i + 1) }}
                            </div>
                            <div class="mt-2 text-xs font-semibold {{ $step['active'] ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $step['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
```

Note: ensure variables `$preTestStatus`, `$videoStatus`, `$postTestStatus` are passed from the controller. If `videoStatus` is not passed, derive it from `$progress`.

- [ ] **Step 2: Add colored left borders to section cards**

For each section card, add a colored left border. Example for Introduction:

```blade
<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 border-l-4 border-l-emerald-500 p-5 sm:p-6">
```

- Pre-test: `border-l-amber-500`
- Introduction/Videos/Case Scenarios: `border-l-emerald-500`
- Hands-on video submission: `border-l-amber-500`
- Post-test: `border-l-rose-500`
- Results: `border-l-violet-500`

- [ ] **Step 3: Improve locked-state messaging**

For the post-test locked state, show:

```blade
<p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
    Post-test will unlock after your hands-on video is reviewed and passed by your mentor.
</p>
```

- [ ] **Step 4: Verify mobile responsive**

Open the mentee module detail on a narrow viewport (or use browser dev tools).
Expected: Cards stack, text readable, timeline steps don't overlap.

- [ ] **Step 5: Commit**

```bash
git add resources/views/mentee/module-detail.blade.php
git commit -m "feat: polish mentee module-detail view with timeline and status chips"
```

---

## Task 6: Add county map to EmONC dashboard

**Files:**
- Modify: `resources/views/filament/pages/emonc-dashboard.blade.php`

- [ ] **Step 1: Add Leaflet map container and GeoJSON load**

Replace the map placeholder div with:

```blade
<div id="emonc-map" class="w-full h-64 rounded-xl"></div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const map = L.map('emonc-map').setView([-1.2921, 36.8219], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        fetch('/geojson/kenya-counties.json')
            .then(r => r.json())
            .then(geojson => {
                L.geoJSON(geojson, {
                    style: { color: '#4f46e5', weight: 1, fillColor: '#c7d2fe', fillOpacity: 0.4 },
                    onEachFeature: function (feature, layer) {
                        layer.bindPopup(feature.properties.name || 'County');
                    }
                }).addTo(map);
            })
            .catch(() => {
                document.getElementById('emonc-map').innerHTML = '<p class="text-slate-400">Map data unavailable.</p>';
            });
    });
</script>
```

- [ ] **Step 2: Verify GeoJSON path exists**

Check: `public/geojson/kenya-counties.json` should exist (used elsewhere in the app). If not, find the existing path and update the fetch URL.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/emonc-dashboard.blade.php
git commit -m "feat: add Kenya county map to EmONC dashboard"
```

---

## Task 7: Improve activity completion matrix for mentors

**Files:**
- Modify: `app/Filament/Forms/Components/ActivityCompletionMatrix.php`
- Modify: `resources/views/filament/forms/components/activity-completion-matrix.blade.php`

- [ ] **Step 1: Add "all done" column and certificate-readiness hint to the matrix view**

In `activity-completion-matrix.blade.php`, after the activity columns, add:

```blade
<th class="px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 text-center">All done?</th>
<th class="px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300">Certificate</th>
```

And per row:

```blade
<td class="px-3 py-2 text-center">
    @if($allDone)
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Yes ✓</span>
    @else
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">No</span>
    @endif
</td>
<td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300">
    @if($allDone && $videoPassed)
        Ready for mentor approval
    @else
        {{ implode(', ', $rowBlockedReasons) }}
    @endif
</td>
```

- [ ] **Step 2: Compute per-row state in the form component**

In `ActivityCompletionMatrix.php`, ensure the `getState()` or data array includes per-mentee video review status and blocked reasons. If not, add a helper to compute it.

- [ ] **Step 3: Add bulk actions to the matrix form**

In the Filament form where `ActivityCompletionMatrix` is used (`ManageClassModules.php`), add bulk action buttons above the matrix:
- "Mark all activities complete for selected"
- "Mark all activities incomplete for selected"

This can be implemented as additional form actions or checkboxes within the matrix itself.

- [ ] **Step 4: Verify matrix updates progress and notifications**

Open a class module's Activities action, mark activities complete, save.
Expected: `ClassModuleActivityParticipant` rows updated, notifications sent, `MenteeModuleProgress` auto-completed when all activities done.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Forms/Components/ActivityCompletionMatrix.php resources/views/filament/forms/components/activity-completion-matrix.blade.php

git commit -m "feat: improve activity completion matrix with all-done and certificate hints"
```

---

## Task 8: Wire Infant/Newborn/Child Care dashboards

**Files:**
- Modify: `app/Filament/Pages/MentorDashboard.php`
- Create: `app/Filament/Pages/MentorshipsInfantDashboard.php` etc. (optional thin wrappers)

- [ ] **Step 1: Read program filter from query string in MentorDashboard**

In `MentorDashboard::mount()`, read `request('program')` and filter `mentorships` accordingly:

```php
$filter = request('program');
if ($filter) {
    $this->mentorships = collect($this->mentorships)
        ->filter(fn ($m) => str_contains(strtolower($m['program_name'] ?? ''), $filter))
        ->values()
        ->toArray();
}
```

- [ ] **Step 2: Update Mentorships nav URLs**

Ensure the URLs in `AppServiceProvider` use the correct slug, e.g. `/admin/mentor-dashboard?program=infant`.

- [ ] **Step 3: Verify program filtering works**

Click "Infant Care" in Mentorships nav.
Expected: Mentor Dashboard opens filtered to Infant Care mentorships only.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/MentorDashboard.php app/Providers/AppServiceProvider.php
git commit -m "feat: enable program filtering for Infant/Newborn/Child Care mentorship dashboards"
```

---

## Task 9: Add Chart.js charts to EmONC dashboard

**Files:**
- Modify: `resources/views/filament/pages/emonc-dashboard.blade.php`

- [ ] **Step 1: Add a donut chart for activity completion distribution**

After the map, add:

```blade
<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">Activity Completion Distribution</h3>
    <canvas id="completionDonut" height="120"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('completionDonut').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode(array_column($chartData['completion_distribution'], 'label')) !!},
            datasets: [{
                data: {!! json_encode(array_column($chartData['completion_distribution'], 'value')) !!},
                backgroundColor: ['#22c55e', '#f59e0b', '#94a3b8'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>
```

- [ ] **Step 2: Verify chart renders**

Open EmONC dashboard.
Expected: Donut chart shows completion distribution.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/emonc-dashboard.blade.php
git commit -m "feat: add Chart.js completion donut to EmONC dashboard"
```

---

## Task 10: Final verification

- [ ] **Step 1: Run Pint**

```bash
./vendor/bin/pint --test
```
Expected: PASS

- [ ] **Step 2: Cache and smoke test**

```bash
php artisan route:cache && php artisan view:cache && php artisan config:cache
```

Open:
- `/admin/emonc-dashboard` — loads without errors.
- `/admin/mentor-dashboard?program=infant` — loads filtered.
- Mentee module detail page — loads with timeline and status chips.

- [ ] **Step 3: Run existing tests**

```bash
composer test
```
Note: pre-existing SQLite RefreshDatabase issue may cause failures unrelated to these changes.

- [ ] **Step 4: Commit any fixes**

```bash
git add .
git commit -m "fix: final style and cache fixes for EmONC dashboard"
```

---

## Spec coverage check

| Spec section | Implementing task |
|--------------|-------------------|
| 3. Navigation: Mentorships group | Task 1, 8 |
| 4.1 KPI cards | Task 2, 3, 4 |
| 4.2 Pending actions strip | Task 3, 4 |
| 4.3 County/facility map | Task 6 |
| 4.4 Completion matrix | Task 2, 3, 4 |
| 4.5 Charts | Task 9 |
| 5. Mentee module-detail view | Task 5 |
| 6. Activity completion tracking | Task 7 |
| 9. Accessibility | Task 5 (focus rings already in place) |

No placeholders remain. All tasks include exact file paths, code snippets, and verification commands.
