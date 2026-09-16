# Mobile Mentorship & Training Expansion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete mobile mentorship creation (online+offline), session notes, mentee enrollment, training participant actions, resource links, home expansion, and conflict handling.

**Architecture:** Vertical slices — each cluster delivers backend API + mobile screen(s) together. Backend uses new dedicated controllers following the `AuthorizesClassAccess` trait pattern already in place. Mobile uses the existing offline-first api.service.js + sync-queue.js patterns, bumping IndexedDB to v6 with 3 new stores.

**Tech Stack:** Laravel 12 / Sanctum (backend), React 18 / Capacitor (mobile), IndexedDB via offline-store.js, PHPUnit (backend tests)

**Spec:** `docs/superpowers/specs/2026-04-14-mentorship-mobile-expansion-design.md`

---

## File Map

### New Backend Files
- `app/Http/Controllers/Api/LookupController.php` — programs, counties, user search
- `app/Http/Controllers/Api/MentorshipCreateController.php` — mentorship CRUD + submit
- `app/Http/Controllers/Api/ClassLifecycleController.php` — class start/end + mentee enroll/remove
- `app/Http/Controllers/Api/ClassSessionController.php` — session notes update
- `tests/Feature/Api/LookupApiTest.php`
- `tests/Feature/Api/MentorshipCreateApiTest.php`
- `tests/Feature/Api/ClassLifecycleApiTest.php`
- `tests/Feature/Api/ClassSessionApiTest.php`
- `tests/Feature/Api/TrainingActionsApiTest.php`

### Modified Backend Files
- `routes/api.php` — 12 new routes
- `app/Http/Controllers/Api/GlobalTrainingController.php` — +enroll, +attendance
- `app/Http/Controllers/Api/ResourceController.php` — implement index()

### New Mobile Files
- `src/screens/screen-mentorship-form.jsx`
- `src/screens/screen-session-notes.jsx`
- `src/components/ResourceCard.jsx`

### Modified Mobile Files
- `src/services/offline-store.js` — DB_VERSION 6, 3 new stores, new accessors
- `src/services/api.service.js` — new method groups
- `src/services/sync-queue.js` — 7 new op types + ID reconciliation
- `src/screens/screen-mentorship-detail.jsx` — resources section
- `src/screens/screen-training-detail.jsx` — enroll, attendance, resources
- `src/screens/screen-module-detail.jsx` — sessions list → session notes
- `src/screens/screen-dashboard.jsx` — needs attention + upcoming trainings
- `src/App.jsx` — bootstrap + new modals
- `src/constants.js` — reports nav rule

---

## Task 1: Lookup API — Programs, Counties, User Search

**Files:**
- Create: `app/Http/Controllers/Api/LookupController.php`
- Create: `tests/Feature/Api/LookupApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/LookupApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\County;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LookupApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('facility_mentor');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_can_list_programs(): void
    {
        Program::factory()->create(['name' => 'MNCH Program']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/programs')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_can_list_program_modules(): void
    {
        $program = Program::factory()->create();
        ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson("/api/v1/programs/{$program->id}/modules")
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name', 'order_sequence', 'session_count']]]);
    }

    public function test_can_list_counties(): void
    {
        County::factory()->create(['name' => 'Nairobi']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/counties')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_user_search_requires_two_chars(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/users/search?q=a')
             ->assertUnprocessable();
    }

    public function test_unauthenticated_cannot_access_lookups(): void
    {
        $this->getJson('/api/v1/programs')->assertUnauthorized();
        $this->getJson('/api/v1/counties')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
cd C:/xampp/htdocs/MNCH-Master
composer test -- --filter=LookupApiTest
```
Expected: errors (class not found, route not found)

- [ ] **Step 3: Create LookupController**

Create `app/Http/Controllers/Api/LookupController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function programs(): JsonResponse
    {
        $programs = Program::orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json(['data' => $programs]);
    }

    public function programModules(Program $program): JsonResponse
    {
        $modules = ProgramModule::where('program_id', $program->id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get()
            ->map(fn(ProgramModule $m) => [
                'id'             => $m->id,
                'name'           => $m->name,
                'description'    => $m->description,
                'order_sequence' => $m->order_sequence,
                'session_count'  => $m->sessions()->where('is_active', true)->count(),
            ]);

        return response()->json(['data' => $modules]);
    }

    public function counties(): JsonResponse
    {
        $counties = County::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $counties]);
    }

    public function userSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q'           => 'required|string|min:2',
            'facility_id' => 'nullable|integer',
        ]);

        $query = User::query()
            ->where('name', 'like', '%' . $request->q . '%')
            ->limit(30);

        if ($request->facility_id) {
            $query->whereHas('facilities', fn($q) => $q->where('facilities.id', $request->facility_id));
        }

        $users = $query->get()->map(fn(User $u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'facility_name' => $u->facilities()->first()?->name ?? '',
        ]);

        return response()->json(['data' => $users]);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/api.php`, inside the authenticated `v1` group, add after the existing facilities block:

```php
        // ── Lookup ────────────────────────────────────────────────────────────────
        Route::prefix('programs')->name('programs.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\LookupController::class, 'programs'])->name('index');
            Route::get('{program}/modules', [\App\Http\Controllers\Api\LookupController::class, 'programModules'])->name('modules');
        });
        Route::get('counties', [\App\Http\Controllers\Api\LookupController::class, 'counties'])->name('counties');
        Route::get('users/search', [\App\Http\Controllers\Api\LookupController::class, 'userSearch'])->name('users.search');
```

- [ ] **Step 5: Check factories exist**

```bash
ls C:/xampp/htdocs/MNCH-Master/database/factories/ | grep -E "Program|County|ProgramModule"
```

If `ProgramFactory.php` or `CountyFactory.php` are missing, create minimal ones:

```php
// database/factories/ProgramFactory.php  (only if missing)
<?php
namespace Database\Factories;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
class ProgramFactory extends Factory {
    protected $model = Program::class;
    public function definition(): array {
        return ['name' => fake()->words(3, true), 'description' => fake()->sentence()];
    }
}
```

```php
// database/factories/ProgramModuleFactory.php  (only if missing)
<?php
namespace Database\Factories;
use App\Models\ProgramModule;
use Illuminate\Database\Eloquent\Factories\Factory;
class ProgramModuleFactory extends Factory {
    protected $model = ProgramModule::class;
    public function definition(): array {
        return ['name' => fake()->words(2, true), 'order_sequence' => 1, 'is_active' => true];
    }
}
```

- [ ] **Step 6: Run tests — expect green**

