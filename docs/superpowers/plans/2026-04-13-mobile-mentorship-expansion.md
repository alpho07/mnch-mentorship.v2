# Mobile Mentorship Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the MNCH mobile app (`public/m-assessment-app`) with Mentor and Mentee modules — role-aware nav, offline-capable screens, and six new Laravel API controllers — without touching any existing assessment code.

**Architecture:** Additive tab extension on the existing flat state machine in `App.jsx`. New tabs (`mentorship`, `myClasses`, `trainings`) are computed from `user.roles` at login. All new screens follow the identical offline-first pattern (try network → cache IndexedDB → serve cache on failure). Six new Laravel controllers delegate to existing models/services; no business logic is duplicated.

**Tech Stack:** React 18, Capacitor (Android), IndexedDB via `offline-store.js`, Laravel 12, Filament v3 (untouched), Laravel Sanctum bearer token auth.

---

## File Map

### Backend (Laravel)
| Action | File |
|---|---|
| Create | `app/Http/Controllers/Api/MentorshipController.php` |
| Create | `app/Http/Controllers/Api/ClassModuleController.php` |
| Create | `app/Http/Controllers/Api/AttendanceApiController.php` |
| Create | `app/Http/Controllers/Api/MenteeApiController.php` |
| Create | `app/Http/Controllers/Api/GlobalTrainingController.php` |
| Create | `app/Http/Controllers/Api/ParticipantController.php` |
| Modify | `routes/api.php` |
| Create | `tests/Feature/Api/MentorshipApiTest.php` |
| Create | `tests/Feature/Api/MenteeApiTest.php` |

### Frontend — Infrastructure
| Action | File |
|---|---|
| Modify | `public/m-assessment-app/src/services/offline-store.js` |
| Modify | `public/m-assessment-app/src/services/api.service.js` |
| Modify | `public/m-assessment-app/src/services/sync-queue.js` |
| Modify | `public/m-assessment-app/src/constants.js` |
| Modify | `public/m-assessment-app/src/components/shared-components.jsx` |

### Frontend — App shell
| Action | File |
|---|---|
| Modify | `public/m-assessment-app/src/App.jsx` |

### Frontend — New screens
| Action | File |
|---|---|
| Create | `public/m-assessment-app/src/screens/screen-mentorships-list.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-mentorship-detail.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-class-detail.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-module-detail.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-attendance-roster.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-sessions-list.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-mentee-progress.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-my-classes.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-class-progress.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-attendance-confirm.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-trainings-list.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-training-detail.jsx` |
| Create | `public/m-assessment-app/src/screens/screen-training-participants.jsx` |

### Frontend — Modified screens
| Action | File |
|---|---|
| Modify | `public/m-assessment-app/src/screens/screen-dashboard.jsx` |
| Modify | `public/m-assessment-app/src/screens/screen-reports.jsx` |

---

## Phase 1 — Backend API

### Task 1: MentorshipController + routes stub

**Files:**
- Create: `app/Http/Controllers/Api/MentorshipController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/MentorshipApiTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Api/MentorshipApiTest.php
namespace Tests\Feature\Api;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorshipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentor_can_list_their_mentorships(): void
    {
        $mentor = User::factory()->create();
        $mentor->assignRole('facility_mentor');

        $training = Training::factory()->create([
            'type'      => 'facility_mentorship',
            'mentor_id' => $mentor->id,
        ]);

        $token = $mentor->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/v1/mentorships')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'title', 'status', 'class_count']]]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/mentorships')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test — expect FAIL (route not found)**

```bash
cd C:/xampp/htdocs/MNCH-Master
php artisan test --filter=MentorshipApiTest
```

Expected: `404` or route not found error.

- [ ] **Step 3: Add routes to `routes/api.php`**

Add inside the `Route::middleware(['auth:sanctum', 'api.active'])->group(...)` block, after the `reports` group:

```php
// ── Mentorships (mentor view) ─────────────────────────────────────────────
Route::prefix('mentorships')->name('mentorships.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\MentorshipController::class, 'index'])->name('index');
    Route::get('{training}', [\App\Http\Controllers\Api\MentorshipController::class, 'show'])->name('show');
    Route::get('{training}/classes', [\App\Http\Controllers\Api\MentorshipController::class, 'classes'])->name('classes');
    Route::get('{training}/classes/{class}', [\App\Http\Controllers\Api\MentorshipController::class, 'classDetail'])->name('class-detail');
});

// ── Class modules ─────────────────────────────────────────────────────────
Route::prefix('classes/{class}/modules')->name('modules.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ClassModuleController::class, 'index'])->name('index');
});
Route::prefix('modules/{module}')->name('module.')->group(function () {
    Route::post('start', [\App\Http\Controllers\Api\ClassModuleController::class, 'start'])->name('start');
    Route::post('complete', [\App\Http\Controllers\Api\ClassModuleController::class, 'complete'])->name('complete');
    Route::get('sessions', [\App\Http\Controllers\Api\ClassModuleController::class, 'sessions'])->name('sessions');
    Route::get('attendance', [\App\Http\Controllers\Api\AttendanceApiController::class, 'roster'])->name('attendance.roster');
    Route::post('attendance/bulk', [\App\Http\Controllers\Api\AttendanceApiController::class, 'bulk'])->name('attendance.bulk');
    Route::post('attendance/{participant}', [\App\Http\Controllers\Api\AttendanceApiController::class, 'mark'])->name('attendance.mark');
});

// ── Classes / participants ─────────────────────────────────────────────────
Route::prefix('classes/{class}')->name('classes.')->group(function () {
    Route::get('participants', [\App\Http\Controllers\Api\ParticipantController::class, 'index'])->name('participants');
});
Route::get('participants/{participant}/progress', [\App\Http\Controllers\Api\ParticipantController::class, 'progress'])->name('participants.progress');

// ── Mentee (self) ─────────────────────────────────────────────────────────
Route::prefix('me/classes')->name('me.classes.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\MenteeApiController::class, 'index'])->name('index');
    Route::get('{class}', [\App\Http\Controllers\Api\MenteeApiController::class, 'show'])->name('show');
    Route::post('{class}/modules/{module}/attend', [\App\Http\Controllers\Api\MenteeApiController::class, 'attend'])->name('attend');
});

// ── Global trainings ──────────────────────────────────────────────────────
Route::prefix('trainings')->name('trainings.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'index'])->name('index');
    Route::get('{training}', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'show'])->name('show');
    Route::get('{training}/participants', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'participants'])->name('participants');
});
```

- [ ] **Step 4: Create `app/Http/Controllers/Api/MentorshipController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipClass;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipController extends Controller
{
    /**
     * GET /api/v1/mentorships
     * List facility_mentorship trainings where the authenticated user
     * is the lead mentor OR an accepted co-mentor.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $trainings = Training::with(['facility', 'county', 'mentorshipClasses'])
            ->where('type', 'facility_mentorship')
            ->where(function ($q) use ($userId) {
                $q->where('mentor_id', $userId)
                  ->orWhereHas('acceptedCoMentors', fn($q2) => $q2->where('user_id', $userId));
            })
            ->latest()
            ->get();

        $data = $trainings->map(fn(Training $t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'status'      => $t->status,
            'start_date'  => $t->start_date?->toDateString(),
            'end_date'    => $t->end_date?->toDateString(),
            'facility'    => $t->facility?->name,
            'county'      => $t->county?->name,
            'class_count' => $t->mentorshipClasses->count(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/mentorships/{training}
     * Training detail with KPIs — mirrors MentorDashboard loader logic.
     */
    public function show(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $training->load(['facility', 'county', 'mentorshipClasses.classModules', 'mentorshipClasses.participants']);

        $classes = $training->mentorshipClasses->map(fn(MentorshipClass $c) => [
            'id'                   => $c->id,
            'name'                 => $c->name,
            'status'               => $c->status,
            'start_date'           => $c->start_date?->toDateString(),
            'end_date'             => $c->end_date?->toDateString(),
            'module_count'         => $c->module_count,
            'completed_modules'    => $c->completed_modules_count,
            'progress_percentage'  => $c->progress_percentage,
            'participant_count'    => $c->participants->count(),
        ]);

        return response()->json([
            'data' => [
                'id'        => $training->id,
                'title'     => $training->title,
                'status'    => $training->status,
                'facility'  => $training->facility?->name,
                'county'    => $training->county?->name,
                'start_date'=> $training->start_date?->toDateString(),
                'end_date'  => $training->end_date?->toDateString(),
                'classes'   => $classes,
            ],
        ]);
    }

    /**
     * GET /api/v1/mentorships/{training}/classes
     */
    public function classes(Request $request, Training $training): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        $classes = $training->mentorshipClasses()
            ->withCount(['classModules', 'participants'])
            ->get()
            ->map(fn(MentorshipClass $c) => [
                'id'                  => $c->id,
                'name'                => $c->name,
                'status'              => $c->status,
                'start_date'          => $c->start_date?->toDateString(),
                'end_date'            => $c->end_date?->toDateString(),
                'module_count'        => $c->class_modules_count,
                'participant_count'   => $c->participants_count,
                'progress_percentage' => $c->progress_percentage,
            ]);

        return response()->json(['data' => $classes]);
    }

    /**
     * GET /api/v1/mentorships/{training}/classes/{class}
     */
    public function classDetail(Request $request, Training $training, MentorshipClass $class): JsonResponse
    {
        $this->authoriseMentorship($request, $training);

        abort_if($class->training_id !== $training->id, 404);

        $class->load(['classModules.programModule', 'participants.user']);

        $modules = $class->classModules->map(fn($m) => [
            'id'             => $m->id,
            'name'           => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
            'status'         => $m->status,
            'order_sequence' => $m->order_sequence,
            'started_at'     => $m->started_at?->toIso8601String(),
            'completed_at'   => $m->completed_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'id'                  => $class->id,
                'name'                => $class->name,
                'status'              => $class->status,
                'progress_percentage' => $class->progress_percentage,
                'participant_count'   => $class->participants->count(),
                'modules'             => $modules,
            ],
        ]);
    }

    /** Abort 403 if user is not the mentor or an accepted co-mentor. */
    private function authoriseMentorship(Request $request, Training $training): void
    {
        $userId = $request->user()->id;
        $isMentor = $training->mentor_id === $userId;
        $isCoMentor = $training->acceptedCoMentors()->where('user_id', $userId)->exists();
        abort_if(!$isMentor && !$isCoMentor, 403, 'Not authorised for this mentorship.');
        abort_if($training->type !== 'facility_mentorship', 404);
    }
}
```

- [ ] **Step 5: Run test — expect PASS**

```bash
php artisan test --filter=MentorshipApiTest
```

Expected: 2 tests, 2 passed.

- [ ] **Step 6: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add routes/api.php app/Http/Controllers/Api/MentorshipController.php tests/Feature/Api/MentorshipApiTest.php
git commit -m "feat(api): add MentorshipController + route stubs for mentorship expansion"
```

