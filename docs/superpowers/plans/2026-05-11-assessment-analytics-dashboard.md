# Assessment Analytics Dashboard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `mode=assessment` as the first tab on `/analytics/dashboard`, showing KPI cards, section-score charts, an eligibility-driven facilities power table, and a mentorship drill-down page; plus a Filament backend action to mark feedback as given.

**Architecture:** Extend the existing `AnalyticsDashboardController` with an assessment branch. All query logic lives in a new `AssessmentAnalyticsService`. The view uses a new `assessment-mode.blade.php` partial included from `index.blade.php`. Feedback tracking is stored in four new columns on the `assessments` table and managed via a Filament table action.

**Tech Stack:** Laravel 12, Blade, Bootstrap 5, Chart.js, Alpine.js, Filament v3, MySQL

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `database/migrations/YYYY_MM_DD_add_feedback_tracking_to_assessments_table.php` | 4 feedback columns |
| Modify | `app/Models/Assessment.php` | feedback fillable / casts / feedbackGivenBy() |
| Create | `app/Services/AssessmentAnalyticsService.php` | All assessment analytics queries |
| Modify | `app/Http/Controllers/AnalyticsDashboardController.php` | assessment mode branch + facilityMentorshipBreakdown() |
| Modify | `routes/web.php` | mentorship breakdown route |
| Modify | `resources/views/analytics/dashboard/index.blade.php` | Assessments button in toggle + @include partial |
| Create | `resources/views/analytics/dashboard/assessment-mode.blade.php` | KPI strip, insights, charts, power table |
| Create | `resources/views/analytics/dashboard/facility-mentorship-breakdown.blade.php` | Drill-down page |
| Modify | `app/Filament/Resources/AssessmentResource.php` | Mark Feedback Given action |

---

## Task 1: Database migration — feedback tracking columns

**Files:**
- Create: `database/migrations/2026_05_11_000001_add_feedback_tracking_to_assessments_table.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_feedback_tracking_to_assessments_table --table=assessments
```

- [ ] **Step 2: Fill migration body**

Open the generated file and replace the `up()` / `down()` bodies:

```php
public function up(): void
{
    Schema::table('assessments', function (Blueprint $table) {
        $table->boolean('feedback_given')->default(false)->after('updated_by');
        $table->foreignId('feedback_given_by')->nullable()->constrained('users')->nullOnDelete()->after('feedback_given');
        $table->timestamp('feedback_given_at')->nullable()->after('feedback_given_by');
        $table->text('feedback_notes')->nullable()->after('feedback_given_at');
    });
}

public function down(): void
{
    Schema::table('assessments', function (Blueprint $table) {
        $table->dropConstrainedForeignId('feedback_given_by');
        $table->dropColumn(['feedback_given', 'feedback_given_at', 'feedback_notes']);
    });
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

Expected output: `Migrating: 2026_05_11_000001_add_feedback_tracking_to_assessments_table` then `Migrated`.

- [ ] **Step 4: Verify columns exist**

```bash
php artisan tinker --execute="Schema::getColumnListing('assessments');" 
```

Expected: array includes `feedback_given`, `feedback_given_by`, `feedback_given_at`, `feedback_notes`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_11_000001_add_feedback_tracking_to_assessments_table.php
git commit -m "feat: add feedback tracking columns to assessments table"
```

---

## Task 2: Update Assessment model

**Files:**
- Modify: `app/Models/Assessment.php`

- [ ] **Step 1: Add feedback fields to `$fillable`**

In `app/Models/Assessment.php`, replace the existing `$fillable` array:

```php
protected $fillable = [
    'facility_id',
    'assessment_type',
    'assessment_date',
    'assessor_id',
    'assessor_name',
    'assessor_contact',
    'status',
    'overall_score',
    'overall_percentage',
    'overall_grade',
    'section_progress',
    'completed_at',
    'completed_by',
    'created_by',
    'updated_by',
    'feedback_given',
    'feedback_given_by',
    'feedback_given_at',
    'feedback_notes',
];
```

- [ ] **Step 2: Add feedback casts**

Replace the existing `$casts` array:

```php
protected $casts = [
    'assessment_date'    => 'date',
    'section_progress'   => 'array',
    'completed_at'       => 'datetime',
    'overall_score'      => 'decimal:2',
    'overall_percentage' => 'decimal:2',
    'feedback_given'     => 'boolean',
    'feedback_given_at'  => 'datetime',
];
```

- [ ] **Step 3: Add `feedbackGivenBy()` relationship**

Add after the existing `completedBy()` method:

```php
public function feedbackGivenBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'feedback_given_by');
}
```

- [ ] **Step 4: Verify**

```bash
php artisan tinker --execute="echo App\Models\Assessment::first()?->feedback_given ?? 'null/false';"
```