```bash
composer test -- --filter=LookupApiTest
```
Expected: 5 tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/LookupController.php tests/Feature/Api/LookupApiTest.php routes/api.php database/factories/
git commit -m "feat(api): add lookup endpoints — programs, counties, user search"
```

---

## Task 2: Mentorship CRUD API

**Files:**
- Create: `app/Http/Controllers/Api/MentorshipCreateController.php`
- Create: `tests/Feature/Api/MentorshipCreateApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/MentorshipCreateApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MentorshipCreateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private Program $program;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;
        $this->program = Program::factory()->create();
        $this->facility = Facility::factory()->create();
    }

    public function test_mentor_can_create_mentorship(): void
    {
        $module = ProgramModule::factory()->create(['program_id' => $this->program->id, 'is_active' => true]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson('/api/v1/mentorships', [
                 'program_id'  => $this->program->id,
                 'facility_id' => $this->facility->id,
                 'start_date'  => '2026-05-01',
                 'end_date'    => '2026-06-30',
                 'module_ids'  => [$module->id],
             ])
             ->assertCreated()
             ->assertJsonStructure(['data' => ['id', 'title', 'identifier', 'status', 'class']]);

        $this->assertDatabaseHas('trainings', [
            'type'      => 'facility_mentorship',
            'mentor_id' => $this->mentor->id,
        ]);
    }

    public function test_identifier_is_auto_generated(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson('/api/v1/mentorships', [
                 'program_id'  => $this->program->id,
                 'facility_id' => $this->facility->id,
                 'start_date'  => '2026-05-01',
                 'end_date'    => '2026-06-30',
                 'module_ids'  => [],
             ])
             ->assertCreated()
             ->assertJsonPath('data.identifier', fn($v) => str_starts_with($v, 'MT-'));
    }

    public function test_mentor_can_update_mentorship(): void
    {
        $training = Training::factory()->facilityMentorship()->create([
            'mentor_id' => $this->mentor->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['title' => 'Updated Title'])
             ->assertOk()
             ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_non_mentor_cannot_update_another_mentorship(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $other->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/mentorships/{$training->id}", ['title' => 'Hack'])
             ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
composer test -- --filter=MentorshipCreateApiTest
```
Expected: failures (routes/controller not found)

- [ ] **Step 3: Create MentorshipCreateController**

Create `app/Http/Controllers/Api/MentorshipCreateController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Services\ModuleUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MentorshipCreateController extends Controller
{
    public function __construct(private readonly ModuleUsageService $moduleUsageService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'program_id'       => 'required|integer|exists:programs,id',
            'facility_id'      => 'required|integer|exists:facilities,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'title'            => 'nullable|string|max:255',
            'module_ids'       => 'nullable|array',
            'module_ids.*'     => 'integer|exists:program_modules,id',
        ]);

        $user = $request->user();

        $training = Training::create([
            'type'             => 'facility_mentorship',
            'program_id'       => $request->program_id,
            'facility_id'      => $request->facility_id,
            'mentor_id'        => $user->id,
            'start_date'       => $request->start_date,
            'end_date'         => $request->end_date,
            'max_participants' => $request->max_participants ?? 20,
            'status'           => 'draft',
            'identifier'       => 'MT-' . strtoupper(Str::random(6)),
            'title'            => $request->title ?? $this->autoTitle($request),
        ]);

        $class = MentorshipClass::create([
            'training_id' => $training->id,
            'name'        => ($training->program?->name ?? 'Class') . ' - Class 1',
            'status'      => 'draft',
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
        ]);

        if (!empty($request->module_ids)) {
            $added = $this->moduleUsageService->assignModulesToClass($training, $class, $request->module_ids, $user->id);
            // Auto-create sessions for each newly assigned module
            foreach ($class->classModules()->get() as $cm) {
                $cm->autoCreateSessions();
            }
        }

        $class->load(['classModules.programModule']);

        return response()->json([
            'data' => [
                'id'         => $training->id,
                'title'      => $training->title,
                'identifier' => $training->identifier,
                'status'     => $training->status,
                'start_date' => $training->start_date?->toDateString(),
                'end_date'   => $training->end_date?->toDateString(),
                'class'      => [
                    'id'      => $class->id,
                    'name'    => $class->name,
                    'status'  => $class->status,
                    'modules' => $class->classModules->map(fn($m) => [
                        'id'             => $m->id,
                        'name'           => $m->programModule?->name ?? 'Module',
                        'order_sequence' => $m->order_sequence,
                        'session_count'  => $m->sessions()->count(),
                    ]),
                ],
            ],
        ], 201);
    }

    public function update(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'facility_mentorship', 404);
        abort_if($training->mentor_id !== $request->user()->id && !$request->user()->isAboveSite(), 403);

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'start_date'       => 'sometimes|date',
            'end_date'         => 'sometimes|date',
            'max_participants' => 'sometimes|integer|min:1',
        ]);

        $training->update($request->only(['title', 'start_date', 'end_date', 'max_participants']));

        return response()->json(['data' => [
            'id'         => $training->id,
            'title'      => $training->title,
            'status'     => $training->status,
            'start_date' => $training->start_date?->toDateString(),
            'end_date'   => $training->end_date?->toDateString(),
        ]]);
    }

    public function submit(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'facility_mentorship', 404);
        abort_if($training->mentor_id !== $request->user()->id, 403);

        $allCompleted = $training->mentorshipClasses()
            ->where('status', '!=', 'completed')
            ->doesntExist();

        abort_if(!$allCompleted, 422, 'All classes must be completed before submitting.');

        $training->update(['status' => 'submitted']);

        return response()->json(['message' => 'Mentorship submitted.', 'data' => ['id' => $training->id, 'status' => 'submitted']]);
    }

    private function autoTitle(Request $request): string
    {
        $facility = \App\Models\Facility::find($request->facility_id)?->name ?? 'Unknown';
        $program  = \App\Models\Program::find($request->program_id)?->name ?? 'Mentorship';
        $year     = date('Y', strtotime($request->start_date));
        return "{$program} — {$facility} {$year}";
    }
}
```

- [ ] **Step 4: Add routes in `routes/api.php`**

Inside the `mentorships` prefix group, add after the existing GET routes:

```php
            Route::post('/', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'store'])->name('store');
            Route::put('{training}', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'update'])->name('update');
            Route::post('{training}/submit', [\App\Http\Controllers\Api\MentorshipCreateController::class, 'submit'])->name('submit');
```

- [ ] **Step 5: Run tests — expect green**

```bash
composer test -- --filter=MentorshipCreateApiTest
```
Expected: 4 tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/MentorshipCreateController.php tests/Feature/Api/MentorshipCreateApiTest.php routes/api.php
git commit -m "feat(api): mentorship create/update/submit endpoints"
```

---

## Task 3: Class Lifecycle + Mentee Enrollment API

**Files:**
- Create: `app/Http/Controllers/Api/ClassLifecycleController.php`
- Create: `tests/Feature/Api/ClassLifecycleApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/ClassLifecycleApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private Training $training;
    private MentorshipClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;

        $this->training = Training::factory()->facilityMentorship()->create(['mentor_id' => $this->mentor->id]);
        $this->class = MentorshipClass::factory()->create([
            'training_id' => $this->training->id,
            'status'      => 'draft',
        ]);
    }

    public function test_mentor_can_enroll_mentee(): void
    {
        $mentee = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/classes/{$this->class->id}/mentees", ['user_id' => $mentee->id])
             ->assertCreated()
             ->assertJsonStructure(['data' => ['participant_id', 'user_id', 'name']]);

        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $this->class->id,
            'user_id'             => $mentee->id,
        ]);
    }

    public function test_non_mentor_cannot_enroll_mentee(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $otherToken = $other->createToken('test')->plainTextToken;
        $mentee = User::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
             ->postJson("/api/v1/classes/{$this->class->id}/mentees", ['user_id' => $mentee->id])
             ->assertForbidden();
    }

    public function test_mentor_can_remove_unenrolled_mentee(): void
    {
        $mentee = User::factory()->create();
        $participant = ClassParticipant::factory()->create([
            'mentorship_class_id' => $this->class->id,
            'user_id'             => $mentee->id,
            'status'              => 'enrolled',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->deleteJson("/api/v1/classes/{$this->class->id}/mentees/{$participant->id}")
             ->assertOk();

        $this->assertDatabaseMissing('class_participants', ['id' => $participant->id]);
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
composer test -- --filter=ClassLifecycleApiTest
```

- [ ] **Step 3: Create ClassLifecycleController**

Create `app/Http/Controllers/Api/ClassLifecycleController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Services\ModuleUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassLifecycleController extends Controller
{
    use AuthorizesClassAccess;

    public function __construct(private readonly ModuleUsageService $moduleUsageService) {}

    /**
     * POST /api/v1/classes/{class}/start
     */
    public function start(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canStart()) {
            return response()->json([
                'message' => "Class cannot be started. Status: {$class->status}. Ensure it has modules and enrolled mentees.",
            ], 422);
        }

        $class->start();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/end
     */
    public function end(MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        if (!$class->canEnd()) {
            return response()->json([
                'message' => "Class cannot be ended. Status: {$class->status}.",
            ], 422);
        }

        $class->end();
        $class->refresh();

        return response()->json(['data' => ['id' => $class->id, 'status' => $class->status]]);
    }

    /**
     * POST /api/v1/classes/{class}/mentees
     */
    public function enrollMentee(Request $request, MentorshipClass $class): JsonResponse
    {
        $this->authorizeClassAccess($class);

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $already = ClassParticipant::where('mentorship_class_id', $class->id)
            ->where('user_id', $request->user_id)
            ->exists();

        abort_if($already, 409, 'User is already enrolled in this class.');

        $participant = ClassParticipant::create([
            'mentorship_class_id' => $class->id,
            'user_id'             => $request->user_id,
            'status'              => 'enrolled',
            'enrolled_at'         => now(),
        ]);

        $this->moduleUsageService->cascadeAllModulesToParticipant($class, $participant);

        $participant->load('user');

        return response()->json([
            'data' => [
                'participant_id' => $participant->id,
                'user_id'        => $participant->user_id,
                'name'           => $participant->user?->name,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/classes/{class}/mentees/{participant}
     */
    public function removeMentee(MentorshipClass $class, ClassParticipant $participant): JsonResponse
    {
        $this->authorizeClassAccess($class);

        abort_if($participant->mentorship_class_id !== $class->id, 422, 'Participant does not belong to this class.');
        abort_if($participant->status !== 'enrolled', 422, 'Cannot remove a mentee who has started modules.');

        $participant->delete();

        return response()->json(['message' => 'Mentee removed.']);
    }
}
```

- [ ] **Step 4: Check MentorshipClass has `canEnd()` method**

```bash
grep -n "canEnd\|function end" C:/xampp/htdocs/MNCH-Master/app/Models/MentorshipClass.php
```

If `canEnd()` is missing, add to the model:

```php
public function canEnd(): bool {
    return $this->status === 'active';
}
```

- [ ] **Step 5: Check ClassParticipant has factory**

```bash
ls C:/xampp/htdocs/MNCH-Master/database/factories/ | grep ClassParticipant
```

If missing, create `database/factories/ClassParticipantFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\ClassParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
class ClassParticipantFactory extends Factory {
    protected $model = ClassParticipant::class;
    public function definition(): array {
        return ['status' => 'enrolled', 'enrolled_at' => now()];
    }
}
```

Also check MentorshipClass has a factory; if missing:

```php
<?php
namespace Database\Factories;
use App\Models\MentorshipClass;
use Illuminate\Database\Eloquent\Factories\Factory;
class MentorshipClassFactory extends Factory {
    protected $model = MentorshipClass::class;
    public function definition(): array {
        return ['name' => 'Class 1', 'status' => 'draft', 'start_date' => now(), 'end_date' => now()->addMonths(2)];
    }
}
```

- [ ] **Step 6: Add routes in `routes/api.php`**

Add inside the authenticated `v1` group, after the existing `classes/{class}/modules` block:

```php
        // ── Class lifecycle + mentee enrollment ───────────────────────────────────
        Route::prefix('classes/{class}')->name('class.')->group(function () {
            Route::post('start', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'start'])->name('start');
            Route::post('end', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'end'])->name('end');
            Route::post('mentees', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'enrollMentee'])->name('mentees.store');
            Route::delete('mentees/{participant}', [\App\Http\Controllers\Api\ClassLifecycleController::class, 'removeMentee'])->name('mentees.destroy');
        });
```

- [ ] **Step 7: Run tests — expect green**