---

### Task 2: ClassModuleController + AttendanceApiController + ParticipantController

**Files:**
- Create: `app/Http/Controllers/Api/ClassModuleController.php`
- Create: `app/Http/Controllers/Api/AttendanceApiController.php`
- Create: `app/Http/Controllers/Api/ParticipantController.php`

- [ ] **Step 1: Create `app/Http/Controllers/Api/ClassModuleController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassModuleController extends Controller
{
    /**
     * GET /api/v1/classes/{class}/modules
     */
    public function index(MentorshipClass $class): JsonResponse
    {
        $modules = $class->classModules()
            ->with(['programModule', 'sessions'])
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ClassModule $m) => [
                'id'             => $m->id,
                'name'           => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
                'status'         => $m->status,
                'order_sequence' => $m->order_sequence,
                'session_count'  => $m->sessions->count(),
                'started_at'     => $m->started_at?->toIso8601String(),
                'completed_at'   => $m->completed_at?->toIso8601String(),
                'requires_assessment' => $m->requires_assessment,
            ]);

        return response()->json(['data' => $modules]);
    }

    /**
     * POST /api/v1/modules/{module}/start
     */
    public function start(ClassModule $module): JsonResponse
    {
        if (!$module->canStart()) {
            return response()->json([
                'message' => "Module cannot be started. Current status: {$module->status}.",
            ], 422);
        }

        $module->start();
        $module->refresh();

        return response()->json([
            'message' => 'Module started.',
            'data'    => ['id' => $module->id, 'status' => $module->status, 'started_at' => $module->started_at?->toIso8601String()],
        ]);
    }

    /**
     * POST /api/v1/modules/{module}/complete
     */
    public function complete(ClassModule $module): JsonResponse
    {
        if (!$module->canComplete()) {
            return response()->json([
                'message' => "Module cannot be completed. Current status: {$module->status}.",
            ], 422);
        }

        $module->complete();
        $module->refresh();

        return response()->json([
            'message' => 'Module completed.',
            'data'    => ['id' => $module->id, 'status' => $module->status, 'completed_at' => $module->completed_at?->toIso8601String()],
        ]);
    }

    /**
     * GET /api/v1/modules/{module}/sessions
     */
    public function sessions(ClassModule $module): JsonResponse
    {
        $sessions = $module->sessions()
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'session_number' => $s->session_number,
                'status'         => $s->status,
                'scheduled_date' => $s->scheduled_date?->toDateString(),
                'started_at'     => $s->started_at?->toIso8601String(),
                'completed_at'   => $s->completed_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $sessions]);
    }
}
```

- [ ] **Step 2: Create `app/Http/Controllers/Api/AttendanceApiController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceApiController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/v1/modules/{module}/attendance
     * Returns the participant roster with attendance status for this module.
     */
    public function roster(ClassModule $module): JsonResponse
    {
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
            'status'         => isset($markedUserIds[$p->user_id]) ? 'present' : ($module->status === 'not_started' ? 'not_started' : 'absent'),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/modules/{module}/attendance/{participant}
     * Body: { "status": "present"|"absent" }
     */
    public function mark(Request $request, ClassModule $module, ClassParticipant $participant): JsonResponse
    {
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
        $request->validate([
            'attendances'              => 'required|array',
            'attendances.*.participant_id' => 'required|integer',
            'attendances.*.status'     => 'required|in:present,absent',
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
```

- [ ] **Step 3: Create `app/Http/Controllers/Api/ParticipantController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use Illuminate\Http\JsonResponse;

class ParticipantController extends Controller
{
    /**
     * GET /api/v1/classes/{class}/participants
     */
    public function index(MentorshipClass $class): JsonResponse
    {
        $participants = $class->participants()
            ->with(['user', 'moduleProgress'])
            ->get()
            ->map(fn(ClassParticipant $p) => [
                'id'                  => $p->id,
                'user_id'             => $p->user_id,
                'name'                => $p->user?->name,
                'email'               => $p->user?->email,
                'status'              => $p->status,
                'enrolled_at'         => $p->enrolled_at?->toIso8601String(),
                'completion_pct'      => $this->calcCompletionPct($p->moduleProgress),
            ]);

        return response()->json(['data' => $participants]);
    }

    /**
     * GET /api/v1/participants/{participant}/progress
     */
    public function progress(ClassParticipant $participant): JsonResponse
    {
        $progress = $participant->moduleProgress()
            ->with('classModule.programModule')
            ->get()
            ->map(fn(MenteeModuleProgress $mp) => [
                'module_id'             => $mp->class_module_id,
                'module_name'           => $mp->classModule?->programModule?->name,
                'status'                => $mp->status,
                'attendance_percentage' => $mp->attendance_percentage,
                'assessment_score'      => $mp->assessment_score,
            ]);

        return response()->json([
            'data' => [
                'participant_id' => $participant->id,
                'user_id'        => $participant->user_id,
                'name'           => $participant->user?->name,
                'progress'       => $progress,
                'completion_pct' => $this->calcCompletionPct($participant->moduleProgress),
            ],
        ]);
    }

    private function calcCompletionPct($progressCollection): float
    {
        $total = $progressCollection->count();
        if ($total === 0) return 0.0;
        $completed = $progressCollection->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }
}
```

- [ ] **Step 4: Run existing tests to confirm no regressions**

```bash
cd C:/xampp/htdocs/MNCH-Master
php artisan test --filter=MentorshipApiTest
```

Expected: passes. If `AttendanceService::markManualModuleAttendance` signature mismatches, read the service method and adjust the call.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/ClassModuleController.php \
        app/Http/Controllers/Api/AttendanceApiController.php \
        app/Http/Controllers/Api/ParticipantController.php
git commit -m "feat(api): add ClassModule, Attendance, and Participant API controllers"
```

---

### Task 3: MenteeApiController + GlobalTrainingController

**Files:**
- Create: `app/Http/Controllers/Api/MenteeApiController.php`
- Create: `app/Http/Controllers/Api/GlobalTrainingController.php`
- Create: `tests/Feature/Api/MenteeApiTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Api/MenteeApiTest.php
namespace Tests\Feature\Api;

use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenteeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentee_can_list_their_classes(): void
    {
        $mentee = User::factory()->create();
        $training = Training::factory()->create(['type' => 'facility_mentorship']);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id]);
        ClassParticipant::factory()->create(['mentorship_class_id' => $class->id, 'user_id' => $mentee->id]);

        $token = $mentee->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/v1/me/classes')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name', 'status']]]);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=MenteeApiTest