Expected: `false` or `null` (no error).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Assessment.php
git commit -m "feat: add feedback tracking to Assessment model"
```

---

## Task 3: AssessmentAnalyticsService — getSummaryStats() + generateInsights()

**Files:**
- Create: `app/Services/AssessmentAnalyticsService.php`

- [ ] **Step 1: Create the service file**

Create `app/Services/AssessmentAnalyticsService.php`:

```php
<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssessmentAnalyticsService
{
    public function getSummaryStats(array $filters = []): array
    {
        $year           = $filters['year'] ?? null;
        $assessmentType = $filters['assessment_type'] ?? null;
        $countyId       = $filters['county_id'] ?? null;

        $base = fn() => Assessment::whereNull('deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->when($countyId, fn($q) => $q->whereHas('facility.subcounty', fn($s) => $s->where('county_id', $countyId)));

        $skillsLabSectionId = AssessmentSection::where('code', 'skills_lab')->value('id');

        $totalAssessments   = $base()->count();
        $facilitiesAssessed = $base()->distinct('facility_id')->count('facility_id');
        $allFacilities      = Facility::whereNull('deleted_at')->count();
        $avgScore           = round((float) ($base()->where('status', 'completed')->avg('overall_percentage') ?? 0), 1);

        $withSkillsLab = (int) DB::table('assessments')
            ->join('assessment_section_scores', 'assessment_section_scores.assessment_id', '=', 'assessments.id')
            ->where('assessment_section_scores.assessment_section_id', (int) $skillsLabSectionId)
            ->where('assessment_section_scores.percentage', '>', 0)
            ->where('assessments.status', 'completed')
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->when($countyId, fn($q) => $q
                ->join('facilities', 'facilities.id', '=', 'assessments.facility_id')
                ->join('subcounties', 'subcounties.id', '=', 'facilities.subcounty_id')
                ->where('subcounties.county_id', $countyId))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $eligible = (int) DB::table('assessments')
            ->join('assessment_section_scores', 'assessment_section_scores.assessment_id', '=', 'assessments.id')
            ->where('assessment_section_scores.assessment_section_id', (int) $skillsLabSectionId)
            ->where('assessment_section_scores.percentage', '>', 0)
            ->where('assessments.status', 'completed')
            ->where('assessments.feedback_given', true)
            ->whereNull('assessments.deleted_at')
            ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
            ->when($assessmentType, fn($q) => $q->where('assessments.assessment_type', $assessmentType))
            ->distinct('assessments.facility_id')
            ->count('assessments.facility_id');

        $facilityCoveragePercent = $allFacilities > 0
            ? round(($facilitiesAssessed / $allFacilities) * 100, 1)
            : 0;

        // YoY
        $curYear   = Carbon::now()->year;
        $prevYear  = $curYear - 1;
        $curCount  = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $curYear)->count();
        $prevCount = Assessment::whereNull('deleted_at')->whereYear('assessment_date', $prevYear)->count();
        $yoyChange = $prevCount > 0
            ? round((($curCount - $prevCount) / $prevCount) * 100, 1)
            : 0;

        return compact(
            'totalAssessments', 'facilitiesAssessed', 'allFacilities',
            'avgScore', 'withSkillsLab', 'eligible',
            'facilityCoveragePercent', 'yoyChange', 'curYear'
        );
    }

    public function generateInsights(array $stats): array
    {
        $insights           = [];
        $facilitiesAssessed = $stats['facilitiesAssessed'] ?? 0;
        $allFacilities      = $stats['allFacilities'] ?? 0;
        $coverage           = $stats['facilityCoveragePercent'] ?? 0;
        $withSkillsLab      = $stats['withSkillsLab'] ?? 0;
        $eligible           = $stats['eligible'] ?? 0;
        $avgScore           = $stats['avgScore'] ?? 0;

        if ($coverage >= 60) {
            $insights[] = ['type' => 'success', 'icon' => 'check-circle', 'text' =>
                "Strong coverage at {$coverage}% — {$facilitiesAssessed} of {$allFacilities} facilities have been assessed."];
        } elseif ($coverage >= 30) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "Moderate coverage at {$coverage}% — " . ($allFacilities - $facilitiesAssessed) . " facilities still need assessment."];
        } elseif ($facilitiesAssessed > 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'exclamation-circle', 'text' =>
                "Low coverage at {$coverage}% — significant outreach needed; " . ($allFacilities - $facilitiesAssessed) . " facilities unassessed."];
        }

        $noSkillsLab = $facilitiesAssessed - $withSkillsLab;
        if ($noSkillsLab > 0) {
            $insights[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' =>
                "{$noSkillsLab} assessed " . str('facility')->plural($noSkillsLab) . " lack a skills lab — not eligible for mentorship training."];
        }

        $pendingFeedback = $withSkillsLab - $eligible;
        if ($pendingFeedback > 0) {
            $insights[] = ['type' => 'info', 'icon' => 'clock', 'text' =>
                "{$pendingFeedback} " . str('facility')->plural($pendingFeedback) . " have a skills lab but feedback not given — partially eligible for mentorship."];
        }

        if ($avgScore > 0) {
            $grade = $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Improvement');
            $type  = $avgScore >= 80 ? 'success' : ($avgScore >= 50 ? 'warning' : 'danger');
            $insights[] = ['type' => $type, 'icon' => 'chart-bar', 'text' =>
                "National average score is {$avgScore}% ({$grade}). {$eligible} " . str('facility')->plural($eligible) . " fully eligible for mentorship."];
        }

        return array_slice($insights, 0, 4);
    }
}
```

- [ ] **Step 2: Verify file loads**

```bash
php artisan tinker --execute="app(App\Services\AssessmentAnalyticsService::class)->getSummaryStats([]);"
```

Expected: array with keys `totalAssessments`, `facilitiesAssessed`, etc. (values may be 0 in dev).

- [ ] **Step 3: Commit**

```bash
git add app/Services/AssessmentAnalyticsService.php
git commit -m "feat: add AssessmentAnalyticsService getSummaryStats and generateInsights"
```

---

## Task 4: AssessmentAnalyticsService — getChartData()

**Files:**
- Modify: `app/Services/AssessmentAnalyticsService.php`

- [ ] **Step 1: Add `getChartData()` method**

Add after `generateInsights()` inside `AssessmentAnalyticsService`:

```php
public function getChartData(array $filters = []): array
{
    $year           = $filters['year'] ?? null;
    $assessmentType = $filters['assessment_type'] ?? null;

    // ── 1. Monthly trend (12 months) grouped by type ──────────────────────
    $monthlyTrend = [];
    for ($i = 11; $i >= 0; $i--) {
        $date  = Carbon::now()->subMonths($i);
        $ms    = $date->copy()->startOfMonth();
        $me    = $date->copy()->endOfMonth();

        $counts = Assessment::whereNull('deleted_at')
            ->whereBetween('assessment_date', [$ms, $me])
            ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
            ->selectRaw('assessment_type, COUNT(*) as count')
            ->groupBy('assessment_type')
            ->pluck('count', 'assessment_type');

        $monthlyTrend[] = [
            'month'    => $date->format('M y'),
            'short'    => $date->format('M'),
            'baseline' => (int) ($counts['baseline'] ?? 0),
            'midline'  => (int) ($counts['midline']  ?? 0),
            'endline'  => (int) ($counts['endline']  ?? 0),
        ];
    }

    // ── 2. Grade distribution ─────────────────────────────────────────────
    $gradeRows = Assessment::where('status', 'completed')
        ->whereNull('deleted_at')
        ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
        ->selectRaw('overall_grade, COUNT(*) as count')
        ->groupBy('overall_grade')
        ->pluck('count', 'overall_grade');

    $gradeDistribution = [
        'green'  => (int) ($gradeRows['green']  ?? 0),
        'yellow' => (int) ($gradeRows['yellow'] ?? 0),
        'red'    => (int) ($gradeRows['red']    ?? 0),
    ];

    // ── 3. Section score averages ─────────────────────────────────────────
    $sectionAverages = DB::table('assessment_section_scores')
        ->join('assessment_sections', 'assessment_sections.id', '=', 'assessment_section_scores.assessment_section_id')
        ->join('assessments', 'assessments.id', '=', 'assessment_section_scores.assessment_id')
        ->where('assessments.status', 'completed')
        ->whereNull('assessments.deleted_at')
        ->when($year, fn($q) => $q->whereYear('assessments.assessment_date', $year))
        ->select(
            'assessment_sections.name',
            'assessment_sections.code',
            DB::raw('ROUND(AVG(assessment_section_scores.percentage), 1) as avg_percentage')
        )
        ->groupBy('assessment_sections.id', 'assessment_sections.name', 'assessment_sections.code')
        ->orderBy('assessment_sections.order')
        ->get()
        ->map(fn($row) => [
            'name'       => $row->name,
            'code'       => $row->code,
            'percentage' => (float) $row->avg_percentage,
            'color'      => $row->avg_percentage >= 80 ? '#10B981' : ($row->avg_percentage >= 50 ? '#F59E0B' : '#EF4444'),
        ]);

    // ── 4. Status breakdown ───────────────────────────────────────────────
    $statusRows = Assessment::whereNull('deleted_at')
        ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
        ->selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->pluck('count', 'status');

    $statusBreakdown = [
        'completed'   => (int) ($statusRows['completed']   ?? 0),
        'in_progress' => (int) ($statusRows['in_progress'] ?? 0),
        'draft'       => (int) ($statusRows['draft']       ?? 0),
    ];

    return compact('monthlyTrend', 'gradeDistribution', 'sectionAverages', 'statusBreakdown');
}
```

- [ ] **Step 2: Verify**

```bash
php artisan tinker --execute="app(App\Services\AssessmentAnalyticsService::class)->getChartData([]);"
```

Expected: array with keys `monthlyTrend`, `gradeDistribution`, `sectionAverages`, `statusBreakdown`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/AssessmentAnalyticsService.php
git commit -m "feat: add getChartData to AssessmentAnalyticsService"
```