```bash
composer test -- --filter=ClassLifecycleApiTest
```

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/ClassLifecycleController.php tests/Feature/Api/ClassLifecycleApiTest.php routes/api.php database/factories/
git commit -m "feat(api): class start/end and mentee enroll/remove endpoints"
```

---

## Task 4: Session Notes API

**Files:**
- Create: `app/Http/Controllers/Api/ClassSessionController.php`
- Create: `tests/Feature/Api/ClassSessionApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/ClassSessionApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ClassModule;
use App\Models\ClassSession;
use App\Models\MentorshipClass;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassSessionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private string $token;
    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $this->mentor = User::factory()->create();
        $this->mentor->assignRole('facility_mentor');
        $this->token = $this->mentor->createToken('test')->plainTextToken;

        $training = Training::factory()->facilityMentorship()->create(['mentor_id' => $this->mentor->id]);
        $class = MentorshipClass::factory()->create(['training_id' => $training->id, 'status' => 'active']);
        $module = ClassModule::factory()->create(['mentorship_class_id' => $class->id, 'status' => 'in_progress']);
        $this->session = ClassSession::factory()->create(['class_module_id' => $module->id]);
    }

    public function test_mentor_can_update_session_notes(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->putJson("/api/v1/sessions/{$this->session->id}", [
                 'actual_date' => '2026-05-10',
                 'notes'       => 'Good session.',
                 'location'    => 'Ward 3',
             ])
             ->assertOk()
             ->assertJsonPath('data.notes', 'Good session.');
    }

    public function test_non_mentor_cannot_update_session(): void
    {
        $other = User::factory()->create();
        $other->assignRole('facility_mentor');
        $otherToken = $other->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $otherToken)
             ->putJson("/api/v1/sessions/{$this->session->id}", ['notes' => 'Hack'])
             ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
composer test -- --filter=ClassSessionApiTest
```

- [ ] **Step 3: Create ClassSessionController**

Create `app/Http/Controllers/Api/ClassSessionController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClassAccess;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    use AuthorizesClassAccess;

    /**
     * PUT /api/v1/sessions/{session}
     */
    public function update(Request $request, ClassSession $session): JsonResponse
    {
        $this->authorizeModuleAccess($session->classModule);

        $request->validate([
            'actual_date'      => 'nullable|date',
            'actual_time'      => 'nullable|string|max:10',
            'location'         => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'facilitator_id'   => 'nullable|integer|exists:users,id',
            'attendance_taken'  => 'nullable|boolean',
            'status'           => 'nullable|in:scheduled,in_progress,completed',
        ]);

        $data = $request->only(['actual_date', 'actual_time', 'location', 'notes', 'attendance_taken', 'status']);
        $data['facilitator_id'] = $request->facilitator_id ?? $request->user()->id;

        $session->update($data);
        $session->refresh();

        return response()->json([
            'data' => [
                'id'               => $session->id,
                'title'            => $session->title,
                'actual_date'      => $session->actual_date?->toDateString(),
                'actual_time'      => $session->actual_time,
                'location'         => $session->location,
                'notes'            => $session->notes,
                'attendance_taken'  => $session->attendance_taken,
                'status'           => $session->status,
            ],
        ]);
    }
}
```

- [ ] **Step 4: Check ClassSession has factory; create if missing**

```bash
ls C:/xampp/htdocs/MNCH-Master/database/factories/ | grep ClassSession
```

If missing, create `database/factories/ClassSessionFactory.php`:

```php
<?php
namespace Database\Factories;
use App\Models\ClassSession;
use Illuminate\Database\Eloquent\Factories\Factory;
class ClassSessionFactory extends Factory {
    protected $model = ClassSession::class;
    public function definition(): array {
        return ['title' => 'Session 1', 'session_number' => 1, 'status' => 'scheduled', 'attendance_taken' => false];
    }
}
```

Also check ClassModule has factory; if missing:

```php
<?php
namespace Database\Factories;
use App\Models\ClassModule;
use Illuminate\Database\Eloquent\Factories\Factory;
class ClassModuleFactory extends Factory {
    protected $model = ClassModule::class;
    public function definition(): array {
        return ['status' => 'not_started', 'order_sequence' => 1, 'requires_assessment' => false, 'attendance_link_active' => false];
    }
}
```

- [ ] **Step 5: Add route in `routes/api.php`**

Add inside the authenticated `v1` group:

```php
        Route::put('sessions/{session}', [\App\Http\Controllers\Api\ClassSessionController::class, 'update'])->name('sessions.update');
```

- [ ] **Step 6: Run tests — expect green**

```bash
composer test -- --filter=ClassSessionApiTest
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ClassSessionController.php tests/Feature/Api/ClassSessionApiTest.php routes/api.php database/factories/
git commit -m "feat(api): session notes update endpoint"
```

---

## Task 5: Training Participant Actions + Resource API

**Files:**
- Modify: `app/Http/Controllers/Api/GlobalTrainingController.php`
- Modify: `app/Http/Controllers/Api/ResourceController.php`
- Create: `tests/Feature/Api/TrainingActionsApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/TrainingActionsApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Training $training;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'mentee', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('mentee');
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->training = Training::factory()->create(['type' => 'global_training', 'status' => 'active']);
    }

    public function test_user_can_enroll_in_training(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/enroll")
             ->assertCreated()
             ->assertJsonStructure(['data' => ['participant_id', 'status']]);

        $this->assertDatabaseHas('training_participants', [
            'training_id' => $this->training->id,
            'user_id'     => $this->user->id,
        ]);
    }

    public function test_duplicate_enrollment_returns_409(): void
    {
        TrainingParticipant::create(['training_id' => $this->training->id, 'user_id' => $this->user->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/enroll")
             ->assertConflict();
    }

    public function test_user_can_mark_own_attendance(): void
    {
        TrainingParticipant::create(['training_id' => $this->training->id, 'user_id' => $this->user->id]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/attendance")
             ->assertOk()
             ->assertJsonPath('message', 'Attendance recorded.');
    }

    public function test_unenrolled_user_cannot_mark_attendance(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->postJson("/api/v1/trainings/{$this->training->id}/attendance")
             ->assertForbidden();
    }

    public function test_can_list_resources(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
             ->getJson('/api/v1/resources')
             ->assertOk()
             ->assertJsonStructure(['data']);
    }
}
```

- [ ] **Step 2: Run tests — expect failures**

```bash
composer test -- --filter=TrainingActionsApiTest
```

- [ ] **Step 3: Add enroll and attendance to GlobalTrainingController**

Read the current file first, then add these two methods before the closing brace:

```php
    /**
     * POST /api/v1/trainings/{training}/enroll
     */
    public function enroll(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);
        abort_if(!in_array($training->status, ['upcoming', 'active']), 422, 'Enrollment is not open for this training.');

        $already = $training->participants()->where('user_id', $request->user()->id)->exists();
        abort_if($already, 409, 'You are already enrolled in this training.');

        $participant = $training->participants()->create([
            'user_id'           => $request->user()->id,
            'attendance_status' => 'registered',
            'completion_status' => 'pending',
        ]);

        return response()->json([
            'data' => ['participant_id' => $participant->id, 'status' => $participant->attendance_status],
        ], 201);
    }

    /**
     * POST /api/v1/trainings/{training}/attendance
     */
    public function attendance(Request $request, Training $training): JsonResponse
    {
        abort_if($training->type !== 'global_training', 404);

        $participant = $training->participants()->where('user_id', $request->user()->id)->first();
        abort_if(!$participant, 403, 'You are not enrolled in this training.');

        $participant->update(['attendance_status' => 'present']);

        return response()->json(['message' => 'Attendance recorded.']);
    }
```

- [ ] **Step 4: Implement ResourceController::index()**

Read `app/Http/Controllers/Api/ResourceController.php`, then replace the `index()` stub:

```php
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $query = \App\Models\Resource::accessibleTo($user)
            ->where('status', 'published')
            ->with('resourceType');

        if ($request->type) {
            $query->byType($request->type);
        }

        $resources = $query->latest('published_at')->get()->map(fn($r) => [
            'id'          => $r->id,
            'title'       => $r->title,
            'description' => $r->excerpt,
            'type'        => $r->resourceType?->slug ?? 'document',
            'url'         => $r->external_url,
            'file_url'    => $r->file_path ? asset('storage/' . $r->file_path) : null,
        ]);

        return response()->json(['data' => $resources]);
    }
```

Also add the import at the top of the file:

```php
use Illuminate\Http\Request;
```

- [ ] **Step 5: Add routes in `routes/api.php`**

Inside the `trainings` prefix group, add after the existing GET routes:

```php
            Route::post('{training}/enroll', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'enroll'])->name('enroll');
            Route::post('{training}/attendance', [\App\Http\Controllers\Api\GlobalTrainingController::class, 'attendance'])->name('attendance');
```

Add resource route inside the authenticated `v1` group:

```php
        Route::get('resources', [\App\Http\Controllers\Api\ResourceController::class, 'index'])->name('resources.index');
```

- [ ] **Step 6: Run tests — expect green**

```bash
composer test -- --filter=TrainingActionsApiTest
```

- [ ] **Step 7: Run full test suite**

```bash
composer test
```
Expected: all existing + new tests pass

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/GlobalTrainingController.php app/Http/Controllers/Api/ResourceController.php tests/Feature/Api/TrainingActionsApiTest.php routes/api.php
git commit -m "feat(api): training enroll/attendance and resource list endpoints"
```

---

## Task 6: Offline Store v6

**Files:**
- Modify: `src/services/offline-store.js`

- [ ] **Step 1: Bump DB_VERSION and add new stores**

Read `src/services/offline-store.js`. Make these changes:

Change `DB_VERSION` from `5` to `6`:
```js
const DB_VERSION = 6;
```