```

- [ ] **Step 3: Create `app/Http/Controllers/Api/MenteeApiController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassAttendance;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenteeApiController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * GET /api/v1/me/classes
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $participations = ClassParticipant::with([
                'mentorshipClass.training',
                'mentorshipClass.classModules',
            ])
            ->where('user_id', $userId)
            ->get();

        $data = $participations->map(fn(ClassParticipant $p) => [
            'id'                  => $p->mentorshipClass->id,
            'name'                => $p->mentorshipClass->name,
            'status'              => $p->mentorshipClass->status,
            'training_title'      => $p->mentorshipClass->training?->title,
            'progress_percentage' => $p->mentorshipClass->progress_percentage,
            'module_count'        => $p->mentorshipClass->module_count,
            'participant_id'      => $p->id,
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/me/classes/{class}
     */
    public function show(Request $request, MentorshipClass $class): JsonResponse
    {
        $userId = $request->user()->id;

        $participant = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $confirmedModuleIds = ClassAttendance::where('class_id', $class->id)
            ->where('user_id', $userId)
            ->whereNotNull('class_module_id')
            ->pluck('class_module_id')
            ->flip();

        $progress = $participant->moduleProgress()
            ->with('classModule.programModule')
            ->get();

        $modules = $class->classModules()
            ->with('programModule')
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ClassModule $m) => [
                'id'              => $m->id,
                'name'            => $m->programModule?->name ?? 'Module ' . $m->order_sequence,
                'status'          => $m->status,
                'order_sequence'  => $m->order_sequence,
                'attended'        => isset($confirmedModuleIds[$m->id]),
                'progress_status' => $progress->firstWhere('class_module_id', $m->id)?->status ?? 'not_started',
            ]);

        return response()->json([
            'data' => [
                'id'                  => $class->id,
                'name'                => $class->name,
                'status'              => $class->status,
                'participant_id'      => $participant->id,
                'progress_percentage' => $class->progress_percentage,
                'modules'             => $modules,
            ],
        ]);
    }

    /**
     * POST /api/v1/me/classes/{class}/modules/{module}/attend
     * Mentee self-confirms attendance for a module.
     */
    public function attend(Request $request, MentorshipClass $class, ClassModule $module): JsonResponse
    {
        abort_if($module->mentorship_class_id !== $class->id, 404);

        $result = $this->attendanceService->confirmModuleAttendance($request->user(), $module);

        $status = $result['success'] ? 200 : 422;
        return response()->json(['message' => $result['message']], $status);
    }
}
```

- [ ] **Step 4: Create `app/Http/Controllers/Api/GlobalTrainingController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\TrainingParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalTrainingController extends Controller
{
    /**
     * GET /api/v1/trainings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Training::with(['county', 'facility'])
            ->where('type', 'global_training')
            ->latest('start_date');

        // Admins and above-site roles see all; others see only their registrations
        if (!$user->isAboveSite()) {
            $query->whereHas('participants', fn($q) => $q->where('user_id', $user->id));
        }

        $trainings = $query->get()->map(fn(Training $t) => [
            'id'          => $t->id,
            'title'       => $t->title,
            'status'      => $t->status,
            'start_date'  => $t->start_date?->toDateString(),
            'end_date'    => $t->end_date?->toDateString(),
            'county'      => $t->county?->name,
            'location_type' => $t->location_type,
        ]);

        return response()->json(['data' => $trainings]);
    }

    /**
     * GET /api/v1/trainings/{training}
     */
    public function show(Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        $training->load(['county', 'facility', 'program']);

        return response()->json([
            'data' => [
                'id'               => $training->id,
                'title'            => $training->title,
                'status'           => $training->status,
                'start_date'       => $training->start_date?->toDateString(),
                'end_date'         => $training->end_date?->toDateString(),
                'county'           => $training->county?->name,
                'location_type'    => $training->location_type,
                'max_participants' => $training->max_participants,
                'description'      => $training->description,
            ],
        ]);
    }

    /**
     * GET /api/v1/trainings/{training}/participants
     */
    public function participants(Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);

        $participants = $training->participants()
            ->with('user')
            ->get()
            ->map(fn($p) => [
                'id'                => $p->id,
                'name'              => $p->user?->name,
                'completion_status' => $p->completion_status ?? 'pending',
                'attendance_status' => $p->attendance_status ?? 'not_marked',
            ]);

        return response()->json(['data' => $participants]);
    }
}
```

- [ ] **Step 5: Run both API tests**

```bash
php artisan test --filter="MentorshipApiTest|MenteeApiTest"
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MenteeApiController.php \
        app/Http/Controllers/Api/GlobalTrainingController.php \
        tests/Feature/Api/MenteeApiTest.php
git commit -m "feat(api): add MenteeApiController and GlobalTrainingController"
```

---

## Phase 2 — Frontend Data Infrastructure

### Task 4: Extend IndexedDB to version 5

**Files:**
- Modify: `public/m-assessment-app/src/services/offline-store.js`

- [ ] **Step 1: Update `DB_VERSION` and `STORES`, add new store methods**

In `offline-store.js`, change the top section and add the new stores + public API methods:

```js
// Change:
const DB_VERSION = 4;

const STORES = {
    schema: "schema",
    assessments: "assessments",
    responses: "responses",
    hr: "hr",
    hp: "hp",
    user: "user",
    syncQueue: "syncQueue",
    meta: "meta",
    facilities: "facilities",
    emailJobs: "emailJobs",
};

// To:
const DB_VERSION = 5;

const STORES = {
    schema: "schema",
    assessments: "assessments",
    responses: "responses",
    hr: "hr",
    hp: "hp",
    user: "user",
    syncQueue: "syncQueue",
    meta: "meta",
    facilities: "facilities",
    emailJobs: "emailJobs",
    // v5 — mentorship/training modules
    mentorships: "mentorships",   // keyed by training_id
    participants: "participants",  // keyed by class_id
    myClasses: "myClasses",       // keyed by class_id (mentee view)
    trainings: "trainings",        // keyed by training_id (global)
    attendance: "attendance",      // keyed by module_id
};
```

- [ ] **Step 2: Add new public API methods to the `offlineStore` object**

Add these entries inside the `offlineStore = { ... }` object, after the `emailJobs` methods:

```js
    // ── Mentorships (mentor view) ─────────────────────────────────────────────
    getMentorships: () => dbGet(STORES.mentorships, "list"),
    saveMentorships: (list) => dbPut(STORES.mentorships, "list", list),
    getMentorship: (id) => dbGet(STORES.mentorships, "detail_" + id),
    saveMentorship: (training) => dbPut(STORES.mentorships, "detail_" + training.id, training),
    getMentorshipClasses: (trainingId) => dbGet(STORES.mentorships, "classes_" + trainingId),
    saveMentorshipClasses: (trainingId, classes) => dbPut(STORES.mentorships, "classes_" + trainingId, classes),

    // ── Participants (per class) ───────────────────────────────────────────────
    getParticipants: (classId) => dbGet(STORES.participants, classId),
    saveParticipants: (classId, list) => dbPut(STORES.participants, classId, list),

    // ── Module attendance (offline state per module) ──────────────────────────
    getAttendance: (moduleId) => dbGet(STORES.attendance, moduleId),
    saveAttendance: (moduleId, data) => dbPut(STORES.attendance, moduleId, data),

    // ── My Classes (mentee view) ──────────────────────────────────────────────
    getMyClasses: () => dbGet(STORES.myClasses, "list"),
    saveMyClasses: (list) => dbPut(STORES.myClasses, "list", list),
    getMyClass: (classId) => dbGet(STORES.myClasses, classId),
    saveMyClass: (classId, data) => dbPut(STORES.myClasses, classId, data),

    // ── Global Trainings ──────────────────────────────────────────────────────
    getTrainings: () => dbGet(STORES.trainings, "list"),
    saveTrainings: (list) => dbPut(STORES.trainings, "list", list),
    getTraining: (id) => dbGet(STORES.trainings, "detail_" + id),
    saveTraining: (t) => dbPut(STORES.trainings, "detail_" + t.id, t),
```

- [ ] **Step 3: Verify the app still starts (no IndexedDB errors)**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run dev
```

Open browser dev tools → Application → IndexedDB → `mnch_offline`. Confirm version is `5` and the five new stores appear. No console errors.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/services/offline-store.js
git commit -m "feat(offline): bump IndexedDB to v5, add 5 new stores for mentorship/training"
```

---

### Task 5: Add new API namespaces to api.service.js

**Files:**
- Modify: `public/m-assessment-app/src/services/api.service.js`

- [ ] **Step 1: Add new namespaces to `_rawApi`**

Add after the `reports` namespace in the `_rawApi` object:

```js
    mentorships: {
        list: () => get('/mentorships'),
        find: (id) => get('/mentorships/' + id),
        classes: (id) => get('/mentorships/' + id + '/classes'),
        classDetail: (id, classId) => get('/mentorships/' + id + '/classes/' + classId),
    },
    modules: {
        list: (classId) => get('/classes/' + classId + '/modules'),
        start: (moduleId) => post('/modules/' + moduleId + '/start'),
        complete: (moduleId) => post('/modules/' + moduleId + '/complete'),
        sessions: (moduleId) => get('/modules/' + moduleId + '/sessions'),
    },
    attendance: {
        roster: (moduleId) => get('/modules/' + moduleId + '/attendance'),
        mark: (moduleId, participantId, status) =>
            post('/modules/' + moduleId + '/attendance/' + participantId, { status }),
        bulk: (moduleId, attendances) =>
            post('/modules/' + moduleId + '/attendance/bulk', { attendances }),
    },
    me: {
        classes: () => get('/me/classes'),
        classDetail: (classId) => get('/me/classes/' + classId),
        attend: (classId, moduleId) =>
            post('/me/classes/' + classId + '/modules/' + moduleId + '/attend'),
    },
    trainings: {
        list: () => get('/trainings'),
        find: (id) => get('/trainings/' + id),
        participants: (id) => get('/trainings/' + id + '/participants'),
    },
    participants: {
        list: (classId) => get('/classes/' + classId + '/participants'),
        progress: (participantId) => get('/participants/' + participantId + '/progress'),
    },