---

## Task 5: AssessmentAnalyticsService — getFacilitiesReadiness()

**Files:**
- Modify: `app/Services/AssessmentAnalyticsService.php`

- [ ] **Step 1: Add `getFacilitiesReadiness()` method**

Add after `getChartData()` inside `AssessmentAnalyticsService`:

```php
public function getFacilitiesReadiness(array $filters = []): Collection
{
    $year           = $filters['year']            ?? null;
    $assessmentType = $filters['assessment_type'] ?? null;
    $countyId       = $filters['county_id']       ?? null;

    $skillsLabSectionId = (int) AssessmentSection::where('code', 'skills_lab')->value('id');

    // Subquery: latest completed assessment date per facility
    $latestDates = DB::table('assessments')
        ->select('facility_id', DB::raw('MAX(assessment_date) as max_date'))
        ->where('status', 'completed')
        ->whereNull('deleted_at')
        ->when($year, fn($q) => $q->whereYear('assessment_date', $year))
        ->when($assessmentType, fn($q) => $q->where('assessment_type', $assessmentType))
        ->groupBy('facility_id');

    $assessments = Assessment::query()
        ->joinSub($latestDates, 'latest', function ($join) {
            $join->on('assessments.facility_id', '=', 'latest.facility_id')
                 ->on('assessments.assessment_date', '=', 'latest.max_date');
        })
        ->where('assessments.status', 'completed')
        ->whereNull('assessments.deleted_at')
        ->when($countyId, fn($q) => $q->whereHas(
            'facility.subcounty', fn($s) => $s->where('county_id', $countyId)
        ))
        ->with(['assessor', 'feedbackGivenBy', 'facility.facilityLevel'])
        ->addSelect([
            'assessments.*',
            DB::raw(
                '(SELECT ss.percentage FROM assessment_section_scores ss
                  WHERE ss.assessment_id = assessments.id
                  AND ss.assessment_section_id = ' . $skillsLabSectionId . '
                  LIMIT 1) as skills_lab_percentage'
            ),
            DB::raw(
                '(SELECT COUNT(*) FROM trainings t
                  WHERE t.facility_id = assessments.facility_id
                  AND t.type = "facility_mentorship"
                  AND t.deleted_at IS NULL) as mentorship_count'
            ),
        ])
        ->get();

    return $assessments->map(function ($assessment) {
        $hasSkillsLab = ((float) ($assessment->skills_lab_percentage ?? 0)) > 0;
        $feedbackGiven = (bool) $assessment->feedback_given;

        $assessment->has_skills_lab = $hasSkillsLab;
        $assessment->eligibility_status = match (true) {
            $hasSkillsLab && $feedbackGiven => 'eligible',
            $hasSkillsLab                   => 'partial',
            default                         => 'not_eligible',
        };

        return $assessment;
    });
}
```

- [ ] **Step 2: Verify**

```bash
php artisan tinker --execute="app(App\Services\AssessmentAnalyticsService::class)->getFacilitiesReadiness([])->count();"
```

Expected: integer (number of assessed facilities, could be 0 in dev).

- [ ] **Step 3: Commit**

```bash
git add app/Services/AssessmentAnalyticsService.php
git commit -m "feat: add getFacilitiesReadiness to AssessmentAnalyticsService"
```

---

## Task 6: Controller — assessment mode branch in index()

**Files:**
- Modify: `app/Http/Controllers/AnalyticsDashboardController.php`

- [ ] **Step 1: Add missing use statement**

At the top of `AnalyticsDashboardController.php`, add after the existing `use` block:

```php
use App\Models\Assessment;
use App\Models\County;
use App\Services\AssessmentAnalyticsService;
```

- [ ] **Step 2: Add assessment mode branch in `index()`**

In the `index()` method, locate the line:

```php
$counties = $this->getCountiesData($selectedYear, $mode);
```

Insert a new block **before** that line:

```php
if ($mode === 'assessment') {
    $filters = [
        'year'            => $selectedYear,
        'county_id'       => $request->get('county_id'),
        'assessment_type' => $request->get('assessment_type'),
    ];

    $assessmentService     = app(AssessmentAnalyticsService::class);
    $summaryStats          = $assessmentService->getSummaryStats($filters);
    $chartData             = $assessmentService->getChartData($filters);
    $facilitiesReadiness   = $assessmentService->getFacilitiesReadiness($filters);
    $insights              = $assessmentService->generateInsights($summaryStats);
    $counties              = County::orderBy('name')->get(['id', 'name']);
    $selectedCounty        = $filters['county_id'];
    $selectedAssessmentType = $filters['assessment_type'];

    $availableYears = Assessment::selectRaw('YEAR(assessment_date) as year')
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->filter();

    return view('analytics.dashboard.index', compact(
        'mode', 'selectedYear', 'availableYears',
        'summaryStats', 'chartData', 'facilitiesReadiness', 'insights',
        'counties', 'selectedCounty', 'selectedAssessmentType'
    ));
}
```

- [ ] **Step 3: Verify route loads without error**

```bash
php artisan route:list --name=analytics.dashboard.index
```