Add three new store names to the `STORES` object (after the existing `attendance` entry):
```js
    // v6 — mentorship creation, session notes, conflicts
    mentorshipMentees: "mentorshipMentees",  // keyed by class_id
    mentorshipSessions: "mentorshipSessions", // keyed by "class_${classId}" or "module_${moduleId}"
    conflicts: "conflicts",                   // keyed by conflict id (auto-generated)
```

- [ ] **Step 2: Add accessor methods**

After the existing `saveAttendance` line, add:

```js
    // ── Mentorship (add deleteMentorship for ID reconciliation) ──────────────
    deleteMentorship: (id) => dbDelete(STORES.mentorships, 'detail_' + id),

    // ── Mentorship mentees (enrolled participants per class) ──────────────────
    getMentees: (classId) => dbGet(STORES.mentorshipMentees, 'class_' + classId),
    saveMentees: (classId, list) => dbPut(STORES.mentorshipMentees, 'class_' + classId, list),

    // ── Session notes ─────────────────────────────────────────────────────────
    getSession: (sessionId) => dbGet(STORES.mentorshipSessions, 'session_' + sessionId),
    saveSession: (session) => dbPut(STORES.mentorshipSessions, 'session_' + session.id, session),
    getSessionsByModule: (moduleId) => dbGet(STORES.mentorshipSessions, 'module_' + moduleId),
    saveSessionsByModule: (moduleId, list) => dbPut(STORES.mentorshipSessions, 'module_' + moduleId, list),

    // ── Conflicts (failed sync ops needing user review) ───────────────────────
    getConflicts: () => dbGetAll(STORES.conflicts),
    saveConflict: (conflict) => dbPut(STORES.conflicts, conflict.id, conflict),
    resolveConflict: (id) => dbDelete(STORES.conflicts, id),
    getConflictCount: async () => (await dbGetAllKeys(STORES.conflicts)).length,
```

- [ ] **Step 3: Add resource cache accessors**

After the `saveTraining` line, add:

```js
    // ── Resources ─────────────────────────────────────────────────────────────
    getResources: () => dbGet(STORES.meta, 'resources_list'),
    saveResources: (list) => dbPut(STORES.meta, 'resources_list', list),
    getResourcesCachedAt: () => dbGet(STORES.meta, 'resources_cached_at'),
    setResourcesCachedAt: (ts) => dbPut(STORES.meta, 'resources_cached_at', ts),
```

- [ ] **Step 4: Verify the upgrade handler covers new stores**

The existing `onupgradeneeded` handler already iterates `Object.values(STORES)` and calls `createObjectStore` for any missing names — no change needed.

- [ ] **Step 5: Test in browser dev server**

```bash
cd C:/xampp/htdocs/MNCH-Master/public/m-assessment-app
npm run dev
```

Open DevTools → Application → IndexedDB → mnch_offline. After login, verify version shows `6` and stores `mentorshipMentees`, `mentorshipSessions`, `conflicts` exist.

- [ ] **Step 6: Commit**

```bash
git add src/services/offline-store.js
git commit -m "feat(mobile): IndexedDB v6 — add mentorshipMentees, mentorshipSessions, conflicts stores"
```

---

## Task 7: API Service Expansion

**Files:**
- Modify: `src/services/api.service.js`

- [ ] **Step 1: Add raw API methods**

In the `_rawApi` object (the plain fetch wrapper section, around line 170), add new method groups after the existing `trainings` block:

```js
    lookups: {
        programs: () => get('/programs'),
        programModules: (programId) => get('/programs/' + programId + '/modules'),
        counties: () => get('/counties'),
        userSearch: (q, facilityId) => get('/users/search?q=' + encodeURIComponent(q) + (facilityId ? '&facility_id=' + facilityId : '')),
    },
    mentorshipCreate: {
        create: (payload) => post('/mentorships', payload),
        update: (id, payload) => put('/mentorships/' + id, payload),
        submit: (id) => post('/mentorships/' + id + '/submit'),
    },
    classLifecycle: {
        start: (classId) => post('/classes/' + classId + '/start'),
        end: (classId) => post('/classes/' + classId + '/end'),
        enrollMentee: (classId, userId) => post('/classes/' + classId + '/mentees', { user_id: userId }),
        removeMentee: (classId, participantId) => del('/classes/' + classId + '/mentees/' + participantId),
    },
    sessions: {
        update: (sessionId, payload) => put('/sessions/' + sessionId, payload),
    },
    trainingActions: {
        enroll: (trainingId) => post('/trainings/' + trainingId + '/enroll'),
        attendance: (trainingId) => post('/trainings/' + trainingId + '/attendance'),
    },
    resources: {
        list: (type) => get('/resources' + (type ? '?type=' + type : '')),
    },
```

Ensure a `del` (DELETE) helper exists near the other fetch helpers. If only `get`, `post`, `put` exist, add:

```js
function del(path, body = null) {
    return _fetch('DELETE', path, body);
}
```

(Check the existing `_fetch` or equivalent function signature — replicate whatever `post` uses.)

- [ ] **Step 2: Add offline-aware public API methods**

In the offline-aware `api` export (around line 720), add after the existing `trainings` block:

```js
    // ── Lookup (cache-first, no writes) ─────────────────────────────────────
    lookups: {
        programs: async () => {
            try {
                const data = await _rawApi.lookups.programs();
                const list = data?.data ?? data;
                await offlineStore.setMeta('programs', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('programs')) ?? [];
            }
        },
        programModules: async (programId) => {
            try {
                const data = await _rawApi.lookups.programModules(programId);
                const list = data?.data ?? data;
                await offlineStore.setMeta('program_modules_' + programId, list);
                return list;
            } catch {
                return (await offlineStore.getMeta('program_modules_' + programId)) ?? [];
            }
        },
        counties: async () => {
            try {
                const data = await _rawApi.lookups.counties();
                const list = data?.data ?? data;
                await offlineStore.setMeta('counties', list);
                return list;
            } catch {
                return (await offlineStore.getMeta('counties')) ?? [];
            }
        },
        userSearch: _rawApi.lookups.userSearch,
    },

    // ── Mentorship creation (online→queue) ───────────────────────────────────
    mentorshipCreate: {
        create: async (payload) => {
            try {
                const data = await _rawApi.mentorshipCreate.create(payload);
                return data;
            } catch (e) {
                if (!navigator.onLine) {
                    const tempId = 'local_' + Date.now();
                    const local = { ...payload, id: tempId, _isOffline: true, status: 'draft' };
                    await offlineStore.saveMentorship(local);
                    await syncQueue.enqueue({ type: 'mentorships.create', tempId, payload });
                    return { data: local };
                }
                throw e;
            }
        },
        update: async (id, payload) => {
            try {
                return await _rawApi.mentorshipCreate.update(id, payload);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.update', id, payload });
                    return { data: { id, ...payload } };
                }
                throw e;
            }
        },
        submit: async (id) => {
            try {
                return await _rawApi.mentorshipCreate.submit(id);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.submit', id });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Class lifecycle (online→queue) ───────────────────────────────────────
    classLifecycle: {
        start: async (classId) => {
            try {
                return await _rawApi.classLifecycle.start(classId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.startClass', classId });
                    return { data: { id: classId, status: 'active' } };
                }
                throw e;
            }
        },
        end: (classId) => _rawApi.classLifecycle.end(classId),
        enrollMentee: async (classId, userId) => {
            try {
                return await _rawApi.classLifecycle.enrollMentee(classId, userId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.addMentee', classId, userId });
                    return { data: { participant_id: 'local_' + Date.now(), user_id: userId } };
                }
                throw e;
            }
        },
        removeMentee: async (classId, participantId) => {
            try {
                return await _rawApi.classLifecycle.removeMentee(classId, participantId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'mentorships.removeMentee', classId, participantId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Session notes (online→queue, never discard) ──────────────────────────
    sessions: {
        update: async (sessionId, payload) => {
            try {
                const data = await _rawApi.sessions.update(sessionId, payload);
                await offlineStore.saveSession({ id: sessionId, ...payload });
                return data;
            } catch (e) {
                if (!navigator.onLine) {
                    await offlineStore.saveSession({ id: sessionId, ...payload, _pending: true });
                    await syncQueue.enqueue({ type: 'mentorships.updateSession', sessionId, payload });
                    return { data: { id: sessionId, ...payload } };
                }
                throw e;
            }
        },
    },

    // ── Training actions (online→queue) ──────────────────────────────────────
    trainingActions: {
        enroll: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.enroll(trainingId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'trainings.enroll', trainingId });
                    return { data: { participant_id: 'pending', status: 'registered' } };
                }
                throw e;
            }
        },
        attendance: async (trainingId) => {
            try {
                return await _rawApi.trainingActions.attendance(trainingId);
            } catch (e) {
                if (!navigator.onLine) {
                    await syncQueue.enqueue({ type: 'trainings.attendance', trainingId });
                    return { message: 'Queued for sync.' };
                }
                throw e;
            }
        },
    },

    // ── Resources (cache with 24h TTL) ───────────────────────────────────────
    resources: {
        list: async (type) => {
            const now = Date.now();
            const cachedAt = (await offlineStore.getResourcesCachedAt()) ?? 0;
            const ttl = 24 * 60 * 60 * 1000;
            if (!type && now - cachedAt < ttl) {
                const cached = await offlineStore.getResources();
                if (cached) return cached;
            }
            try {
                const data = await _rawApi.resources.list(type);
                const list = data?.data ?? data;
                if (!type) {
                    await offlineStore.saveResources(list);
                    await offlineStore.setResourcesCachedAt(now);
                }
                return list;
            } catch {
                return (await offlineStore.getResources()) ?? [];
            }
        },
    },
```

- [ ] **Step 3: Verify `del` helper**