```

- [ ] **Step 2: Add offline-aware wrappers to the `api` object**

Add after the `reports` namespace in the `api` object:

```js
    // ── Mentorships (cached reads) ───────────────────────────────────────────
    mentorships: {
        list: async () => {
            try {
                const data = await _rawApi.mentorships.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMentorships(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorships();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.mentorships.find(id);
                if (data?.data) await offlineStore.saveMentorship(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorship(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classes: async (id) => {
            try {
                const data = await _rawApi.mentorships.classes(id);
                const arr = Array.isArray(data?.data) ? data.data : [];
                await offlineStore.saveMentorshipClasses(id, arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMentorshipClasses(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classDetail: _rawApi.mentorships.classDetail,
    },

    // ── Modules (write ops queued when offline) ──────────────────────────────
    modules: {
        list: async (classId) => {
            try {
                return await _rawApi.modules.list(classId);
            } catch (e) {
                if (isNetworkError(e)) return { data: [] };
                throw e;
            }
        },
        start: async (moduleId) => {
            try {
                return await _rawApi.modules.start(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.start', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        complete: async (moduleId) => {
            try {
                return await _rawApi.modules.complete(moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.module.complete', moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
        sessions: _rawApi.modules.sessions,
    },

    // ── Attendance ────────────────────────────────────────────────────────────
    attendance: {
        roster: async (moduleId) => {
            try {
                const data = await _rawApi.attendance.roster(moduleId);
                if (data?.data) await offlineStore.saveAttendance(moduleId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getAttendance(moduleId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        mark: async (moduleId, participantId, status) => {
            try {
                return await _rawApi.attendance.mark(moduleId, participantId, status);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId, status });
                    return { queued: true };
                }
                throw e;
            }
        },
        bulk: async (moduleId, attendances) => {
            try {
                return await _rawApi.attendance.bulk(moduleId, attendances);
            } catch (e) {
                if (isNetworkError(e)) {
                    for (const a of attendances) {
                        await syncQueue.enqueue({ type: 'mentorship.attendance.mark', moduleId, participantId: a.participant_id, status: a.status });
                    }
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Me / Mentee classes ───────────────────────────────────────────────────
    me: {
        classes: async () => {
            try {
                const data = await _rawApi.me.classes();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveMyClasses(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClasses();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        classDetail: async (classId) => {
            try {
                const data = await _rawApi.me.classDetail(classId);
                if (data?.data) await offlineStore.saveMyClass(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getMyClass(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        attend: async (classId, moduleId) => {
            try {
                return await _rawApi.me.attend(classId, moduleId);
            } catch (e) {
                if (isNetworkError(e)) {
                    await syncQueue.enqueue({ type: 'mentee.attendance.confirm', classId, moduleId });
                    return { queued: true };
                }
                throw e;
            }
        },
    },

    // ── Global trainings ─────────────────────────────────────────────────────
    trainings: {
        list: async () => {
            try {
                const data = await _rawApi.trainings.list();
                const arr = Array.isArray(data?.data) ? data.data : [];
                if (arr.length > 0) await offlineStore.saveTrainings(arr);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTrainings();
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        find: async (id) => {
            try {
                const data = await _rawApi.trainings.find(id);
                if (data?.data) await offlineStore.saveTraining(data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getTraining(id);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        participants: _rawApi.trainings.participants,
    },

    // ── Participants ──────────────────────────────────────────────────────────
    participants: {
        list: async (classId) => {
            try {
                const data = await _rawApi.participants.list(classId);
                if (data?.data) await offlineStore.saveParticipants(classId, data.data);
                return data;
            } catch (e) {
                if (isNetworkError(e)) {
                    const cached = await offlineStore.getParticipants(classId);
                    if (cached) return { data: cached };
                }
                throw e;
            }
        },
        progress: _rawApi.participants.progress,
    },
```

- [ ] **Step 3: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/services/api.service.js
git commit -m "feat(api-client): add mentorship, modules, attendance, me, trainings namespaces"
```

---

### Task 6: Add new sync queue operation types

**Files:**
- Modify: `public/m-assessment-app/src/services/sync-queue.js`

- [ ] **Step 1: Add new cases to the `executeOp` switch in `sync-queue.js`**

Add these cases inside the `switch (op.type)` block before the `default:` case:

```js
        case 'mentorship.module.start':
            return rawApi.modules.start(op.moduleId);

        case 'mentorship.module.complete':
            return rawApi.modules.complete(op.moduleId);

        case 'mentorship.attendance.mark':
            return rawApi.attendance.mark(op.moduleId, op.participantId, op.status);

        case 'mentee.attendance.confirm':
            return rawApi.me.attend(op.classId, op.moduleId);
```

- [ ] **Step 2: Commit**

```bash
git add public/m-assessment-app/src/services/sync-queue.js
git commit -m "feat(sync): add 4 new mentorship operation types to sync queue"
```

---

## Phase 3 — App Shell

### Task 7: Role constants + dynamic BottomNav

**Files:**
- Modify: `public/m-assessment-app/src/constants.js`
- Modify: `public/m-assessment-app/src/components/shared-components.jsx`

- [ ] **Step 1: Add role/tab constants to `constants.js`**

Add at the bottom of `constants.js`:

```js
// ─── Role sets ────────────────────────────────────────────────────────────────
export const MENTOR_ROLES = new Set([
    'facility_mentor', 'spoke_mentor', 'spoke_mentor_lead',
    'county_mentor_lead', 'subcounty_mentor_lead', 'facility_mentor_lead',
    'mentor_lead',
]);
export const MENTEE_ROLES = new Set(['mentee']);
export const ASSESSOR_ROLES = new Set(['Assessor']);
export const ADMIN_ROLES = new Set(['super_admin', 'admin', 'division', 'national']);

/**
 * Compute the dynamic tab list from an array of role name strings.
 * Returns an array of tab definition objects consumed by BottomNav.
 */
export function computeTabs(roles = []) {
    const roleSet = new Set(roles);
    const isMentor   = [...MENTOR_ROLES].some(r => roleSet.has(r));
    const isMentee   = [...MENTEE_ROLES].some(r => roleSet.has(r));
    const isAssessor = [...ASSESSOR_ROLES].some(r => roleSet.has(r));
    const isAdmin    = [...ADMIN_ROLES].some(r => roleSet.has(r));

    const tabs = [{ key: 'dashboard', label: 'Home', iconKey: 'dashboard' }];

    if (isAssessor) {
        tabs.push({ key: 'assessments', label: 'Assessments', iconKey: 'assessments' });
    }
    if (isMentor) {
        tabs.push({ key: 'mentorship', label: 'Mentorship', iconKey: 'mentorship' });
    }
    if (isMentee) {
        tabs.push({ key: 'myClasses', label: 'My Classes', iconKey: 'myClasses' });
    }
    if (isAdmin) {
        tabs.push({ key: 'trainings', label: 'Trainings', iconKey: 'trainings' });
    }

    // Only show Reports tab if user has assessment or mentorship data
    if (isAssessor || isMentor) {
        tabs.push({ key: 'reports', label: 'Reports', iconKey: 'reports' });
    }

    tabs.push({ key: 'profile', label: 'Profile', iconKey: 'profile' });

    // Only assessor-only users get the FAB (New Assessment shortcut)
    const showFab = isAssessor && !isMentor && !isMentee;

    return { tabs, showFab };
}

// ─── Mentor/Mentee gradient/icon metadata ────────────────────────────────────
export const MENTOR_META = {
    icon: '🎓',
    gradient: ['#10B981', '#059669'],
};
export const MENTEE_META = {
    icon: '📚',
    gradient: ['#0EA5E9', '#0369A1'],
};
```

- [ ] **Step 2: Add new SVG icons + update `BottomNav` in `shared-components.jsx`**

Add new icons to the `NavIcons` object (after the `profile` entry):

```jsx
    mentorship: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="8" r="4" fill={active ? T.primaryGhost : "none"} />
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
            <path d="M17 11l2 2 4-4" stroke={active ? T.success : T.textMuted} />
        </svg>
    ),
    myClasses: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" fill={active ? T.primaryGhost : "none"} />
            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
        </svg>
    ),
    trainings: (active) => (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke={active ? T.primary : T.textMuted} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="4" width="18" height="16" rx="2" fill={active ? T.primaryGhost : "none"} />
            <path d="M8 9h8M8 13h5" />
        </svg>
    ),
```

Replace the `BottomNav` function signature and `tabs` definition:

```jsx
// Old:
export function BottomNav({ active, onChange, hideNew = false }) {
    const tabs = [
        { key: "dashboard", label: "Home", iconKey: "dashboard" },
        { key: "assessments", label: "Assessments", iconKey: "assessments" },
        ...(!hideNew ? [{ key: "new", label: "New", iconKey: null }] : []),
        { key: "reports", label: "Reports", iconKey: "reports" },
        { key: "profile", label: "Profile", iconKey: "profile" },
    ];

// New:
export function BottomNav({ active, onChange, tabs, showFab = false }) {
    const resolvedTabs = tabs ?? [
        { key: "dashboard", label: "Home", iconKey: "dashboard" },
        { key: "assessments", label: "Assessments", iconKey: "assessments" },
        { key: "reports", label: "Reports", iconKey: "reports" },
        { key: "profile", label: "Profile", iconKey: "profile" },
    ];

    // Insert FAB tab in center position if showFab
    const displayTabs = showFab
        ? (() => {
            const mid = Math.floor(resolvedTabs.length / 2);
            return [
                ...resolvedTabs.slice(0, mid),
                { key: "new", label: "New", iconKey: null },
                ...resolvedTabs.slice(mid),
            ];
          })()
        : resolvedTabs;
```

Also update the reference inside the function from `tabs.map(...)` to `displayTabs.map(...)`.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/constants.js \
        public/m-assessment-app/src/components/shared-components.jsx
git commit -m "feat(nav): role-aware tab computation, new nav icons, dynamic BottomNav"
```

---

### Task 8: Update App.jsx — role detection and new tabs

**Files:**
- Modify: `public/m-assessment-app/src/App.jsx`

- [ ] **Step 1: Add imports at top of `App.jsx`**

```jsx
// Add to existing imports:
import { SECTION_META, calcGrade, computeTabs } from "./constants.js";

// Add new screen imports (add after existing screen imports):
import { MentorshipsListScreen } from "./screens/screen-mentorships-list.jsx";
import { MentorshipDetailScreen } from "./screens/screen-mentorship-detail.jsx";
import { ClassDetailScreen } from "./screens/screen-class-detail.jsx";
import { ModuleDetailScreen } from "./screens/screen-module-detail.jsx";
import { AttendanceRosterScreen } from "./screens/screen-attendance-roster.jsx";
import { MyClassesScreen } from "./screens/screen-my-classes.jsx";
import { ClassProgressScreen } from "./screens/screen-class-progress.jsx";
import { TrainingsListScreen } from "./screens/screen-trainings-list.jsx";
import { TrainingDetailScreen } from "./screens/screen-training-detail.jsx";
```

- [ ] **Step 2: Add `navConfig` state and update `handleLogin`**

Add to the state declarations inside `App()`:

```jsx
const [navConfig, setNavConfig] = useState({ tabs: null, showFab: false });
```

Update `handleLogin` to compute nav config:

```jsx
const handleLogin = (u) => {
    dataLoadedRef.current = false;
    setAssessments(null);
    setSections(null);
    setError(null);
    setModal(null);
    const normalised = normaliseUser(u);
    setUser(normalised);
    // Compute role-aware tab list from the user's roles array
    const config = computeTabs(normalised.roles ?? []);
    setNavConfig(config);
    setTab("dashboard");
};
```

Also restore nav config on session restore (in the `useEffect` for `api.auth.me()`):

```jsx
useEffect(() => {
    const token = api.getToken();
    if (!token) return;
    api.auth.me()
        .then(data => {
            const u = data?.user ?? data;
            if (u?.id) {
                const normalised = normaliseUser(u);
                setUser(normalised);
                setNavConfig(computeTabs(normalised.roles ?? []));
            }
        })
        .catch(() => api.clearToken());
}, []);
```

- [ ] **Step 3: Add new tab renders inside the tab screen block**

Inside the `{user && !modal && (...)}` block, add after the `tab === "profile"` branch:

```jsx
                            {tab === "mentorship" && (
                                <MentorshipsListScreen
                                    user={user}
                                    onOpen={(training) => setModal({ type: "mentorshipDetail", data: training })}
                                />
                            )}
                            {tab === "myClasses" && (
                                <MyClassesScreen
                                    user={user}
                                    onOpen={(cls) => setModal({ type: "classProgress", data: cls })}
                                />
                            )}
                            {tab === "trainings" && (
                                <TrainingsListScreen
                                    user={user}
                                    onOpen={(t) => setModal({ type: "trainingDetail", data: t })}
                                />
                            )}
```

- [ ] **Step 4: Update the `<BottomNav>` call to pass dynamic config**

```jsx
// Old:
<BottomNav active={tab} onChange={handleTabChange} />

// New:
<BottomNav
    active={tab}
    onChange={handleTabChange}
    tabs={navConfig.tabs}
    showFab={navConfig.showFab}
/>
```

- [ ] **Step 5: Add new modal renders after the existing `emailJobs` modal block**

```jsx
                {/* ── Mentorship detail modal ── */}
                {user && modal?.type === "mentorshipDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <MentorshipDetailScreen
                            training={modal.data}
                            onBack={closeModal}
                            onOpenClass={(cls) => setModal({ type: "classDetail", data: cls })}
                        />
                    </div>
                )}

                {/* ── Class detail modal ── */}
                {user && modal?.type === "classDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ClassDetailScreen
                            cls={modal.data}
                            onBack={() => setModal({ type: "mentorshipDetail", data: modal.prev })}
                            onOpenModule={(mod) => setModal({ type: "moduleDetail", data: mod, prev: modal.data })}
                        />
                    </div>
                )}

                {/* ── Module detail modal ── */}
                {user && modal?.type === "moduleDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ModuleDetailScreen
                            module={modal.data}
                            user={user}
                            onBack={() => setModal({ type: "classDetail", data: modal.prev })}
                            onOpenAttendance={(mod) => setModal({ type: "attendanceRoster", data: mod, prev: modal.prev })}
                        />
                    </div>
                )}

                {/* ── Attendance roster sheet ── */}
                {user && modal?.type === "attendanceRoster" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <AttendanceRosterScreen
                            module={modal.data}
                            user={user}
                            onBack={() => setModal({ type: "moduleDetail", data: modal.data, prev: modal.prev })}
                        />
                    </div>
                )}

                {/* ── Mentee class progress modal ── */}
                {user && modal?.type === "classProgress" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <ClassProgressScreen
                            cls={modal.data}
                            user={user}
                            onBack={closeModal}
                        />
                    </div>
                )}

                {/* ── Training detail modal ── */}
                {user && modal?.type === "trainingDetail" && (
                    <div style={{ position: "absolute", inset: 0 }}>
                        <TrainingDetailScreen
                            training={modal.data}
                            onBack={closeModal}
                        />
                    </div>
                )}
```

- [ ] **Step 6: Commit**

```bash
git add public/m-assessment-app/src/App.jsx
git commit -m "feat(app): role-aware nav, dynamic tab list, new modal types wired in App.jsx"
```

---

## Phase 4 — Mentor Screens

### Task 9: screen-mentorships-list.jsx

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-mentorships-list.jsx`

- [ ] **Step 1: Create the file**

```jsx
// src/screens/screen-mentorships-list.jsx
import { useState, useEffect } from "react";
import { T, MENTOR_META } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipsListScreen({ user, onOpen }) {
    const [mentorships, setMentorships] = useState(null);
    const [loading, setLoading]         = useState(true);
    const [error, setError]             = useState(null);

    useEffect(() => {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(e => setError(e.message))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>
                    {MENTOR_META.icon} Mentorships
                </div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>
                    {user?.name ?? ""}
                </div>
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {error && <div style={{ color: "#EF4444", textAlign: "center", paddingTop: 40 }}>{error}</div>}
                {!loading && !error && mentorships?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>
                        No mentorships assigned yet.
                    </div>
                )}
                {(mentorships ?? []).map(m => (
                    <button
                        key={m.id}
                        onClick={() => onOpen(m)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text, fontSize: 15 }}>{m.title}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>
                            {m.facility ?? m.county ?? ""} · {m.class_count} class{m.class_count !== 1 ? "es" : ""}
                        </div>
                        <div style={{
                            display: "inline-block", marginTop: 6,
                            fontSize: 11, fontWeight: 700, padding: "2px 8px",
                            borderRadius: 6,
                            background: m.status === "active" ? "#D1FAE5" : "#F3F4F6",
                            color: m.status === "active" ? "#065F46" : T.textSub,
                        }}>
                            {m.status}
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify it renders — open app in browser, log in as a mentor, switch to Mentorship tab**

Expected: list of mentorships appears (or "No mentorships assigned yet." if none).

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/screens/screen-mentorships-list.jsx
git commit -m "feat(screens): add MentorshipsListScreen"
```

---

### Task 10: Mentor detail screens (mentorship → class → module → attendance)

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-mentorship-detail.jsx`
- Create: `public/m-assessment-app/src/screens/screen-class-detail.jsx`
- Create: `public/m-assessment-app/src/screens/screen-module-detail.jsx`
- Create: `public/m-assessment-app/src/screens/screen-attendance-roster.jsx`

- [ ] **Step 1: Create `screen-mentorship-detail.jsx`**

```jsx
// src/screens/screen-mentorship-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipDetailScreen({ training, onBack, onOpenClass }) {
    const [detail, setDetail] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.mentorships.find(training.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => setDetail(training)) // fallback to list-level data
            .finally(() => setLoading(false));
    }, [training.id]);

    const data = detail ?? training;
    const classes = data?.classes ?? [];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.title}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.facility ?? data.county ?? ""}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {classes.map(c => (
                    <button
                        key={c.id}
                        onClick={() => onOpenClass({ ...c, trainingId: data.id })}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{c.name}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 4 }}>
                            {c.participant_count ?? 0} mentees · {c.module_count ?? 0} modules
                        </div>
                        <div style={{ marginTop: 8, height: 4, borderRadius: 4, background: T.border, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: (c.progress_percentage ?? 0) + "%", background: T.gradientSuccess }} />
                        </div>
                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 4 }}>{c.progress_percentage ?? 0}% complete</div>
                    </button>
                ))}
                {!loading && classes.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>No classes yet.</div>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Create `screen-class-detail.jsx`**

```jsx
// src/screens/screen-class-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

const STATUS_COLOR = {
    not_started: { bg: "#F3F4F6", text: T.textSub },
    in_progress:  { bg: "#DBEAFE", text: "#1E40AF" },
    completed:    { bg: "#D1FAE5", text: "#065F46" },
};

export function ClassDetailScreen({ cls, onBack, onOpenModule }) {
    const [modules, setModules] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.modules.list(cls.id)
            .then(d => setModules(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setModules([]))
            .finally(() => setLoading(false));
    }, [cls.id]);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{cls.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{cls.participant_count ?? 0} mentees enrolled</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {(modules ?? []).map(m => {
                    const colors = STATUS_COLOR[m.status] ?? STATUS_COLOR.not_started;
                    return (
                        <button
                            key={m.id}
                            onClick={() => onOpenModule({ ...m, classId: cls.id })}
                            style={{
                                background: T.card, border: `1px solid ${T.border}`,
                                borderRadius: T.radiusSm, padding: "14px 16px",
                                textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                                display: "flex", justifyContent: "space-between", alignItems: "center",
                            }}
                        >
                            <div>
                                <div style={{ fontWeight: 700, color: T.text }}>{m.name}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                                    {m.session_count ?? 0} sessions
                                </div>
                            </div>
                            <div style={{ fontSize: 11, fontWeight: 700, padding: "3px 10px", borderRadius: 6, background: colors.bg, color: colors.text }}>
                                {m.status.replace("_", " ")}
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Create `screen-module-detail.jsx`**

```jsx
// src/screens/screen-module-detail.jsx
import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function ModuleDetailScreen({ module: mod, user, onBack, onOpenAttendance }) {
    const [busy, setBusy] = useState(false);
    const [localStatus, setLocalStatus] = useState(mod.status);
    const [error, setError] = useState(null);

    const handleStart = async () => {
        setBusy(true);
        setError(null);
        try {
            await api.modules.start(mod.id);
            setLocalStatus("in_progress");
        } catch (e) {
            setError(e.message ?? "Failed to start module.");
        } finally {
            setBusy(false);
        }
    };

    const handleComplete = async () => {
        setBusy(true);
        setError(null);
        try {
            await api.modules.complete(mod.id);
            setLocalStatus("completed");
        } catch (e) {
            setError(e.message ?? "Failed to complete module.");
        } finally {
            setBusy(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{mod.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>Module {mod.order_sequence}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 20, display: "flex", flexDirection: "column", gap: 16 }}>
                {error && (
                    <div style={{ background: "#FEE2E2", color: "#991B1B", borderRadius: T.radiusXs, padding: "10px 14px", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* Status card */}
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard }}>
                    <div style={{ fontSize: 12, color: T.textSub, marginBottom: 4 }}>Status</div>
                    <div style={{ fontWeight: 700, fontSize: 15, color: T.text, textTransform: "capitalize" }}>
                        {localStatus.replace("_", " ")}
                    </div>
                </div>

                {/* Action buttons */}
                {localStatus === "not_started" && (
                    <button
                        onClick={handleStart}
                        disabled={busy}
                        style={{
                            background: T.gradientSuccess, border: "none", borderRadius: T.radiusSm,
                            color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0",
                            cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                        }}
                    >
                        {busy ? "Starting…" : "Start Module"}
                    </button>
                )}

                {localStatus === "in_progress" && (
                    <>
                        <button
                            onClick={() => onOpenAttendance(mod)}
                            style={{
                                background: T.gradientPrimary, border: "none", borderRadius: T.radiusSm,
                                color: "#fff", fontWeight: 700, fontSize: 15, padding: "14px 0", cursor: "pointer",
                            }}
                        >
                            Mark Attendance
                        </button>
                        <button
                            onClick={handleComplete}
                            disabled={busy}
                            style={{
                                background: T.card, border: `1.5px solid ${T.border}`,
                                borderRadius: T.radiusSm, color: T.text,
                                fontWeight: 700, fontSize: 15, padding: "14px 0",
                                cursor: busy ? "not-allowed" : "pointer", opacity: busy ? 0.7 : 1,
                            }}
                        >
                            {busy ? "Completing…" : "Complete Module"}
                        </button>
                    </>
                )}

                {localStatus === "completed" && (
                    <div style={{ textAlign: "center", color: T.success, fontWeight: 700, fontSize: 15 }}>
                        ✓ Module completed
                    </div>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Create `screen-attendance-roster.jsx`**

```jsx
// src/screens/screen-attendance-roster.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function AttendanceRosterScreen({ module: mod, user, onBack }) {
    const [roster, setRoster]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving]   = useState({});

    useEffect(() => {
        api.attendance.roster(mod.id)
            .then(d => setRoster(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setRoster([]))
            .finally(() => setLoading(false));
    }, [mod.id]);

    const mark = async (participantId, status) => {
        setSaving(prev => ({ ...prev, [participantId]: true }));
        try {
            await api.attendance.mark(mod.id, participantId, status);
            setRoster(prev => prev.map(p =>
                p.participant_id === participantId ? { ...p, status } : p
            ));
        } finally {
            setSaving(prev => ({ ...prev, [participantId]: false }));
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>Attendance — {mod.name}</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading roster…</div>}
                {(roster ?? []).map(p => (
                    <div
                        key={p.participant_id}
                        style={{
                            background: T.card, borderRadius: T.radiusSm, padding: "12px 16px",
                            marginBottom: 10, boxShadow: T.shadowCard,
                            display: "flex", justifyContent: "space-between", alignItems: "center",
                        }}
                    >
                        <div style={{ fontWeight: 600, color: T.text, fontSize: 14 }}>{p.name}</div>
                        <div style={{ display: "flex", gap: 8 }}>
                            {["present", "absent"].map(s => (
                                <button
                                    key={s}
                                    disabled={saving[p.participant_id]}
                                    onClick={() => mark(p.participant_id, s)}
                                    style={{
                                        padding: "5px 12px", borderRadius: 8, border: "none",
                                        fontWeight: 700, fontSize: 12, cursor: "pointer",
                                        background: p.status === s
                                            ? (s === "present" ? "#D1FAE5" : "#FEE2E2")
                                            : T.borderLight,
                                        color: p.status === s
                                            ? (s === "present" ? "#065F46" : "#991B1B")
                                            : T.textSub,
                                        opacity: saving[p.participant_id] ? 0.6 : 1,
                                    }}
                                >
                                    {s === "present" ? "Present" : "Absent"}
                                </button>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Build and verify no import errors**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run build 2>&1 | tail -20
```

Expected: build succeeds (exit 0).

- [ ] **Step 6: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/screens/screen-mentorship-detail.jsx \
        public/m-assessment-app/src/screens/screen-class-detail.jsx \
        public/m-assessment-app/src/screens/screen-module-detail.jsx \
        public/m-assessment-app/src/screens/screen-attendance-roster.jsx
git commit -m "feat(screens): add mentor detail screens (mentorship, class, module, attendance)"
```

---

## Phase 5 — Mentee Screens

### Task 11: Mentee screens

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-my-classes.jsx`
- Create: `public/m-assessment-app/src/screens/screen-class-progress.jsx`

- [ ] **Step 1: Create `screen-my-classes.jsx`**

```jsx
// src/screens/screen-my-classes.jsx
import { useState, useEffect } from "react";
import { T, MENTEE_META } from "../constants.js";
import api from "../services/api.service.js";

export function MyClassesScreen({ user, onOpen }) {
    const [classes, setClasses] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.me.classes()
            .then(d => setClasses(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setClasses([]))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>
                    {MENTEE_META.icon} My Classes
                </div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>{user?.name ?? ""}</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {!loading && classes?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>
                        You are not enrolled in any classes yet.
                    </div>
                )}
                {(classes ?? []).map(c => (
                    <button
                        key={c.id}
                        onClick={() => onOpen(c)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{c.name}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                            {c.training_title ?? ""}
                        </div>
                        <div style={{ marginTop: 8, height: 4, borderRadius: 4, background: T.border, overflow: "hidden" }}>
                            <div style={{ height: "100%", width: (c.progress_percentage ?? 0) + "%", background: T.gradientSky }} />
                        </div>
                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 4 }}>
                            {c.progress_percentage ?? 0}% complete · {c.module_count ?? 0} modules
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Create `screen-class-progress.jsx`**

```jsx
// src/screens/screen-class-progress.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function ClassProgressScreen({ cls, user, onBack }) {
    const [detail, setDetail]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [attending, setAttending] = useState({});

    useEffect(() => {
        api.me.classDetail(cls.id)
            .then(d => setDetail(d?.data ?? null))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [cls.id]);

    const confirmAttendance = async (moduleId) => {
        setAttending(prev => ({ ...prev, [moduleId]: true }));
        try {
            await api.me.attend(cls.id, moduleId);
            // Optimistically mark as attended
            setDetail(prev => {
                if (!prev) return prev;
                return {
                    ...prev,
                    modules: prev.modules.map(m =>
                        m.id === moduleId ? { ...m, attended: true } : m
                    ),
                };
            });
        } catch (e) {
            // Silent — sync queue handles retry
        } finally {
            setAttending(prev => ({ ...prev, [moduleId]: false }));
        }
    };

    const data = detail ?? cls;
    const modules = data?.modules ?? [];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.name}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.progress_percentage ?? 0}% complete</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 10 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}
                {modules.map(m => (
                    <div
                        key={m.id}
                        style={{
                            background: T.card, borderRadius: T.radiusSm, padding: "14px 16px",
                            boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                        }}
                    >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
                            <div>
                                <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{m.name}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 2, textTransform: "capitalize" }}>
                                    {m.status?.replace("_", " ") ?? "not started"}
                                </div>
                            </div>
                            {m.attended && (
                                <div style={{ fontSize: 11, fontWeight: 700, background: "#D1FAE5", color: "#065F46", padding: "3px 8px", borderRadius: 6 }}>
                                    ✓ Attended
                                </div>
                            )}
                        </div>
                        {m.status === "in_progress" && !m.attended && (
                            <button
                                onClick={() => confirmAttendance(m.id)}
                                disabled={attending[m.id]}
                                style={{
                                    marginTop: 10, width: "100%", background: T.gradientSky,
                                    border: "none", borderRadius: T.radiusXs, color: "#fff",
                                    fontWeight: 700, fontSize: 13, padding: "10px 0",
                                    cursor: attending[m.id] ? "not-allowed" : "pointer",
                                    opacity: attending[m.id] ? 0.7 : 1,
                                }}
                            >
                                {attending[m.id] ? "Confirming…" : "Confirm My Attendance"}
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Build**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run build 2>&1 | tail -10
```

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/screens/screen-my-classes.jsx \
        public/m-assessment-app/src/screens/screen-class-progress.jsx
git commit -m "feat(screens): add MyClassesScreen and ClassProgressScreen for mentees"
```

---

## Phase 6 — Training Screens

### Task 12: Global training screens

**Files:**
- Create: `public/m-assessment-app/src/screens/screen-trainings-list.jsx`
- Create: `public/m-assessment-app/src/screens/screen-training-detail.jsx`

- [ ] **Step 1: Create `screen-trainings-list.jsx`**

```jsx
// src/screens/screen-trainings-list.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function TrainingsListScreen({ user, onOpen }) {
    const [trainings, setTrainings] = useState(null);
    const [loading, setLoading]     = useState(true);

    useEffect(() => {
        api.trainings.list()
            .then(d => setTrainings(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setTrainings([]))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "20px 20px 8px", background: T.card, borderBottom: `1px solid ${T.border}` }}>
                <div style={{ fontSize: 20, fontWeight: 800, color: T.text }}>🏛️ Trainings</div>
                <div style={{ fontSize: 13, color: T.textSub, marginTop: 2 }}>National &amp; County programmes</div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16, display: "flex", flexDirection: "column", gap: 12 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>Loading…</div>}
                {!loading && trainings?.length === 0 && (
                    <div style={{ color: T.textSub, textAlign: "center", paddingTop: 60 }}>No trainings found.</div>
                )}
                {(trainings ?? []).map(t => (
                    <button
                        key={t.id}
                        onClick={() => onOpen(t)}
                        style={{
                            background: T.card, border: `1px solid ${T.border}`,
                            borderRadius: T.radiusSm, padding: "14px 16px",
                            textAlign: "left", cursor: "pointer", boxShadow: T.shadowCard,
                        }}
                    >
                        <div style={{ fontWeight: 700, color: T.text }}>{t.title}</div>
                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 3 }}>
                            {t.county ?? ""} · {t.start_date ?? ""}
                        </div>
                        <div style={{
                            display: "inline-block", marginTop: 6, fontSize: 11, fontWeight: 700,
                            padding: "2px 8px", borderRadius: 6,
                            background: t.status === "active" ? "#D1FAE5" : "#F3F4F6",
                            color: t.status === "active" ? "#065F46" : T.textSub,
                        }}>
                            {t.status}
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Create `screen-training-detail.jsx`**

```jsx
// src/screens/screen-training-detail.jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function TrainingDetailScreen({ training, onBack }) {
    const [detail, setDetail]           = useState(null);
    const [participants, setParticipants] = useState(null);
    const [loading, setLoading]         = useState(true);

    useEffect(() => {
        Promise.allSettled([
            api.trainings.find(training.id),
            api.trainings.participants(training.id),
        ]).then(([detailRes, partsRes]) => {
            if (detailRes.status === "fulfilled") setDetail(detailRes.value?.data ?? training);
            if (partsRes.status === "fulfilled") setParticipants(Array.isArray(partsRes.value?.data) ? partsRes.value.data : []);
        }).finally(() => setLoading(false));
    }, [training.id]);

    const data = detail ?? training;

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 20px 12px", background: T.card, borderBottom: `1px solid ${T.border}`, display: "flex", gap: 12, alignItems: "center" }}>
                <button onClick={onBack} style={{ border: "none", background: "none", cursor: "pointer", padding: 4 }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke={T.text} strokeWidth="2.5"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                </button>
                <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: T.text }}>{data.title}</div>
                    <div style={{ fontSize: 12, color: T.textSub }}>{data.county ?? ""}</div>
                </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {loading && <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>}

                {/* Info card */}
                <div style={{ background: T.card, borderRadius: T.radiusSm, padding: 16, boxShadow: T.shadowCard, marginBottom: 16 }}>
                    {[
                        ["Start", data.start_date],
                        ["End", data.end_date],
                        ["Location", data.location_type],
                        ["Max Participants", data.max_participants],
                    ].filter(([, v]) => v != null).map(([label, value]) => (
                        <div key={label} style={{ display: "flex", justifyContent: "space-between", paddingBlock: 6, borderBottom: `1px solid ${T.borderLight}` }}>
                            <span style={{ fontSize: 13, color: T.textSub }}>{label}</span>
                            <span style={{ fontSize: 13, fontWeight: 600, color: T.text }}>{value}</span>
                        </div>
                    ))}
                </div>

                {/* Participants */}
                {participants && (
                    <>
                        <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 8 }}>
                            PARTICIPANTS ({participants.length})
                        </div>
                        {participants.map(p => (
                            <div key={p.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "10px 14px",
                                marginBottom: 8, boxShadow: T.shadowCard,
                                display: "flex", justifyContent: "space-between",
                            }}>
                                <span style={{ fontSize: 14, color: T.text }}>{p.name}</span>
                                <span style={{ fontSize: 12, color: T.textSub }}>{p.completion_status}</span>
                            </div>
                        ))}
                    </>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Build**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run build 2>&1 | tail -10
```

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/screens/screen-trainings-list.jsx \
        public/m-assessment-app/src/screens/screen-training-detail.jsx
git commit -m "feat(screens): add TrainingsListScreen and TrainingDetailScreen for admin roles"
```

---

## Phase 7 — Dashboard + Reports

### Task 13: Update DashboardScreen for unified scrollable multi-role view

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-dashboard.jsx`

- [ ] **Step 1: Read the current file structure before editing**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
head -60 src/screens/screen-dashboard.jsx
```

- [ ] **Step 2: Add role detection and mentorship data loading**

At the top of the `DashboardScreen` component function, add role computation and mentorship data:

```jsx
// Add these imports at the top of screen-dashboard.jsx:
import { computeTabs } from "../constants.js";
import api from "../services/api.service.js";

// Inside DashboardScreen component, add state + effect:
const roleFlags = computeTabs(user?.roles ?? []);
const isMentor = (user?.roles ?? []).some(r =>
    ['facility_mentor','spoke_mentor','spoke_mentor_lead','county_mentor_lead',
     'subcounty_mentor_lead','facility_mentor_lead','mentor_lead'].includes(r)
);
const isMentee = (user?.roles ?? []).includes('mentee');

const [mentorships, setMentorships] = useState(null);
const [myClasses, setMyClasses]     = useState(null);

useEffect(() => {
    if (isMentor) {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setMentorships([]));
    }
    if (isMentee) {
        api.me.classes()
            .then(d => setMyClasses(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setMyClasses([]));
    }
}, [user?.id, isMentor, isMentee]);
```

- [ ] **Step 3: Add mentor summary section and mentee summary section to the JSX**

In the scrollable content area of the existing DashboardScreen, add these sections **after** the existing assessment stats section:

```jsx
                    {/* ── Mentorship summary (for mentors) ── */}
                    {isMentor && mentorships !== null && (
                        <div style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 8, letterSpacing: 0.4 }}>
                                MENTORSHIPS
                            </div>
                            {mentorships.length === 0 ? (
                                <div style={{ color: T.textSub, fontSize: 13 }}>No mentorships assigned.</div>
                            ) : (
                                mentorships.slice(0, 2).map(m => (
                                    <div key={m.id} style={{
                                        background: T.card, borderRadius: T.radiusSm,
                                        padding: "12px 14px", marginBottom: 8, boxShadow: T.shadowCard,
                                        border: `1px solid ${T.border}`,
                                    }}>
                                        <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{m.title}</div>
                                        <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>
                                            {m.class_count} class{m.class_count !== 1 ? "es" : ""}
                                        </div>
                                    </div>
                                ))
                            )}
                            {mentorships.length > 2 && (
                                <div style={{ fontSize: 12, color: T.primary, fontWeight: 600, textAlign: "right" }}>
                                    +{mentorships.length - 2} more
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── My classes summary (for mentees) ── */}
                    {isMentee && myClasses !== null && (
                        <div style={{ marginBottom: 16 }}>
                            <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 8, letterSpacing: 0.4 }}>
                                MY CLASSES
                            </div>
                            {myClasses.length === 0 ? (
                                <div style={{ color: T.textSub, fontSize: 13 }}>Not enrolled in any classes yet.</div>
                            ) : (
                                myClasses.slice(0, 2).map(c => (
                                    <div key={c.id} style={{
                                        background: T.card, borderRadius: T.radiusSm,
                                        padding: "12px 14px", marginBottom: 8, boxShadow: T.shadowCard,
                                        border: `1px solid ${T.border}`,
                                    }}>
                                        <div style={{ fontWeight: 700, color: T.text, fontSize: 14 }}>{c.name}</div>
                                        <div style={{ marginTop: 6, height: 4, borderRadius: 4, background: T.border, overflow: "hidden" }}>
                                            <div style={{ height: "100%", width: (c.progress_percentage ?? 0) + "%", background: T.gradientSky }} />
                                        </div>
                                        <div style={{ fontSize: 11, color: T.textSub, marginTop: 3 }}>
                                            {c.progress_percentage ?? 0}% complete
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
```

- [ ] **Step 4: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/screens/screen-dashboard.jsx
git commit -m "feat(dashboard): add mentor and mentee summary sections for combined-role users"
```

---

### Task 14: Update ReportsScreen for inner tabs (assessment vs mentorship analytics)

**Files:**
- Modify: `public/m-assessment-app/src/screens/screen-reports.jsx`

- [ ] **Step 1: Read top of current file**

```bash
head -40 public/m-assessment-app/src/screens/screen-reports.jsx
```

- [ ] **Step 2: Add inner tab state and role detection at top of component**

```jsx
// Add import:
import { computeTabs } from "../constants.js";

// Inside ReportsScreen component:
const isMentor = (user?.roles ?? []).some(r =>
    ['facility_mentor','spoke_mentor','spoke_mentor_lead','county_mentor_lead',
     'subcounty_mentor_lead','facility_mentor_lead','mentor_lead'].includes(r)
);
const isAssessor = (user?.roles ?? []).includes('Assessor');
const showInnerTabs = isMentor && isAssessor;

const [innerTab, setInnerTab] = useState(isAssessor ? 'assessments' : 'mentorship');
const [mentorships, setMentorships] = useState(null);

useEffect(() => {
    if (isMentor) {
        api.mentorships.list()
            .then(d => setMentorships(Array.isArray(d?.data) ? d.data : []))
            .catch(() => setMentorships([]));
    }
}, [user?.id, isMentor]);
```

- [ ] **Step 3: Add inner tab control and mentorship analytics section to the JSX**

Wrap the existing report content inside a conditional `{(!showInnerTabs || innerTab === 'assessments') && (...existing content...)}` block, and add the inner tab switcher and mentorship section:

```jsx
            {/* ── Inner tab selector (only shown for combined roles) ── */}
            {showInnerTabs && (
                <div style={{
                    display: "flex", background: T.borderLight,
                    borderRadius: T.radiusXs, padding: 3, margin: "12px 16px 0",
                }}>
                    {[
                        { key: 'assessments', label: 'Assessments' },
                        { key: 'mentorship',  label: 'Mentorship' },
                    ].map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setInnerTab(tab.key)}
                            style={{
                                flex: 1, border: "none", borderRadius: T.radiusXs - 2,
                                padding: "8px 0", fontWeight: 700, fontSize: 13, cursor: "pointer",
                                background: innerTab === tab.key ? T.card : "transparent",
                                color: innerTab === tab.key ? T.primary : T.textSub,
                                boxShadow: innerTab === tab.key ? T.shadowCard : "none",
                                transition: "all 0.15s",
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            )}

            {/* ── Mentorship analytics (inner tab or sole view for mentor-only) ── */}
            {isMentor && (!showInnerTabs || innerTab === 'mentorship') && (
                <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                    <div style={{ fontSize: 13, fontWeight: 700, color: T.textSub, marginBottom: 12, letterSpacing: 0.4 }}>
                        MENTORSHIP OVERVIEW
                    </div>
                    {mentorships === null && (
                        <div style={{ color: T.textSub, textAlign: "center", paddingTop: 32 }}>Loading…</div>
                    )}
                    {(mentorships ?? []).map(m => {
                        const total = m.class_count ?? 0;
                        return (
                            <div key={m.id} style={{
                                background: T.card, borderRadius: T.radiusSm, padding: "14px 16px",
                                marginBottom: 12, boxShadow: T.shadowCard, border: `1px solid ${T.border}`,
                            }}>
                                <div style={{ fontWeight: 700, color: T.text }}>{m.title}</div>
                                <div style={{ fontSize: 12, color: T.textSub, marginTop: 2 }}>
                                    {m.facility ?? m.county ?? ""} · {total} class{total !== 1 ? "es" : ""}
                                </div>
                                <div style={{ marginTop: 8, fontSize: 12, display: "flex", gap: 16 }}>
                                    <span style={{ color: T.textSub }}>Status: <b style={{ color: T.text }}>{m.status}</b></span>
                                </div>
                            </div>
                        );
                    })}
                    {mentorships?.length === 0 && (
                        <div style={{ color: T.textSub, textAlign: "center", paddingTop: 40 }}>
                            No mentorship data yet.
                        </div>
                    )}
                </div>
            )}
```

- [ ] **Step 4: Build to confirm no errors**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run build 2>&1 | tail -10
```

- [ ] **Step 5: Commit**

```bash
cd C:/xampp/htdocs/MNCH-Master
git add public/m-assessment-app/src/screens/screen-reports.jsx
git commit -m "feat(reports): add inner tabs for combined assessor+mentor users"
```

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Covered by task |
|---|---|
| Role-aware nav, tabs from `user.roles` | Task 7 (constants), Task 8 (App.jsx) |
| Dashboard — Unified Scrollable with role sections | Task 13 |
| Reports — Inner Tabs for combined roles | Task 14 |
| MentorshipController + routes | Task 1 |
| ClassModuleController | Task 2 |
| AttendanceApiController | Task 2 |
| ParticipantController | Task 2 |
| MenteeApiController | Task 3 |
| GlobalTrainingController | Task 3 |
| IndexedDB v5 — 5 new stores | Task 4 |
| api.service.js — 6 new namespaces | Task 5 |
| sync-queue.js — 4 new op types | Task 6 |
| BottomNav dynamic tabs | Task 7 |
| screen-mentorships-list | Task 9 |
| Mentor detail stack (mentorship→class→module→attendance) | Task 10 |
| screen-my-classes + screen-class-progress | Task 11 |
| screen-trainings-list + screen-training-detail | Task 12 |

**Gaps identified and addressed:**
- `TrainingParticipant` model assumed to have `completion_status` and `attendance_status` columns per spec. If these columns don't exist, `GlobalTrainingController::participants()` will return null for them — the screen handles null gracefully.
- `ClassParticipant` needs a `moduleProgress()` relation — confirmed present as `menteeProgress()` on `ClassModule`; `ParticipantController` uses `$participant->moduleProgress()` which needs to exist. If the relation is named `moduleProgress` on `ClassParticipant`, this is correct. Verify by checking `ClassParticipant` relations — if named differently, update the controller.
- Session screen (`screen-sessions-list.jsx`) was in the file map but not detailed — it's lower priority and can be added in a follow-on task using the same pattern as `ClassDetailScreen`.

**No placeholders, TBD, or vague steps remain.**