Then visit `/analytics/dashboard?mode=assessment` in browser — expect 200 (view may be missing, that's OK for now).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/AnalyticsDashboardController.php
git commit -m "feat: add assessment mode branch to AnalyticsDashboardController"
```

---

## Task 7: Controller — facilityMentorshipBreakdown() + route

**Files:**
- Modify: `app/Http/Controllers/AnalyticsDashboardController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add missing use statements to controller**

Add to the `use` block (if not already present):

```php
use App\Models\MentorshipClass;
use App\Models\ClassParticipant;
use App\Models\ClassAttendance;
```

- [ ] **Step 2: Add `facilityMentorshipBreakdown()` method**

Add as a new public method on `AnalyticsDashboardController`:

```php
public function facilityMentorshipBreakdown(\App\Models\Facility $facility, Request $request)
{
    $selectedYear = $request->get('year', '');

    $mentorships = Training::where('type', 'facility_mentorship')
        ->where('facility_id', $facility->id)
        ->when(!empty($selectedYear), fn($q) => $q->whereYear('start_date', $selectedYear))
        ->with([
            'mentor',
            'program',
            'mentorshipClasses' => fn($q) => $q->with([
                'classModules.programModule',
                'classSessions',
                'participants' => fn($q) => $q->with(['user.cadre', 'user.department']),
            ]),
        ])
        ->orderBy('start_date', 'desc')
        ->get();

    // Compute per-class attendance summary
    $mentorships->each(function ($training) {
        $training->mentorshipClasses->each(function ($class) {
            $moduleIds        = $class->classModules->pluck('id');
            $totalSlots       = $class->classModules->count() * $class->participants->count();
            $attendedSlots    = ClassAttendance::whereIn('class_module_id', $moduleIds)->count();

            $class->attendance_total   = $totalSlots;
            $class->attendance_present = $attendedSlots;

            // Per-mentee attendance
            $class->participants->each(function ($participant) use ($class, $moduleIds) {
                $attended = ClassAttendance::whereIn('class_module_id', $moduleIds)
                    ->where('user_id', $participant->user_id)
                    ->count();
                $total = $class->classModules->count();

                $participant->modules_attended = $attended;
                $participant->modules_total    = $total;
                $participant->attendance_pct   = $total > 0 ? round(($attended / $total) * 100) : 0;
                $participant->attendance_label = match (true) {
                    $participant->attendance_pct >= 80 => 'Present',
                    $participant->attendance_pct >= 50 => 'Partial',
                    default                            => 'Absent',
                };
            });
        });
    });

    $availableYears = Training::selectRaw('YEAR(start_date) as year')
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->filter();

    $breadcrumbs = [
        ['name' => 'Analytics Dashboard', 'url' => route('analytics.dashboard.index', ['mode' => 'assessment'])],
        ['name' => $facility->name . ' — Mentorships', 'url' => null],
    ];

    return view('analytics.dashboard.facility-mentorship-breakdown', compact(
        'facility', 'mentorships', 'availableYears', 'selectedYear', 'breadcrumbs'
    ));
}
```

- [ ] **Step 3: Add route in `routes/web.php`**

Inside the `analytics/dashboard` route group (before the closing `});`), add:

```php
Route::get('/facility/{facility}/mentorship-breakdown',
    [AnalyticsDashboardController::class, 'facilityMentorshipBreakdown'])
    ->name('facility.mentorship-breakdown');
```

- [ ] **Step 4: Verify route registered**

```bash
php artisan route:list --name=analytics.dashboard.facility.mentorship-breakdown
```

Expected: one route listed.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AnalyticsDashboardController.php routes/web.php
git commit -m "feat: add facilityMentorshipBreakdown controller method and route"
```

---

## Task 8: Update index.blade.php — Assessments tab + partial include

**Files:**
- Modify: `resources/views/analytics/dashboard/index.blade.php`

- [ ] **Step 1: Add Assessments button to the mode toggle**

Find the `<div class="mode-toggle"` block in the hero. It currently contains two buttons. Add the Assessments button **before** Trainings:

```html
<div class="mode-toggle" data-intro="Switch between Assessment, Training and Mentorship analytics modes." data-step="2">
    <button type="button" class="mode-btn {{ $mode === 'assessment' ? 'active' : '' }}" data-mode="assessment">
        <i class="fas fa-clipboard-check me-1"></i> Assessments
    </button>
    <button type="button" class="mode-btn {{ $mode === 'training' ? 'active' : '' }}" data-mode="training">
        <i class="fas fa-chalkboard-teacher me-1"></i> Trainings
    </button>
    <button type="button" class="mode-btn {{ $mode === 'mentorship' ? 'active' : '' }}" data-mode="mentorship">
        <i class="fas fa-user-friends me-1"></i> Mentorships
    </button>
</div>
```

- [ ] **Step 2: Update hero title to handle assessment mode**

Find:
```html
<h1>{{ ucfirst($mode) }} Analytics Dashboard</h1>
```
Replace with:
```html
<h1>{{ $mode === 'assessment' ? 'Assessment' : ucfirst($mode) }} Analytics Dashboard</h1>
```

Find the hero subtitle `<p>` block and add:
```html
@if($mode === 'assessment')
    Comprehensive assessment analytics across Kenya healthcare facilities
@elseif($mode === 'training')
```
(wrap existing training/mentorship lines in `@elseif`/`@else` accordingly).

- [ ] **Step 3: Include assessment-mode partial**

After the filter collapse section, find the first `@if` or content block that starts the main dashboard body. Wrap all existing non-assessment content with `@if($mode !== 'assessment')` and add the assessment include:

```blade
@if($mode === 'assessment')
    @include('analytics.dashboard.assessment-mode')
@else
    {{-- existing KPI strip, charts, map content stays here unchanged --}}
```

Find `<!-- ████████ KPI STRIP ████████ -->` and wrap everything from that comment down to the final `</div>` of content (before `@endsection`) in `@else ... @endif`.

- [ ] **Step 4: Verify page loads in training mode (no regressions)**

Visit `/analytics/dashboard?mode=training` — confirm existing dashboard still renders correctly.

- [ ] **Step 5: Commit**

```bash
git add resources/views/analytics/dashboard/index.blade.php
git commit -m "feat: add Assessments tab to analytics dashboard mode toggle"
```

---

## Task 9: Build assessment-mode.blade.php — KPI strip, insights, charts

**Files:**
- Create: `resources/views/analytics/dashboard/assessment-mode.blade.php`

- [ ] **Step 1: Create the partial**

Create `resources/views/analytics/dashboard/assessment-mode.blade.php`:

```blade
@php
    $totalAssessments       = $summaryStats['totalAssessments'] ?? 0;
    $facilitiesAssessed     = $summaryStats['facilitiesAssessed'] ?? 0;
    $allFacilities          = $summaryStats['allFacilities'] ?? 0;
    $avgScore               = $summaryStats['avgScore'] ?? 0;
    $withSkillsLab          = $summaryStats['withSkillsLab'] ?? 0;
    $eligible               = $summaryStats['eligible'] ?? 0;
    $facilityCoverage       = $summaryStats['facilityCoveragePercent'] ?? 0;
    $yoyChange              = $summaryStats['yoyChange'] ?? 0;
    $avgColor               = $avgScore >= 80 ? 'up' : ($avgScore >= 50 ? 'flat' : 'down');
@endphp

{{-- ████████ ASSESSMENT FILTERS ████████ --}}
<div class="dash-section">
    <div class="collapse show" id="assessmentFilters">
        <div class="filter-card">
            <form method="GET" action="{{ route('analytics.dashboard.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="mode" value="assessment">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">County</label>
                    <select name="county_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Counties</option>
                        @foreach($counties ?? [] as $county)
                            <option value="{{ $county->id }}" {{ ($selectedCounty ?? '') == $county->id ? 'selected' : '' }}>{{ $county->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Assessment Type</label>
                    <select name="assessment_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="baseline" {{ ($selectedAssessmentType ?? '') === 'baseline' ? 'selected' : '' }}>Baseline</option>
                        <option value="midline"  {{ ($selectedAssessmentType ?? '') === 'midline'  ? 'selected' : '' }}>Midline</option>
                        <option value="endline"  {{ ($selectedAssessmentType ?? '') === 'endline'  ? 'selected' : '' }}>Endline</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('analytics.dashboard.index', ['mode' => 'assessment']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ████████ KPI STRIP ████████ --}}
<div class="kpi-strip-wrap">
    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-value">{{ number_format($totalAssessments) }}</div>
            <div class="kpi-label">Total Assessments</div>
            @if($yoyChange > 0)
                <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> {{ $yoyChange }}% YoY</span>
            @elseif($yoyChange < 0)
                <span class="kpi-trend down"><i class="fas fa-arrow-down"></i> {{ abs($yoyChange) }}% YoY</span>
            @else
                <span class="kpi-trend flat"><i class="fas fa-minus"></i> Stable</span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-hospital"></i></div>
            <div class="kpi-value">{{ number_format($facilitiesAssessed) }}</div>
            <div class="kpi-label">Facilities Assessed</div>
            <span class="kpi-trend flat"><i class="fas fa-building"></i> {{ $facilityCoverage }}% coverage</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-star-half-alt"></i></div>
            <div class="kpi-value">{{ $avgScore }}%</div>
            <div class="kpi-label">Avg Score</div>
            <span class="kpi-trend {{ $avgColor }}">
                {{ $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Work') }}
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-flask"></i></div>
            <div class="kpi-value">{{ number_format($withSkillsLab) }}</div>
            <div class="kpi-label">Have Skills Lab</div>
            @if($facilitiesAssessed > 0)
                <span class="kpi-trend flat">{{ round(($withSkillsLab / $facilitiesAssessed) * 100) }}% of assessed</span>
            @endif
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-value">{{ number_format($eligible) }}</div>
            <div class="kpi-label">Eligible for Mentorship</div>
            @if($withSkillsLab > 0)
                @php $partials = $withSkillsLab - $eligible; @endphp
                <span class="kpi-trend flat">{{ $partials }} partial</span>
            @endif
        </div>
    </div>
</div>

{{-- ████████ INSIGHTS ████████ --}}
@if(!empty($insights))
<div class="dash-section">
    <div class="section-title"><i class="fas fa-lightbulb"></i> Insights</div>
    <div class="insights-grid">
        @foreach($insights as $insight)
            <div class="insight-card {{ $insight['type'] }}">
                <div class="insight-icon"><i class="fas fa-{{ $insight['icon'] }}"></i></div>
                <div class="insight-text">{{ $insight['text'] }}</div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- ████████ CHARTS ROW 1 ████████ --}}
<div class="dash-section">
    <div class="chart-row">
        <div class="chart-2-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-chart-bar"></i> Assessments Over Time</h6>
                    <small>Last 12 months by type</small>
                </div>
                <div class="chart-card-body"><canvas id="assessmentTrendChart" height="100"></canvas></div>
            </div>
        </div>
        <div class="chart-1-3">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-circle-notch"></i> Grade Distribution</h6>
                    <small>Completed assessments</small>
                </div>
                <div class="chart-card-body"><canvas id="gradeDistChart" height="180"></canvas></div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ CHARTS ROW 2 ████████ --}}