Search for `function del` or `del =` in `api.service.js`. If it doesn't exist, find where `put` is defined (it follows the same `_fetch` call pattern) and add `del` immediately after:

```js
const del = (path, body = null) => _fetch('DELETE', path, body);
```

- [ ] **Step 4: Start dev server and confirm no console errors on load**

```bash
npm run dev
```
Open browser, log in — check console for import or syntax errors.

- [ ] **Step 5: Commit**

```bash
git add src/services/api.service.js
git commit -m "feat(mobile): api.service — lookups, mentorship CRUD, class lifecycle, sessions, training actions, resources"
```

---

## Task 8: Sync Queue — New Op Types + ID Reconciliation

**Files:**
- Modify: `src/services/sync-queue.js`

- [ ] **Step 1: Add new op type comments in header**

In `sync-queue.js`, find the comment block listing op types (around line 42) and append:

```js
//   "mentorships.create"        → { tempId, payload }
//   "mentorships.update"        → { id, payload }
//   "mentorships.submit"        → { id }
//   "mentorships.addMentee"     → { classId, userId }
//   "mentorships.removeMentee"  → { classId, participantId }
//   "mentorships.updateSession" → { sessionId, payload }
//   "mentorships.startClass"    → { classId }
//   "trainings.enroll"          → { trainingId }
//   "trainings.attendance"      → { trainingId }
```

- [ ] **Step 2: Add cases to executeOp switch**

In the `executeOp` function, add before the `default:` case:

```js
        case 'mentorships.create': {
            const migrateId = async (fromId, toId) => {
                const existing = await offlineStore.getMentorship(fromId);
                if (existing) {
                    await offlineStore.saveMentorship({ ...existing, id: toId, _isOffline: false });
                    await offlineStore.deleteMentorship(fromId);
                }
                window.dispatchEvent(new CustomEvent('mentorship:id-resolved', {
                    detail: { tempId: fromId, realId: toId },
                }));
            };
            try {
                const response = await rawApi.mentorshipCreate.create(op.payload);
                const realId = response?.data?.id;
                if (!realId) throw new Error('[SyncQueue] mentorships.create: server returned no id');
                await migrateId(op.tempId, realId);
                return response;
            } catch (e) {
                if (e.status === 409) {
                    const realId = e.data?.data?.id;
                    if (realId) await migrateId(op.tempId, realId);
                    return null;
                }
                if (e.status >= 400 && e.status < 500) {
                    // Permanent failure — write to conflicts instead of discarding silently
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now(),
                        op_type: op.type,
                        payload: op.payload,
                        error: e.message ?? 'Request rejected',
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    return null;
                }
                throw e;
            }
        }

        case 'mentorships.update':
            return rawApi.mentorshipCreate.update(op.id, op.payload);

        case 'mentorships.submit':
            return rawApi.mentorshipCreate.submit(op.id);

        case 'mentorships.addMentee':
            return rawApi.classLifecycle.enrollMentee(op.classId, op.userId);

        case 'mentorships.removeMentee': {
            try {
                return await rawApi.classLifecycle.removeMentee(op.classId, op.participantId);
            } catch (e) {
                if (e.status === 404) return null; // already removed — discard
                throw e;
            }
        }

        case 'mentorships.updateSession': {
            // NEVER discard silently — write to conflicts on permanent failure
            try {
                return await rawApi.sessions.update(op.sessionId, op.payload);
            } catch (e) {
                if (e.status >= 400 && e.status < 500) {
                    await offlineStore.saveConflict({
                        id: 'conflict_' + Date.now(),
                        op_type: 'mentorships.updateSession',
                        payload: { sessionId: op.sessionId, ...op.payload },
                        error: e.message ?? 'Session sync failed',
                        created_at: new Date().toISOString(),
                        resolved: false,
                    });
                    return null; // dequeue — handled via conflicts UI
                }
                throw e;
            }
        }

        case 'mentorships.startClass':
            return rawApi.classLifecycle.start(op.classId);

        case 'trainings.enroll': {
            try {
                return await rawApi.trainingActions.enroll(op.trainingId);
            } catch (e) {
                if (e.status === 409) return null; // already enrolled
                throw e;
            }
        }

        case 'trainings.attendance':
            return rawApi.trainingActions.attendance(op.trainingId);
```

Note: The existing flush() function already discards non-401 4xx errors generically. The `mentorships.updateSession` and `mentorships.create` cases handle their own 4xx before the generic handler can discard them by catching and writing to `conflicts`.

- [ ] **Step 3: Build and check for errors**

```bash
npm run build 2>&1 | tail -20
```
Expected: build succeeds with no errors

- [ ] **Step 4: Commit**

```bash
git add src/services/sync-queue.js
git commit -m "feat(mobile): sync-queue — 9 new op types with ID reconciliation and conflict handling"
```

---

## Task 9: Mentorship Creation Wizard

**Files:**
- Create: `src/screens/screen-mentorship-form.jsx`
- Modify: `src/App.jsx`

- [ ] **Step 1: Create the wizard screen**