<div class="dash-section">
    <div class="chart-row">
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-layer-group"></i> Section Score Averages</h6>
                    <small>Avg % across all completed assessments</small>
                </div>
                <div class="chart-card-body"><canvas id="sectionScoreChart" height="160"></canvas></div>
            </div>
        </div>
        <div class="chart-half">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h6><i class="fas fa-tasks"></i> Assessment Status</h6>
                    <small>Draft / In Progress / Completed</small>
                </div>
                <div class="chart-card-body"><canvas id="statusChart" height="160"></canvas></div>
            </div>
        </div>
    </div>
</div>

{{-- ████████ CHART JS ████████ --}}
@push('scripts')
<script>
(function () {
    const tealPalette = ['#0097A7','#F59E0B','#8B5CF6','#10B981','#EF4444','#2563EB'];

    // 1. Monthly trend
    const trendLabels  = {!! json_encode(array_column($chartData['monthlyTrend'], 'short')) !!};
    const trendChart   = document.getElementById('assessmentTrendChart');
    if (trendChart) {
        new Chart(trendChart, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    { label: 'Baseline', data: {!! json_encode(array_column($chartData['monthlyTrend'], 'baseline')) !!}, backgroundColor: '#0097A7' },
                    { label: 'Midline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'midline'))  !!}, backgroundColor: '#F59E0B' },
                    { label: 'Endline',  data: {!! json_encode(array_column($chartData['monthlyTrend'], 'endline'))  !!}, backgroundColor: '#8B5CF6' },
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { x: { stacked: false }, y: { beginAtZero: true, stacked: false } } }
        });
    }

    // 2. Grade distribution donut
    const gradeChart = document.getElementById('gradeDistChart');
    if (gradeChart) {
        new Chart(gradeChart, {
            type: 'doughnut',
            data: {
                labels: ['Good (≥80%)', 'Fair (50–79%)', 'Poor (<50%)'],
                datasets: [{ data: [{{ $chartData['gradeDistribution']['green'] }}, {{ $chartData['gradeDistribution']['yellow'] }}, {{ $chartData['gradeDistribution']['red'] }}], backgroundColor: ['#10B981','#F59E0B','#EF4444'], borderWidth: 2 }]
            },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
        });
    }

    // 3. Section score averages — horizontal bar
    const sectionChart = document.getElementById('sectionScoreChart');
    if (sectionChart) {
        new Chart(sectionChart, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartData['sectionAverages']->pluck('name')->toArray()) !!},
                datasets: [{
                    label: 'Avg Score (%)',
                    data: {!! json_encode($chartData['sectionAverages']->pluck('percentage')->toArray()) !!},
                    backgroundColor: {!! json_encode($chartData['sectionAverages']->pluck('color')->toArray()) !!},
                }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100 } } }
        });
    }

    // 4. Status donut
    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Draft'],
                datasets: [{ data: [{{ $chartData['statusBreakdown']['completed'] }}, {{ $chartData['statusBreakdown']['in_progress'] }}, {{ $chartData['statusBreakdown']['draft'] }}], backgroundColor: ['#0097A7','#F59E0B','#94A3B8'], borderWidth: 2 }]
            },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
</script>
@endpush
```

- [ ] **Step 2: Verify `@push('scripts')` is supported**

Check `resources/views/layouts/dashboard.blade.php` for `@stack('scripts')`. If not present, add it just before `</body>`. If the layout uses a different stack name, use that instead.

- [ ] **Step 3: Visit `/analytics/dashboard?mode=assessment` and verify KPIs and charts render**

- [ ] **Step 4: Commit**

```bash
git add resources/views/analytics/dashboard/assessment-mode.blade.php
git commit -m "feat: add assessment mode KPI strip, insights, and charts partial"
```

---

## Task 10: Add the power table to assessment-mode.blade.php

**Files:**
- Modify: `resources/views/analytics/dashboard/assessment-mode.blade.php`

- [ ] **Step 1: Append the power table section**

Add the following block at the **end** of `assessment-mode.blade.php`, before `@endpush`:

```blade
{{-- ████████ FACILITIES READINESS TABLE ████████ --}}
<div class="dash-section" x-data="{
    filterCounty: '{{ $selectedCounty ?? '' }}',
    filterSkillsLab: 'all',
    filterEligibility: 'all',
    filterFeedback: 'all',
    filterType: '{{ $selectedAssessmentType ?? 'all' }}',
    matches(row) {
        if (this.filterSkillsLab !== 'all' && row.dataset.skillsLab !== this.filterSkillsLab) return false;
        if (this.filterEligibility !== 'all' && row.dataset.eligibility !== this.filterEligibility) return false;
        if (this.filterFeedback !== 'all' && row.dataset.feedback !== this.filterFeedback) return false;
        if (this.filterType !== 'all' && row.dataset.atype !== this.filterType) return false;
        return true;
    }
}">
    <div class="section-title"><i class="fas fa-table"></i> Facilities Readiness & Mentorship Eligibility</div>

    {{-- Table filters --}}
    <div class="filter-card mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Skills Lab</label>
                <select class="form-select form-select-sm" x-model="filterSkillsLab">
                    <option value="all">All</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Eligibility</label>
                <select class="form-select form-select-sm" x-model="filterEligibility">
                    <option value="all">All</option>
                    <option value="eligible">Eligible</option>
                    <option value="partial">Partial</option>
                    <option value="not_eligible">Not Eligible</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Feedback</label>
                <select class="form-select form-select-sm" x-model="filterFeedback">
                    <option value="all">All</option>
                    <option value="given">Given</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Type</label>
                <select class="form-select form-select-sm" x-model="filterType">
                    <option value="all">All Types</option>
                    <option value="baseline">Baseline</option>
                    <option value="midline">Midline</option>
                    <option value="endline">Endline</option>
                </select>
            </div>
        </div>
    </div>

    <div class="chart-card" style="overflow-x:auto;">
        <table class="analytics-table" id="readinessTable">
            <thead>
                <tr>
                    <th>Facility</th>
                    <th>Subcounty / County</th>
                    <th>Level</th>
                    <th>Latest Assessment</th>
                    <th>Assessed By</th>
                    <th>Skills Lab</th>
                    <th>Feedback</th>
                    <th>Eligibility</th>
                    <th>Mentorships</th>
                </tr>
            </thead>
            <tbody>
                @forelse($facilitiesReadiness as $assessment)
                    @php
                        $slKey   = $assessment->has_skills_lab ? 'yes' : 'no';
                        $fbKey   = $assessment->feedback_given ? 'given' : 'pending';
                        $eligKey = $assessment->eligibility_status;
                    @endphp
                    <tr
                        x-show="matches($el)"
                        data-skills-lab="{{ $slKey }}"
                        data-eligibility="{{ $eligKey }}"
                        data-feedback="{{ $fbKey }}"
                        data-atype="{{ $assessment->assessment_type }}"
                    >
                        <td>
                            <div class="fw-semibold" style="color:var(--gray-800)">{{ $assessment->facility->name }}</div>
                            <small class="badge bg-secondary">{{ $assessment->facility->mfl_code }}</small>
                        </td>
                        <td>
                            <div>{{ $assessment->facility->subcounty->name ?? '—' }}</div>
                            <small style="color:var(--gray-500)">{{ $assessment->facility->subcounty->county->name ?? '—' }}</small>
                        </td>
                        <td>
                            @if($assessment->facility->facilityLevel)
                                <span class="badge" style="background:var(--teal-50);color:var(--teal-dark)">{{ $assessment->facility->facilityLevel->name }}</span>
                            @else
                                <span style="color:var(--gray-500)">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $assessment->assessment_date->format('d M Y') }}</div>
                            <span class="badge" style="background:{{ $assessment->assessment_type === 'baseline' ? 'rgba(0,151,167,.12)' : ($assessment->assessment_type === 'midline' ? 'rgba(245,158,11,.12)' : 'rgba(139,92,246,.12)') }};color:{{ $assessment->assessment_type === 'baseline' ? 'var(--teal-dark)' : ($assessment->assessment_type === 'midline' ? '#92400E' : '#5B21B6') }}">
                                {{ ucfirst($assessment->assessment_type) }}
                            </span>
                        </td>
                        <td>{{ $assessment->assessor_name }}</td>
                        <td>
                            @if($assessment->has_skills_lab)
                                <span class="badge" style="background:#D1FAE5;color:#065F46"><i class="fas fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge" style="background:#FEE2E2;color:#991B1B"><i class="fas fa-times me-1"></i>No</span>
                            @endif
                        </td>
                        <td>
                            @if($assessment->feedback_given)
                                <span style="font-size:.8rem;color:#065F46">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    {{ $assessment->feedbackGivenBy->name ?? 'System' }}
                                    @if($assessment->feedback_given_at)
                                        <br><small style="color:var(--gray-500)">{{ $assessment->feedback_given_at->format('d M Y') }}</small>
                                    @endif
                                </span>
                            @else
                                <span class="badge" style="background:#FEF3C7;color:#92400E"><i class="fas fa-clock me-1"></i>Pending</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $eligStyle = match($assessment->eligibility_status) {
                                    'eligible'     => 'background:#D1FAE5;color:#065F46',
                                    'partial'      => 'background:#FEF3C7;color:#92400E',
                                    default        => 'background:#FEE2E2;color:#991B1B',
                                };
                                $eligLabel = match($assessment->eligibility_status) {
                                    'eligible' => '🟢 Eligible',
                                    'partial'  => '🟡 Partial',
                                    default    => '🔴 Not Eligible',
                                };
                            @endphp
                            <span class="badge" style="{{ $eligStyle }}">{{ $eligLabel }}</span>
                        </td>
                        <td>
                            @if($assessment->mentorship_count > 0)
                                <a href="{{ route('analytics.dashboard.facility.mentorship-breakdown', ['facility' => $assessment->facility_id, 'year' => $selectedYear]) }}"
                                   style="color:var(--teal);font-weight:700;text-decoration:none;">
                                    {{ $assessment->mentorship_count }}
                                    <i class="fas fa-external-link-alt ms-1" style="font-size:.7rem"></i>
                                </a>
                            @else
                                <span style="color:var(--gray-500)">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--gray-500)">
                            <i class="fas fa-clipboard-list fa-2x mb-2 d-block"></i>
                            No assessed facilities found for the current filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 2: Verify Alpine.js is loaded on the dashboard layout**

Check `resources/views/layouts/dashboard.blade.php` for `alpinejs` or `x-cloak`. If Alpine is not loaded, add:
```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```
before `</head>`.