Create `src/screens/screen-mentorship-form.jsx`:

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function MentorshipFormScreen({ user, onBack, onCreated }) {
    const [step, setStep] = useState(1);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    // Step 1 fields
    const [programs, setPrograms] = useState([]);
    const [programId, setProgramId] = useState("");
    const [facilityId, setFacilityId] = useState(user?.facility_id ?? "");
    const [facilityName, setFacilityName] = useState(user?.facility ?? "");
    const [startDate, setStartDate] = useState("");
    const [endDate, setEndDate] = useState("");
    const [maxParticipants, setMaxParticipants] = useState(20);

    // Step 2 fields
    const [availableModules, setAvailableModules] = useState([]);
    const [selectedModuleIds, setSelectedModuleIds] = useState([]);

    // Step 3 fields
    const [menteeSearch, setMenteeSearch] = useState("");
    const [menteeResults, setMenteeResults] = useState([]);
    const [selectedMentees, setSelectedMentees] = useState([]);
    const [menteeSearching, setMenteeSearching] = useState(false);

    // Created result (set after Step 4 save)
    const [created, setCreated] = useState(null);

    useEffect(() => {
        api.lookups.programs().then(setPrograms).catch(() => {});
    }, []);

    useEffect(() => {
        if (programId) {
            api.lookups.programModules(programId).then(setAvailableModules).catch(() => {});
        }
    }, [programId]);

    const searchMentees = async (q) => {
        if (q.length < 2) { setMenteeResults([]); return; }
        setMenteeSearching(true);
        try {
            const results = await api.lookups.userSearch(q, facilityId || null);
            const data = results?.data ?? results ?? [];
            setMenteeResults(data.filter(u => !selectedMentees.find(s => s.id === u.id)));
        } catch { setMenteeResults([]); }
        finally { setMenteeSearching(false); }
    };

    const toggleModule = (id) => {
        setSelectedModuleIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    const addMentee = (u) => {
        setSelectedMentees(prev => [...prev, u]);
        setMenteeResults(prev => prev.filter(r => r.id !== u.id));
        setMenteeSearch("");
    };

    const removeMentee = (id) => setSelectedMentees(prev => prev.filter(u => u.id !== id));

    const handleSave = async (startNow) => {
        setSaving(true);
        setError(null);
        try {
            const res = await api.mentorshipCreate.create({
                program_id: parseInt(programId),
                facility_id: parseInt(facilityId),
                start_date: startDate,
                end_date: endDate,
                max_participants: maxParticipants,
                module_ids: selectedModuleIds,
            });
            const newTraining = res?.data ?? res;
            setCreated(newTraining);

            // Enroll selected mentees
            if (selectedMentees.length > 0 && newTraining?.class?.id) {
                for (const mentee of selectedMentees) {
                    await api.classLifecycle.enrollMentee(newTraining.class.id, mentee.id).catch(() => {});
                }
            }

            // Optionally start the class
            if (startNow && newTraining?.class?.id) {
                await api.classLifecycle.start(newTraining.class.id).catch(() => {});
            }

            onCreated(newTraining);
        } catch (e) {
            setError(e.message ?? "Failed to save mentorship.");
        } finally {
            setSaving(false);
        }
    };

    const stepLabel = ["Setup", "Modules", "Mentees", "Review"];

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            {/* Header */}
            <div style={{ padding: "16px 16px 0", background: T.surface, borderBottom: `1px solid ${T.borderLight}` }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
                    <button onClick={onBack} style={{ background: "none", border: "none", fontSize: 20, cursor: "pointer", color: T.textSecondary }}>←</button>
                    <span style={{ fontWeight: 700, fontSize: 17, color: T.text }}>New Mentorship</span>
                </div>
                {/* Step indicators */}
                <div style={{ display: "flex", gap: 4, paddingBottom: 12 }}>
                    {stepLabel.map((label, i) => (
                        <div key={i} style={{ flex: 1, textAlign: "center" }}>
                            <div style={{
                                height: 4, borderRadius: 2,
                                background: step > i + 1 ? T.primary : step === i + 1 ? T.primaryLight : T.borderLight,
                                marginBottom: 4,
                            }} />
                            <span style={{ fontSize: 10, color: step === i + 1 ? T.primary : T.textMuted }}>{label}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Content */}
            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radius, padding: 12, marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}

                {/* Step 1: Setup */}
                {step === 1 && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Program *
                            <select value={programId} onChange={e => setProgramId(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: T.surface }}>
                                <option value="">Select program...</option>
                                {programs.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Facility
                            <input value={facilityName} readOnly
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: "#F9FAFB", boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Start Date *
                            <input type="date" value={startDate} onChange={e => setStartDate(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>End Date *
                            <input type="date" value={endDate} min={startDate} onChange={e => setEndDate(e.target.value)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                        <label style={{ fontSize: 13, color: T.textSecondary }}>Max Participants
                            <input type="number" value={maxParticipants} min={1} onChange={e => setMaxParticipants(parseInt(e.target.value) || 20)}
                                style={{ display: "block", width: "100%", marginTop: 4, padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, boxSizing: "border-box" }} />
                        </label>
                    </div>
                )}

                {/* Step 2: Modules */}
                {step === 2 && (
                    <div>
                        <p style={{ fontSize: 13, color: T.textSecondary, marginBottom: 12 }}>
                            Select modules to include. Sessions are auto-created from program templates.
                        </p>
                        {availableModules.length === 0 && (
                            <div style={{ textAlign: "center", color: T.textMuted, padding: 32 }}>
                                {programId ? "No modules available for this program." : "Select a program first."}
                            </div>
                        )}
                        {availableModules.map(m => {
                            const selected = selectedModuleIds.includes(m.id);
                            return (
                                <div key={m.id} onClick={() => toggleModule(m.id)}
                                    style={{ display: "flex", alignItems: "center", gap: 12, padding: "12px 14px", marginBottom: 8, borderRadius: T.radius, border: `1px solid ${selected ? T.primary : T.border}`, background: selected ? T.primaryLight + "20" : T.surface, cursor: "pointer" }}>
                                    <div style={{ width: 20, height: 20, borderRadius: 4, border: `2px solid ${selected ? T.primary : T.border}`, background: selected ? T.primary : "transparent", display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>
                                        {selected && <span style={{ color: "#fff", fontSize: 12 }}>✓</span>}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{m.name}</div>
                                        {m.session_count > 0 && <div style={{ fontSize: 12, color: T.textMuted }}>{m.session_count} session{m.session_count !== 1 ? "s" : ""}</div>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Step 3: Mentees */}
                {step === 3 && (
                    <div>
                        {!navigator.onLine && (
                            <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radius, padding: 12, marginBottom: 14, display: "flex", gap: 10 }}>
                                <span style={{ fontSize: 18 }}>🔒</span>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 600, color: "#92400E" }}>Mobile data required</div>
                                    <div style={{ fontSize: 13, color: "#78350F", marginTop: 2 }}>Turn on mobile data to search and add mentees. You can skip and enroll them after saving.</div>
                                </div>
                            </div>
                        )}
                        {navigator.onLine && (
                            <>
                                <input placeholder="Search by name..." value={menteeSearch}
                                    onChange={e => { setMenteeSearch(e.target.value); searchMentees(e.target.value); }}
                                    style={{ width: "100%", padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, marginBottom: 8, boxSizing: "border-box" }} />
                                {menteeSearching && <div style={{ textAlign: "center", color: T.textMuted, padding: 8, fontSize: 13 }}>Searching...</div>}
                                {menteeResults.map(u => (
                                    <div key={u.id} onClick={() => addMentee(u)}
                                        style={{ padding: "10px 14px", borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 6, cursor: "pointer", background: T.surface }}>
                                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{u.name}</div>
                                        <div style={{ fontSize: 12, color: T.textMuted }}>{u.facility_name}</div>
                                    </div>
                                ))}
                            </>
                        )}
                        {selectedMentees.length > 0 && (
                            <div style={{ marginTop: 12 }}>
                                <div style={{ fontSize: 12, color: T.textSecondary, marginBottom: 6 }}>Added ({selectedMentees.length})</div>
                                {selectedMentees.map(u => (
                                    <div key={u.id} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "8px 14px", borderRadius: T.radius, background: T.primaryLight + "15", marginBottom: 6 }}>
                                        <span style={{ fontSize: 14, color: T.text }}>{u.name}</span>
                                        <button onClick={() => removeMentee(u.id)} style={{ background: "none", border: "none", color: T.textMuted, fontSize: 18, cursor: "pointer" }}>×</button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Step 4: Review */}
                {step === 4 && (
                    <div>
                        <div style={{ background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, padding: 16, marginBottom: 12 }}>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Program</div>
                            <div style={{ fontSize: 15, fontWeight: 500, color: T.text, marginBottom: 12 }}>
                                {programs.find(p => p.id == programId)?.name ?? "—"}
                            </div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Facility</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{facilityName}</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Dates</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{startDate} → {endDate}</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Modules</div>
                            <div style={{ fontSize: 15, color: T.text, marginBottom: 12 }}>{selectedModuleIds.length} selected</div>
                            <div style={{ fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>Mentees</div>
                            <div style={{ fontSize: 15, color: T.text }}>{selectedMentees.length} added</div>
                        </div>
                        <button disabled={saving || !programId || !startDate || !endDate}
                            onClick={() => handleSave(false)}
                            style={{ width: "100%", padding: 14, borderRadius: T.radius, background: T.surface, border: `1px solid ${T.border}`, color: T.text, fontSize: 15, fontWeight: 600, cursor: "pointer", marginBottom: 8 }}>
                            {saving ? "Saving..." : "Save as Draft"}
                        </button>
                        <button disabled={saving || !programId || !startDate || !endDate || selectedMentees.length === 0}
                            onClick={() => handleSave(true)}
                            style={{ width: "100%", padding: 14, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", opacity: selectedMentees.length === 0 ? 0.5 : 1 }}>
                            {saving ? "Starting..." : "Save & Start Class"}
                        </button>
                        {selectedMentees.length === 0 && (
                            <div style={{ textAlign: "center", fontSize: 12, color: T.textMuted, marginTop: 6 }}>Add at least one mentee to start immediately</div>
                        )}
                    </div>
                )}
            </div>

            {/* Footer navigation */}
            {step < 4 && (
                <div style={{ padding: "12px 16px", background: T.surface, borderTop: `1px solid ${T.borderLight}`, display: "flex", gap: 10 }}>
                    {step > 1 && (
                        <button onClick={() => setStep(s => s - 1)}
                            style={{ flex: 1, padding: 12, borderRadius: T.radius, background: T.surface, border: `1px solid ${T.border}`, color: T.text, fontSize: 15, cursor: "pointer" }}>
                            Back
                        </button>
                    )}
                    {step === 3 ? (
                        <button onClick={() => setStep(4)}
                            style={{ flex: 2, padding: 12, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer" }}>
                            {selectedMentees.length > 0 ? "Continue" : "Skip & Continue"}
                        </button>
                    ) : (
                        <button onClick={() => setStep(s => s + 1)}
                            disabled={step === 1 && (!programId || !startDate || !endDate)}
                            style={{ flex: 2, padding: 12, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", opacity: (step === 1 && (!programId || !startDate || !endDate)) ? 0.5 : 1 }}>
                            Continue
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Wire into App.jsx**

In `App.jsx`:

Add import after the existing screen imports:
```jsx
import { MentorshipFormScreen } from "./screens/screen-mentorship-form.jsx";
```

Add state for new wizard:
```jsx
const [showMentorshipWizard, setShowMentorshipWizard] = useState(false);
```

In the `mentorship` tab block, add a "+ New" FAB above the list screen. The cleanest place is to pass a prop:
```jsx
{tab === "mentorship" && (
    <MentorshipsListScreen
        user={user}
        onOpen={(training) => setModal({ type: "mentorshipDetail", data: training })}
        onNew={() => setShowMentorshipWizard(true)}
    />
)}
```

Add the wizard modal before the closing `</PhoneShell>`:
```jsx
{user && showMentorshipWizard && (
    <div style={{ position: "absolute", inset: 0, zIndex: 200 }}>
        <MentorshipFormScreen
            user={user}
            onBack={() => setShowMentorshipWizard(false)}
            onCreated={(training) => {
                setShowMentorshipWizard(false);
                setModal({ type: "mentorshipDetail", data: training });
            }}
        />
    </div>
)}
```

- [ ] **Step 3: Add "+ New Mentorship" button to MentorshipsListScreen**

In `src/screens/screen-mentorships-list.jsx`, add `onNew` prop to the component signature and render a button in the header area:

```jsx
export function MentorshipsListScreen({ user, onOpen, onNew }) {
```

Add a floating action button in the screen's return, inside the outer wrapper:
```jsx
{onNew && (
    <button onClick={onNew} style={{
        position: "absolute", bottom: 80, right: 16, zIndex: 10,
        width: 48, height: 48, borderRadius: "50%", background: T.primary,
        border: "none", color: "#fff", fontSize: 24, cursor: "pointer",
        boxShadow: T.shadow,
    }}>+</button>
)}
```

- [ ] **Step 4: Handle mentorship:id-resolved event in App.jsx**

Add a useEffect to update mentorships state when a temp ID is resolved:

```jsx
useEffect(() => {
    const handler = (e) => {
        const { tempId, realId } = e.detail;
        // Update any mentorship modal that's showing the temp id
        setModal(prev => {
            if (prev?.type === "mentorshipDetail" && prev?.data?.id === tempId) {
                return { ...prev, data: { ...prev.data, id: realId, _isOffline: false } };
            }
            return prev;
        });
    };
    window.addEventListener("mentorship:id-resolved", handler);
    return () => window.removeEventListener("mentorship:id-resolved", handler);
}, []);
```

- [ ] **Step 5: Build and manually test wizard flow**

```bash
npm run build
```
Open app → Mentorship tab → tap "+" → step through wizard → confirm all 4 steps render without errors.

- [ ] **Step 6: Commit**

```bash
git add src/screens/screen-mentorship-form.jsx src/App.jsx src/screens/screen-mentorships-list.jsx
git commit -m "feat(mobile): mentorship creation wizard — 4-step online/offline form"
```

---

## Task 10: Session Notes Screen

**Files:**
- Create: `src/screens/screen-session-notes.jsx`
- Modify: `src/screens/screen-module-detail.jsx`
- Modify: `src/App.jsx`

- [ ] **Step 1: Create session notes screen**

Create `src/screens/screen-session-notes.jsx`:

```jsx
import { useState } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

export function SessionNotesScreen({ session, onBack, onSaved }) {
    const [actualDate, setActualDate] = useState(session.actual_date ?? "");
    const [startTime, setStartTime] = useState(session.actual_time ?? "");
    const [location, setLocation] = useState(session.location ?? "");
    const [notes, setNotes] = useState(session.notes ?? "");
    const [status, setStatus] = useState(session.status ?? "scheduled");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const handleSave = async () => {
        setSaving(true);
        setError(null);
        try {
            await api.sessions.update(session.id, {
                actual_date: actualDate || null,
                actual_time: startTime || null,
                location: location || null,
                notes: notes || null,
                status,
            });
            onSaved?.({ ...session, actual_date: actualDate, actual_time: startTime, location, notes, status });
        } catch (e) {
            setError(e.message ?? "Failed to save.");
        } finally {
            setSaving(false);
        }
    };

    return (
        <div style={{ display: "flex", flexDirection: "column", height: "100%", background: T.bg }}>
            <div style={{ padding: "16px 16px 12px", background: T.surface, borderBottom: `1px solid ${T.borderLight}`, display: "flex", alignItems: "center", gap: 12 }}>
                <button onClick={onBack} style={{ background: "none", border: "none", fontSize: 20, cursor: "pointer", color: T.textSecondary }}>←</button>
                <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: 16, color: T.text }}>{session.title ?? "Session Notes"}</div>
                    <div style={{ fontSize: 12, color: T.textMuted }}>Session {session.session_number}</div>
                </div>
                <button onClick={handleSave} disabled={saving}
                    style={{ padding: "8px 16px", borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 14, fontWeight: 600, cursor: "pointer" }}>
                    {saving ? "Saving..." : "Save"}
                </button>
            </div>

            <div style={{ flex: 1, overflowY: "auto", padding: 16 }}>
                {error && (
                    <div style={{ background: "#FEF2F2", border: "1px solid #FECACA", borderRadius: T.radius, padding: 12, marginBottom: 12, color: "#DC2626", fontSize: 13 }}>
                        {error}
                    </div>
                )}
                {!navigator.onLine && (
                    <div style={{ background: "#FFFBEB", border: "1px solid #FCD34D", borderRadius: T.radius, padding: 10, marginBottom: 12, fontSize: 13, color: "#92400E" }}>
                        Offline — notes will sync when you reconnect.
                    </div>
                )}

                <Field label="Status">
                    <select value={status} onChange={e => setStatus(e.target.value)}
                        style={{ width: "100%", padding: "10px 12px", borderRadius: T.radius, border: `1px solid ${T.border}`, fontSize: 15, background: T.surface }}>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </Field>
                <Field label="Actual Date">
                    <input type="date" value={actualDate} onChange={e => setActualDate(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Start Time">
                    <input type="time" value={startTime} onChange={e => setStartTime(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Location">
                    <input placeholder="Ward, room, or facility" value={location} onChange={e => setLocation(e.target.value)}
                        style={inputStyle} />
                </Field>
                <Field label="Notes / Observations">
                    <textarea value={notes} onChange={e => setNotes(e.target.value)} rows={4} placeholder="What was observed, discussed, or noted..."
                        style={{ ...inputStyle, resize: "vertical" }} />
                </Field>
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <div style={{ marginBottom: 14 }}>
            <label style={{ display: "block", fontSize: 13, color: T.textSecondary, marginBottom: 4 }}>{label}</label>
            {children}
        </div>
    );
}

const inputStyle = {
    width: "100%",
    padding: "10px 12px",
    borderRadius: T.radius,
    border: `1px solid ${T.border}`,
    fontSize: 15,
    boxSizing: "border-box",
    background: "#fff",
};
```

- [ ] **Step 2: Add sessions list to ModuleDetailScreen**

Read `src/screens/screen-module-detail.jsx`. Add a sessions section that loads `api.modules.sessions(mod.id)` and taps to open session notes.

Add `onOpenSession` prop to the component and a sessions section after the attendance section:

```jsx
export function ModuleDetailScreen({ module: mod, user, onBack, onOpenAttendance, onOpenSession }) {
```

Add sessions state and load:
```jsx
const [sessions, setSessions] = useState([]);
useEffect(() => {
    if (mod?.id) {
        api.modules.sessions(mod.id)
            .then(d => setSessions(d?.data ?? d ?? []))
            .catch(() => {});
    }
}, [mod?.id]);
```

Add sessions list section in the render (before the closing div):
```jsx
{sessions.length > 0 && (
    <div style={{ marginTop: 16 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: T.textSecondary, marginBottom: 8 }}>SESSIONS</div>
        {sessions.map(s => (
            <div key={s.id} onClick={() => onOpenSession?.(s)}
                style={{ padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 8, cursor: "pointer" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <div>
                        <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{s.title ?? "Session " + s.session_number}</div>
                        <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>
                            {s.actual_date ?? s.scheduled_date ?? "Not scheduled"}
                        </div>
                    </div>
                    <span style={{ fontSize: 12, padding: "3px 8px", borderRadius: 10, background: s.status === "completed" ? "#D1FAE5" : "#F3F4F6", color: s.status === "completed" ? "#065F46" : T.textSecondary }}>
                        {s.status ?? "scheduled"}
                    </span>
                </div>
            </div>
        ))}
    </div>
)}
```

- [ ] **Step 3: Wire SessionNotesScreen into App.jsx**

Add import:
```jsx
import { SessionNotesScreen } from "./screens/screen-session-notes.jsx";
```

Update the `moduleDetail` modal handler to pass `onOpenSession`:
```jsx
{user && modal?.type === "moduleDetail" && (
    <div style={{ position: "absolute", inset: 0 }}>
        <ModuleDetailScreen
            module={modal.data}
            user={user}
            onBack={() => setModal({ type: "classDetail", data: modal.prev })}
            onOpenAttendance={(mod) => setModal({ type: "attendanceRoster", data: mod, prev: modal.prev })}
            onOpenSession={(session) => setModal({ type: "sessionNotes", data: session, prev: modal.data, prevClass: modal.prev })}
        />
    </div>
)}
```

Add session notes modal:
```jsx
{user && modal?.type === "sessionNotes" && (
    <div style={{ position: "absolute", inset: 0 }}>
        <SessionNotesScreen
            session={modal.data}
            onBack={() => setModal({ type: "moduleDetail", data: modal.prev, prev: modal.prevClass })}
            onSaved={() => setModal({ type: "moduleDetail", data: modal.prev, prev: modal.prevClass })}
        />
    </div>
)}
```

- [ ] **Step 4: Build and test**

```bash
npm run build
```
Open app → mentorship → class → module → tap a session → verify notes form opens.

- [ ] **Step 5: Commit**

```bash
git add src/screens/screen-session-notes.jsx src/screens/screen-module-detail.jsx src/App.jsx
git commit -m "feat(mobile): session notes screen and sessions list on module detail"
```

---

## Task 11: ResourceCard Component + Training & Mentorship Integration

**Files:**
- Create: `src/components/ResourceCard.jsx`
- Modify: `src/screens/screen-mentorship-detail.jsx`
- Modify: `src/screens/screen-training-detail.jsx`

- [ ] **Step 1: Create ResourceCard component**

Create `src/components/ResourceCard.jsx`:

```jsx
import { T } from "../constants.js";

const TYPE_COLORS = {
    mentorship_manual: { bg: "#EDE9FE", color: "#5B21B6", label: "Manual" },
    training: { bg: "#DBEAFE", color: "#1D4ED8", label: "Training" },
    document: { bg: "#F3F4F6", color: "#374151", label: "Document" },
    video: { bg: "#FEE2E2", color: "#991B1B", label: "Video" },
};

export function ResourceCard({ resource }) {
    const type = TYPE_COLORS[resource.type] ?? TYPE_COLORS.document;
    const url = resource.url ?? resource.file_url;

    const handleOpen = () => {
        if (url) window.open(url, "_blank");
    };

    return (
        <div style={{ padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 8, display: "flex", alignItems: "flex-start", gap: 12 }}>
            <div style={{ flex: 1 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4 }}>
                    <span style={{ fontSize: 11, padding: "2px 7px", borderRadius: 8, background: type.bg, color: type.color, fontWeight: 600 }}>{type.label}</span>
                </div>
                <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{resource.title}</div>
                {resource.description && (
                    <div style={{ fontSize: 12, color: T.textMuted, marginTop: 3, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                        {resource.description}
                    </div>
                )}
            </div>
            {url && (
                <button onClick={handleOpen}
                    style={{ padding: "6px 12px", borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 13, cursor: "pointer", flexShrink: 0 }}>
                    Open
                </button>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Add Resources section to MentorshipDetailScreen**

Read `src/screens/screen-mentorship-detail.jsx`. Add resources state, load on mount, and render a collapsible section.

Add import at top:
```jsx
import { ResourceCard } from "../components/ResourceCard.jsx";
```

Add state:
```jsx
const [resources, setResources] = useState([]);
const [showResources, setShowResources] = useState(false);
```

Add to the existing `useEffect` (or a new one) that loads mentorship data:
```jsx
api.resources.list('mentorship_manual').then(d => setResources(Array.isArray(d) ? d : d?.data ?? [])).catch(() => {});
```

Add section in the render before the closing div:
```jsx
{resources.length > 0 && (
    <div style={{ marginTop: 16 }}>
        <button onClick={() => setShowResources(s => !s)}
            style={{ width: "100%", padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, textAlign: "left", cursor: "pointer", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <span style={{ fontSize: 14, fontWeight: 600, color: T.text }}>Resources & Manuals ({resources.length})</span>
            <span style={{ color: T.textMuted }}>{showResources ? "▲" : "▼"}</span>
        </button>
        {showResources && resources.map(r => <ResourceCard key={r.id} resource={r} />)}
    </div>
)}
```

- [ ] **Step 3: Add Resources + Enroll + Attendance to TrainingDetailScreen**

Read `src/screens/screen-training-detail.jsx`. The screen receives a `training` prop and `user` prop.

Add import:
```jsx
import { ResourceCard } from "../components/ResourceCard.jsx";
```

Add state:
```jsx
const [resources, setResources] = useState([]);
const [enrolling, setEnrolling] = useState(false);
const [attending, setAttending] = useState(false);
const [enrolled, setEnrolled] = useState(false);
const [attended, setAttended] = useState(false);
```

Check enrollment on load — the participants list already loads; after it loads, check if current user is in it:
```jsx
// After participants load:
const me = participants.find(p => p.user_id === user?.id);
if (me) {
    setEnrolled(true);
    setAttended(me.attendance_status === 'present');
}
```

Load resources:
```jsx
api.resources.list('training').then(d => setResources(Array.isArray(d) ? d : d?.data ?? [])).catch(() => {});
```

Add Enroll button below the training info card:
```jsx
{!enrolled && ['upcoming', 'active'].includes(training.status) && (
    <button disabled={enrolling} onClick={async () => {
        setEnrolling(true);
        try {
            await api.trainingActions.enroll(training.id);
            setEnrolled(true);
        } catch { } finally { setEnrolling(false); }
    }} style={{ width: "100%", padding: 13, borderRadius: T.radius, background: T.primary, border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", marginBottom: 12 }}>
        {enrolling ? "Enrolling..." : "Enroll in Training"}
    </button>
)}
{enrolled && !attended && training.status === 'active' && (
    <button disabled={attending} onClick={async () => {
        setAttending(true);
        try {
            await api.trainingActions.attendance(training.id);
            setAttended(true);
        } catch { } finally { setAttending(false); }
    }} style={{ width: "100%", padding: 13, borderRadius: T.radius, background: "#10B981", border: "none", color: "#fff", fontSize: 15, fontWeight: 600, cursor: "pointer", marginBottom: 12 }}>
        {attending ? "Marking..." : "Mark My Attendance"}
    </button>
)}
{attended && (
    <div style={{ padding: 12, background: "#D1FAE5", borderRadius: T.radius, textAlign: "center", color: "#065F46", fontWeight: 600, marginBottom: 12, fontSize: 14 }}>
        Attendance Marked
    </div>
)}
```

Add resources section at the bottom:
```jsx
{resources.length > 0 && (
    <div style={{ marginTop: 8 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: T.textSecondary, marginBottom: 8 }}>RESOURCES</div>
        {resources.map(r => <ResourceCard key={r.id} resource={r} />)}
    </div>
)}
```

- [ ] **Step 4: Build and verify**

```bash
npm run build
```
Open training detail → verify enroll button, attendance button, resources section render.

- [ ] **Step 5: Commit**

```bash
git add src/components/ResourceCard.jsx src/screens/screen-mentorship-detail.jsx src/screens/screen-training-detail.jsx
git commit -m "feat(mobile): ResourceCard component + resources/enroll/attendance on mentorship & training detail"
```

---

## Task 12: Home Expansion + Navigation + App Bootstrap

**Files:**
- Modify: `src/screens/screen-dashboard.jsx`
- Modify: `src/App.jsx`
- Modify: `src/constants.js`

- [ ] **Step 1: Add Needs Attention section to DashboardScreen**

Read `src/screens/screen-dashboard.jsx`. Add conflicts state and Needs Attention banner.

Add import:
```jsx
import offlineStore from "../services/offline-store.js";
```

Add state (in the component):
```jsx
const [conflictCount, setConflictCount] = useState(0);
```

Add useEffect to load conflict count:
```jsx
useEffect(() => {
    offlineStore.getConflictCount().then(setConflictCount).catch(() => {});
    const handler = () => offlineStore.getConflictCount().then(setConflictCount).catch(() => {});
    window.addEventListener('mentorship:conflict-updated', handler);
    return () => window.removeEventListener('mentorship:conflict-updated', handler);
}, []);
```

Add Needs Attention banner at the TOP of the scroll content (before any existing sections):
```jsx
{conflictCount > 0 && (
    <div style={{ margin: "0 0 16px", padding: "12px 14px", background: "#FEF2F2", borderRadius: T.radius, border: "1px solid #FECACA", display: "flex", alignItems: "center", gap: 12 }}>
        <span style={{ fontSize: 20 }}>⚠️</span>
        <div style={{ flex: 1 }}>
            <div style={{ fontSize: 14, fontWeight: 600, color: "#DC2626" }}>{conflictCount} item{conflictCount !== 1 ? "s" : ""} need your review</div>
            <div style={{ fontSize: 12, color: "#B91C1C", marginTop: 2 }}>Some notes failed to sync. Tap to review.</div>
        </div>
    </div>
)}
```

Also add Upcoming Trainings section (after existing mentorships section):
```jsx
{upcomingTrainings.length > 0 && (
    <div style={{ marginTop: 20 }}>
        <div style={{ fontSize: 13, fontWeight: 700, color: T.textSecondary, marginBottom: 8, letterSpacing: 0.5 }}>UPCOMING TRAININGS</div>
        {upcomingTrainings.slice(0, 2).map(t => (
            <div key={t.id} style={{ padding: "12px 14px", background: T.surface, borderRadius: T.radius, border: `1px solid ${T.border}`, marginBottom: 8 }}>
                <div style={{ fontSize: 14, fontWeight: 500, color: T.text }}>{t.title}</div>
                <div style={{ fontSize: 12, color: T.textMuted, marginTop: 2 }}>{t.start_date ?? ""}</div>
            </div>
        ))}
    </div>
)}
```

Add `upcomingTrainings` prop to the component signature.

- [ ] **Step 2: Bootstrap mentorships, trainings, resources in App.jsx**

In `App.jsx`, add state variables after existing declarations:
```jsx
const [mentorships, setMentorships] = useState([]);
const [upcomingTrainings, setUpcomingTrainings] = useState([]);
```

In `runLoadData`, add role-based parallel fetches after the existing `Promise.all`:

```jsx
const roles = user?.roles ?? [];
const isMentor = roles.some(r => ['facility_mentor', 'spoke_mentor', 'spoke_mentor_lead', 'county_mentor_lead', 'subcounty_mentor_lead', 'facility_mentor_lead', 'national_mentor_lead'].includes(r));
const isMenteeRole = roles.some(r => r === 'mentee');

if (isMentor) {
    api.mentorships.list()
        .then(d => setMentorships(Array.isArray(d) ? d : d?.data ?? []))
        .catch(() => {});
}
if (isMenteeRole || roles.some(r => ['admin', 'super_admin'].includes(r))) {
    api.trainings.list()
        .then(d => {
            const list = Array.isArray(d) ? d : d?.data ?? [];
            setUpcomingTrainings(list.filter(t => ['upcoming', 'active'].includes(t.status)));
        })
        .catch(() => {});
}
api.resources.list()
    .catch(() => {});
```

Pass `upcomingTrainings` to `DashboardScreen`:
```jsx
<DashboardScreen
    user={user}
    assessments={userAssessments}
    onViewAssessment={openDetail}
    loading={isLoading}
    error={error}
    onRetry={handleRetry}
    upcomingTrainings={upcomingTrainings}
/>
```

- [ ] **Step 3: Update Reports nav rule in constants.js**

Read `src/constants.js`. In `computeTabs()`, find the block that adds `Reports`:

The current rule adds Reports for assessors or mentors. Change it so Reports is only shown for users who also have an assessor role (not pure mentors without assessor role):

```js
// Reports tab: only for users who are assessors (or admins); mentor-only users see reports on Home
const isAssessor = roles.some(r => ['facility_assessor', 'sub_county_assessor', 'county_assessor', 'national_assessor', 'assessor'].includes(r));
if (isAssessor || isAdmin) {
    tabs.push({ id: "reports", label: "Reports", icon: "📊" });
}
```

(Read the existing `computeTabs` to find the exact current condition and replace only the Reports-adding block.)

- [ ] **Step 4: Build and verify**

```bash
npm run build
```
Log in as mentor → Dashboard → verify mentorships section; log in as mentee → verify Upcoming Trainings section.

- [ ] **Step 5: Run full backend test suite one last time**

```bash
cd C:/xampp/htdocs/MNCH-Master
composer test
```
Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/screens/screen-dashboard.jsx src/App.jsx src/constants.js
git commit -m "feat(mobile): home expansion — needs attention, upcoming trainings, bootstrap, nav rule fix"
```

---

## Final Verification Checklist

- [ ] `composer test` — all backend tests green
- [ ] `npm run build` — no build errors
- [ ] Mentor can open `/api/v1/programs` and `/api/v1/programs/{id}/modules` (Postman or browser)
- [ ] Mentorship wizard opens, all 4 steps render
- [ ] Step 3 shows offline banner when network is off
- [ ] Session notes save offline and show "will sync" message
- [ ] Training detail shows Enroll button for unenrolled users
- [ ] ResourceCard renders with type badge and Open button
- [ ] Dashboard shows Needs Attention banner when `conflicts` store has entries
- [ ] `DB_VERSION` in offline-store.js is `6`