- [ ] **Step 3: Visit `/analytics/dashboard?mode=assessment` and verify the table renders with filter dropdowns**

- [ ] **Step 4: Commit**

```bash
git add resources/views/analytics/dashboard/assessment-mode.blade.php
git commit -m "feat: add facilities readiness power table to assessment mode dashboard"
```

---

## Task 11: Build facility-mentorship-breakdown.blade.php

**Files:**
- Create: `resources/views/analytics/dashboard/facility-mentorship-breakdown.blade.php`

- [ ] **Step 1: Create the view**

Create `resources/views/analytics/dashboard/facility-mentorship-breakdown.blade.php`:

```blade
@extends('layouts.dashboard')
@section('title', $facility->name . ' — Mentorship Breakdown')

@section('content')
<style>
.breakdown-hero { background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%); padding: 1.5rem 2rem; color: #fff; margin-bottom: 0; }
.breakdown-hero h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 .25rem; }
.breakdown-hero p  { margin: 0; color: rgba(255,255,255,.8); font-size: .9rem; }
.breadcrumb-bar { background: #fff; border-bottom: 1px solid var(--gray-200); padding: .6rem 2rem; font-size: .82rem; }
.breadcrumb-bar a { color: var(--teal); text-decoration: none; }
.breadcrumb-bar a:hover { text-decoration: underline; }
.program-block { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); margin: 1.5rem; overflow: hidden; }
.program-header { background: linear-gradient(90deg, var(--teal-50) 0%, #E0F2FE 100%); padding: 1rem 1.5rem; border-bottom: 2px solid var(--teal-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
.program-header h5 { margin: 0; font-weight: 800; color: var(--teal-dark); font-size: 1rem; }
.class-row { border-bottom: 1px solid var(--gray-100); }
.class-header { padding: .9rem 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: var(--gray-50); }
.class-header:hover { background: var(--teal-50); }
.class-header h6 { margin: 0; font-weight: 700; color: var(--gray-800); font-size: .9rem; }
.class-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .78rem; color: var(--gray-500); }
.mentee-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.mentee-table th { background: var(--teal-dark); color: rgba(255,255,255,.9); padding: .6rem 1rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; text-align: left; }
.mentee-table td { padding: .55rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
.mentee-table tr:hover td { background: var(--teal-50); }
.badge-status { display: inline-block; padding: .2rem .55rem; border-radius: 12px; font-size: .72rem; font-weight: 700; }
</style>

{{-- Breadcrumb --}}
<div class="breadcrumb-bar">
    @foreach($breadcrumbs as $i => $crumb)
        @if($crumb['url'])
            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
        @else
            <span style="color:var(--gray-700)">{{ $crumb['name'] }}</span>
        @endif
        @if(!$loop->last) <span class="mx-1" style="color:var(--gray-400)">/</span> @endif
    @endforeach
</div>

{{-- Hero --}}
<div class="breakdown-hero">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7);margin-bottom:.4rem;">
                <i class="fas fa-map-marker-alt me-1"></i>
                {{ $facility->subcounty->name ?? '' }}{{ $facility->subcounty->county->name ? ' — ' . $facility->subcounty->county->name : '' }}
            </div>
            <h1><i class="fas fa-user-friends me-2"></i>{{ $facility->name }}</h1>
            <p>{{ $mentorships->count() }} mentorship {{ str('programme')->plural($mentorships->count()) }} • Mentorship drill-down</p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
                <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    @foreach($availableYears as $yr)
                        <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('analytics.dashboard.index', ['mode' => 'assessment']) }}" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

{{-- Programs --}}
@forelse($mentorships as $training)
    <div class="program-block">
        <div class="program-header">
            <div>
                <h5><i class="fas fa-graduation-cap me-2" style="color:var(--teal)"></i>{{ $training->title }}</h5>
                <div style="font-size:.8rem;color:var(--gray-600);margin-top:.25rem">
                    @if($training->program) <span><i class="fas fa-tag me-1"></i>{{ $training->program->name }}</span> @endif
                    @if($training->mentor)  <span class="ms-3"><i class="fas fa-user-tie me-1"></i>{{ $training->mentor->name }}</span> @endif
                    @if($training->start_date) <span class="ms-3"><i class="fas fa-calendar me-1"></i>{{ $training->start_date->format('M Y') }}</span> @endif
                </div>
            </div>
            <span class="badge-status" style="background:{{ match($training->status) { 'completed' => '#DBEAFE', 'ongoing','active' => '#D1FAE5', default => '#F1F5F9' } }};color:{{ match($training->status) { 'completed' => '#1E40AF', 'ongoing','active' => '#065F46', default => '#475569' } }}">
                {{ ucfirst($training->status) }}
            </span>
        </div>

        @forelse($training->mentorshipClasses as $class)
            <div class="class-row" x-data="{ open: false }">
                <div class="class-header" @click="open = !open">
                    <div>
                        <h6>{{ $class->name }}</h6>
                        <div class="class-meta">
                            @if($class->start_date)
                                <span><i class="fas fa-calendar-alt me-1"></i>{{ $class->start_date->format('d M Y') }}
                                @if($class->end_date) – {{ $class->end_date->format('d M Y') }} @endif</span>
                            @endif
                            <span><i class="fas fa-cubes me-1"></i>{{ $class->classModules->count() }} modules</span>
                            <span><i class="fas fa-users me-1"></i>{{ $class->participants->count() }} mentees</span>
                            <span><i class="fas fa-calendar-check me-1"></i>{{ $class->attendance_present }}/{{ $class->attendance_total }} attendances</span>
                            <span><i class="fas fa-video me-1"></i>{{ $class->classSessions->count() }} sessions</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <span class="badge-status" style="background:{{ match($class->status ?? 'draft') { 'completed' => '#DBEAFE', 'active' => '#D1FAE5', default => '#F1F5F9' } }};color:{{ match($class->status ?? 'draft') { 'completed' => '#1E40AF', 'active' => '#065F46', default => '#475569' } }}">
                            {{ ucfirst($class->status ?? 'Draft') }}
                        </span>
                        <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" style="color:var(--teal)"></i>
                    </div>
                </div>

                <div x-show="open" x-transition style="border-top:1px solid var(--gray-100);">
                    @if($class->participants->isEmpty())
                        <div style="padding:1.5rem;text-align:center;color:var(--gray-500)">No mentees enrolled in this class.</div>
                    @else
                        <div style="overflow-x:auto;">
                            <table class="mentee-table">
                                <thead>
                                    <tr>
                                        <th>Mentee</th>
                                        <th>Cadre</th>
                                        <th>Department</th>
                                        <th>Modules Attended</th>
                                        <th>Attendance</th>
                                        <th>Class Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($class->participants as $participant)
                                        <tr>
                                            <td style="font-weight:600">{{ $participant->user->name ?? '—' }}</td>
                                            <td>{{ $participant->user->cadre->name ?? '—' }}</td>
                                            <td>{{ $participant->user->department->name ?? '—' }}</td>
                                            <td>{{ $participant->modules_attended }}/{{ $participant->modules_total }}</td>
                                            <td>
                                                @php
                                                    $pct = $participant->attendance_pct;
                                                    $attStyle = $pct >= 80 ? 'background:#D1FAE5;color:#065F46' : ($pct >= 50 ? 'background:#FEF3C7;color:#92400E' : 'background:#FEE2E2;color:#991B1B');
                                                @endphp
                                                <span class="badge-status" style="{{ $attStyle }}">
                                                    {{ $participant->attendance_label }} ({{ $pct }}%)
                                                </span>
                                            </td>
                                            <td>
                                                @php
                                                    $stStyle = match($participant->status ?? 'enrolled') {
                                                        'completed' => 'background:#DBEAFE;color:#1E40AF',
                                                        'dropped'   => 'background:#FEE2E2;color:#991B1B',
                                                        default     => 'background:#F1F5F9;color:#475569',
                                                    };
                                                @endphp
                                                <span class="badge-status" style="{{ $stStyle }}">{{ ucfirst($participant->status ?? 'Enrolled') }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding:1.5rem;text-align:center;color:var(--gray-500)">No classes found for this mentorship.</div>
        @endforelse
    </div>
@empty
    <div style="margin:2rem;text-align:center;padding:3rem;background:#fff;border-radius:14px;color:var(--gray-500);">
        <i class="fas fa-user-friends fa-3x mb-3 d-block" style="color:var(--gray-300)"></i>
        <h5 style="color:var(--gray-700)">No mentorship programs found</h5>
        <p>{{ $facility->name }} has not hosted any mentorship programs yet.</p>
    </div>
@endforelse

@endsection
```

- [ ] **Step 2: Test the drill-down with a known facility**

Visit `/analytics/dashboard/facility/1/mentorship-breakdown` (replace `1` with a valid facility ID that has assessments). Verify the page loads and shows collapsible class rows.

- [ ] **Step 3: Commit**

```bash
git add resources/views/analytics/dashboard/facility-mentorship-breakdown.blade.php
git commit -m "feat: add facility mentorship breakdown drill-down page"
```

---

## Task 12: Verify dashboard layout supports @stack('scripts') and Alpine.js

**Files:**
- Modify (if needed): `resources/views/layouts/dashboard.blade.php`

- [ ] **Step 1: Check for @stack('scripts')**

```bash
grep -n "stack('scripts')\|push('scripts')\|alpinejs\|alpine" resources/views/layouts/dashboard.blade.php
```

- [ ] **Step 2: Add missing stacks/Alpine if not found**

If `@stack('scripts')` is missing, add just before `</body>`:
```blade
@stack('scripts')
```

If Alpine.js is not loaded, add in `<head>`:
```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
```

- [ ] **Step 3: Visit assessment dashboard and confirm all 4 charts render, table filters work**

- [ ] **Step 4: Commit (only if layout was changed)**

```bash
git add resources/views/layouts/dashboard.blade.php
git commit -m "chore: add Alpine.js CDN and @stack(scripts) to dashboard layout"
```

---

## Task 13: Filament "Mark Feedback Given" action on AssessmentResource

**Files:**
- Modify: `app/Filament/Resources/AssessmentResource.php`

- [ ] **Step 1: Locate the ActionGroup in the table() method**

In `AssessmentResource.php`, find the `ActionGroup::make([` block inside `->actions([`. Currently it contains: `dashboard` (Continue Assessment), `view_summary` (View Summary), `download` (Download Report).

- [ ] **Step 2: Add the action after `view_summary`**

Insert the following action between `view_summary` and `download`:

```php
Tables\Actions\Action::make('markFeedbackGiven')
    ->label(fn($record) => $record->feedback_given ? 'Update Feedback' : 'Mark Feedback Given')
    ->icon('heroicon-o-chat-bubble-left-right')
    ->color('success')
    ->visible(fn($record) => $record->status === 'completed')
    ->form([
        \Filament\Forms\Components\Textarea::make('feedback_notes')
            ->label('Feedback notes (optional)')
            ->default(fn($record) => $record->feedback_notes)
            ->rows(3),
    ])
    ->requiresConfirmation()
    ->modalHeading(fn($record) => $record->feedback_given
        ? 'Update Feedback Record'
        : 'Mark Feedback as Given')
    ->modalDescription(fn($record) => $record->feedback_given
        ? 'Update the feedback notes for this assessment.'
        : 'Confirm that feedback has been given to the facility for this assessment.')
    ->action(function ($record, array $data): void {
        $record->update([
            'feedback_given'    => true,
            'feedback_given_by' => auth()->id(),
            'feedback_given_at' => now(),
            'feedback_notes'    => $data['feedback_notes'] ?? null,
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Feedback marked as given')
            ->success()
            ->send();
    }),
```

- [ ] **Step 3: Clear Filament caches and verify in browser**

```bash
php artisan filament:upgrade
php artisan config:clear
php artisan cache:clear
```

Navigate to `/admin/assessments` in the browser. Open the action menu on a completed assessment — confirm "Mark Feedback Given" appears below "View Summary". Confirm it does NOT appear on draft/in-progress assessments.

- [ ] **Step 4: Test the action**

Click "Mark Feedback Given" on a completed assessment → confirm modal opens → submit → confirm notification fires and `feedback_given = 1` in DB.

```bash
php artisan tinker --execute="App\Models\Assessment::where('feedback_given', true)->count();"
```

Expected: 1 (or more if you tested multiple).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/AssessmentResource.php
git commit -m "feat: add Mark Feedback Given action to AssessmentResource"
```

---

## Self-Review Notes

**Spec coverage check:**
- ✅ Migration (Task 1)
- ✅ Model update (Task 2)  
- ✅ AssessmentAnalyticsService — all 4 methods (Tasks 3–5)
- ✅ Controller assessment branch (Task 6)
- ✅ Mentorship breakdown controller + route (Task 7)
- ✅ Mode toggle update (Task 8)
- ✅ KPI strip, insights, charts (Task 9)
- ✅ Power table with Alpine.js filters (Task 10)
- ✅ Drill-down page (Task 11)
- ✅ Layout verification (Task 12)
- ✅ Filament feedback action (Task 13)

**Type consistency:**
- `feedbackGivenBy()` in Assessment model (Task 2) matches `$assessment->feedbackGivenBy` in table view (Task 10) ✅
- `eligibility_status` set in `getFacilitiesReadiness()` (Task 5) matches `data-eligibility` in table (Task 10) ✅
- `has_skills_lab` boolean set in service (Task 5) matches `$assessment->has_skills_lab` in view (Task 10) ✅
- `mentorship_count` raw select in service (Task 5) matches `$assessment->mentorship_count` in view (Task 10) ✅
- `ClassAttendance` model name verified from codebase — used in Task 7 ✅
- Route name `analytics.dashboard.facility.mentorship-breakdown` registered in Task 7, referenced in Task 10 ✅
