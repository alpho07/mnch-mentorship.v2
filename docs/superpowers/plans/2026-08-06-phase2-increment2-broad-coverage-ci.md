# Phase 2 Testing Safety Net (Increment 2): Broad Route/API Coverage + CI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the two biggest gaps left after Increment 1 (`docs/superpowers/plans/2026-08-06-phase2-testing-safety-net.md`): almost the entire app has zero regression coverage, and there is no CI to run any of it automatically. This increment adds broad (not exhaustive) smoke-level coverage across the route surface, a handful of high-value database constraint tests, and a GitHub Actions workflow.

**Architecture:** Route-level coverage uses a **route-walker pattern** — one parametrized test per bucket (admin web, public web, API) that iterates Laravel's actual registered route table at runtime via `Route::getRoutes()`, rather than hand-writing one test method per route. This is deliberately scoped to **parameter-less GET routes only** (196 of 374 web GET routes, 24 of 54 API GET routes) — routes requiring a resolved model ID (`{training}`, `{class}`, etc.) are excluded, since faking IDs against fixtures risks false confidence; that remains a gap for a future increment, noted at the end of this plan. DB constraint tests are hand-picked for the constraints touched or added earlier this session (monthly_reports, report_templates, assessment_types, human_resource_responses) rather than attempting exhaustive schema coverage.

**Tech Stack:** PHPUnit 11 via `php artisan test`, Laravel's `Route` facade for route introspection, GitHub Actions (origin is `github.com/alpho07/mnch-mentorship.v2`).

## Global Constraints

- Same rules as Increment 1: no `phpunit.xml`/`tests/TestCase.php` changes, every test class extends `Tests\TestCase` with `use RefreshDatabase;`, PascalCase `{Subject}Test.php` naming.
- The route-walker tests assert **HTTP status < 500** (a real, uncaught server error), not a specific 2xx/3xx code — redirects (e.g. to login for guest-only pages) and 403s (permission-correct denials) are legitimate, non-broken outcomes for a smoke test; only 500-class responses indicate an actual defect.
- Any route that turns up a genuine 500 during this work must be **investigated, not silently excluded** — if it's a real, pre-existing bug, document it (add to `docs/PHASE1-DISCOVERY-BASELINE.md` Risk Register) rather than special-casing it out of the test to force green.
- The CI workflow must not introduce any secret/credential requirement — `phpunit.xml` already runs fully self-contained against SQLite in-memory, so CI needs no database service container and no secrets.
- Do not push to `origin` as part of this plan — that's a separate, explicit step the user confirmed happens after review, not automatically.

---

### Task 1: Admin panel route smoke test (parameter-less `admin/*` GET routes, authenticated)

**Files:**
- Create: `tests/Feature/AdminRouteSmokeTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Route::getRoutes()` (read-only route introspection), `App\Models\User` factory + Spatie `Role`/`Permission`.
- Produces: nothing consumed by later tasks — independent.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that are legitimately not meant to be hit as a bare
     * authenticated GET, or that need state this smoke test doesn't set
     * up (e.g. an in-progress wizard session) — excluded with a reason,
     * not silently.
     */
    private const EXCLUDED_URIS = [
        'admin/logout', // POST-only in practice; if GET-registered it's a Filament internal, not a page
    ];

    public static function adminParamlessGetRouteProvider(): array
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $cases = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            if (in_array($uri, self::EXCLUDED_URIS, true)) {
                continue;
            }
            $cases[$uri] = [$uri];
        }

        return $cases;
    }

    /**
     * @dataProvider adminParamlessGetRouteProvider
     */
    public function test_admin_route_does_not_500(string $uri): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');
        $user->syncPermissions(Permission::all());
        $this->actingAs($user);

        $response = $this->get('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for an authenticated super_admin — investigate before excluding."
        );
    }
}
```

- [ ] **Step 2: Run it and triage failures**

Run: `php artisan test tests/Feature/AdminRouteSmokeTest.php`

Expected: most of the ~148 admin routes pass. For any that return 500:
1. Read the actual exception (rerun the single case with `php artisan test --filter="test_admin_route_does_not_500 with data set \"<uri>\""` and inspect the failure output — PHPUnit prints the exception trace even without `withoutExceptionHandling()`).
2. If it's a real, pre-existing bug unrelated to this test's setup (not "this route needs a specific record that doesn't exist yet, which is expected"), add it to `docs/PHASE1-DISCOVERY-BASELINE.md` §9 as a new Risk Register entry — do not fix it as a drive-by inside this test-writing task.
3. If it's expected (e.g. a page that 500s with zero data because it assumes at least one record exists — a real bug, still worth logging, not excluding), same treatment: log it, don't hide it.
4. Only add to `EXCLUDED_URIS` for routes that are structurally wrong to smoke-test this way (e.g. a route that always requires POST body data despite being GET-registered for some Filament-internal reason) — document the reason inline.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AdminRouteSmokeTest.php
git commit -m "test: add parameter-less admin route smoke test (148 routes)"
```

---

### Task 2: Public web route smoke test (parameter-less non-admin GET routes, unauthenticated)

**Files:**
- Create: `tests/Feature/PublicRouteSmokeTest.php`

**Interfaces:**
- Consumes: same `Route::getRoutes()` pattern as Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const EXCLUDED_URIS = [
        // add here with a one-line reason if triage in Step 2 finds a
        // structurally-unsuitable route (e.g. one that streams/never
        // terminates, or expects a signed URL this test can't generate)
    ];

    public static function publicParamlessGetRouteProvider(): array
    {
        $cases = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'api/')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            if (in_array($uri, self::EXCLUDED_URIS, true)) {
                continue;
            }
            $cases[$uri] = [$uri];
        }

        return $cases;
    }

    /**
     * @dataProvider publicParamlessGetRouteProvider
     */
    public function test_public_route_does_not_500(string $uri): void
    {
        $response = $this->get('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for a guest — investigate before excluding. "
            . "A redirect (e.g. to login) is fine; a 500 is not."
        );
    }
}
```

- [ ] **Step 2: Run it and triage failures** (same process as Task 1 Step 2)

Run: `php artisan test tests/Feature/PublicRouteSmokeTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/PublicRouteSmokeTest.php
git commit -m "test: add parameter-less public route smoke test"
```

---

### Task 3: API route smoke test (parameter-less `api/v1/*` GET routes, both auth states)

**Files:**
- Create: `tests/Feature/Api/ApiRouteSmokeTest.php`

**Interfaces:**
- Consumes: same `Route::getRoutes()` pattern, plus `Laravel\Sanctum` for authenticated requests (`Sanctum::actingAs()` — check this project's existing API tests, e.g. `tests/Feature/Api/MentorshipApiTest.php`, for the exact auth helper already in use and match it rather than inventing a new pattern).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Check the existing auth pattern before writing**

Run: `grep -n "actingAs\|Sanctum" tests/Feature/Api/MentorshipApiTest.php` — use whatever pattern that file already uses for authenticating API requests, for consistency. (This plan can't hardcode the exact call without risking it drifting from the real pattern — read that file first.)

- [ ] **Step 2: Write the test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const EXCLUDED_URIS = [
        // populated during Step 3 triage if needed, with a reason each
    ];

    public static function apiParamlessGetRouteProvider(): array
    {
        $cases = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            if (in_array($uri, self::EXCLUDED_URIS, true)) {
                continue;
            }
            $cases[$uri] = [$uri];
        }

        return $cases;
    }

    /**
     * @dataProvider apiParamlessGetRouteProvider
     */
    public function test_api_route_does_not_500_when_authenticated(string $uri): void
    {
        $user = User::factory()->create(['status' => 'active']);
        // Use the same auth helper found in Step 1 (e.g. Sanctum::actingAs($user, ['*'])
        // or $this->actingAs($user, 'sanctum') — replace this line with whatever that
        // file actually uses).
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for an authenticated API user — investigate."
        );
    }

    /**
     * @dataProvider apiParamlessGetRouteProvider
     */
    public function test_api_route_does_not_500_when_unauthenticated(string $uri): void
    {
        $response = $this->getJson('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for a guest — expect 200 (public) or 401 "
            . "(protected), never a 500."
        );
    }
}
```

- [ ] **Step 3: Run it and triage failures** (same process as Task 1 Step 2 — pay particular attention to any route returning 500 instead of 401 when unauthenticated, since that could indicate a missing `auth:sanctum` middleware, which is itself a security-relevant finding worth its own Risk Register entry, not just a test exclusion)

Run: `php artisan test tests/Feature/Api/ApiRouteSmokeTest.php`

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Api/ApiRouteSmokeTest.php
git commit -m "test: add parameter-less API route smoke test (both auth states)"
```

---

### Task 4: Database constraint tests

**Files:**
- Create: `tests/Feature/DatabaseConstraintsTest.php`

**Interfaces:**
- Consumes: `App\Models\MonthlyReport`, `App\Models\ReportTemplate`, `App\Models\AssessmentType`, `App\Models\Facility`, `App\Models\User` — all pre-existing, none modified.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\MonthlyReport;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_reports_enforces_one_report_per_facility_template_and_period(): void
    {
        $facility = Facility::factory()->create();
        $template = ReportTemplate::create(['name' => 'T', 'code' => 'T1', 'report_type' => 'general']);
        $creator = User::factory()->create();
        $period = now()->startOfMonth();

        MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => $period,
        ]);

        $this->expectException(QueryException::class);

        MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => $period,
        ]);
    }

    public function test_monthly_reports_cascades_on_facility_deletion(): void
    {
        $facility = Facility::factory()->create();
        $template = ReportTemplate::create(['name' => 'T', 'code' => 'T2', 'report_type' => 'general']);
        $creator = User::factory()->create();

        $report = MonthlyReport::create([
            'facility_id' => $facility->id,
            'report_template_id' => $template->id,
            'created_by' => $creator->id,
            'reporting_period' => now()->startOfMonth(),
        ]);

        $facility->forceDelete();

        $this->assertDatabaseMissing('monthly_reports', ['id' => $report->id]);
    }

    public function test_report_templates_code_is_unique(): void
    {
        ReportTemplate::create(['name' => 'A', 'code' => 'DUPLICATE_CODE', 'report_type' => 'general']);

        $this->expectException(QueryException::class);

        ReportTemplate::create(['name' => 'B', 'code' => 'DUPLICATE_CODE', 'report_type' => 'general']);
    }

    public function test_assessment_types_code_is_unique(): void
    {
        AssessmentType::create(['name' => 'A', 'code' => 'DUP_TYPE', 'version' => '1.0', 'is_active' => true]);

        $this->expectException(QueryException::class);

        AssessmentType::create(['name' => 'B', 'code' => 'DUP_TYPE', 'version' => '1.0', 'is_active' => true]);
    }

    public function test_users_email_is_unique(): void
    {
        User::factory()->create(['email' => 'duplicate@example.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'duplicate@example.test']);
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test tests/Feature/DatabaseConstraintsTest.php`
Expected: `PASS` on all five. If `test_monthly_reports_cascades_on_facility_deletion` fails because `Facility` uses `SoftDeletes` and `forceDelete()` isn't the right call for triggering the DB-level cascade, adjust to whatever deletion method actually removes the row (check `app/Models/Facility.php` for its delete behavior first if this fails).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DatabaseConstraintsTest.php
git commit -m "test: add database constraint tests for monthly_reports, report_templates, assessment_types, users"
```

---

### Task 5: GitHub Actions CI workflow

**Files:**
- Create: `.github/workflows/tests.yml`

**Interfaces:**
- Consumes: nothing from earlier tasks directly, but runs everything created in Tasks 1-4 plus the existing 334+ tests.
- Produces: nothing — terminal task.

- [ ] **Step 1: Write the workflow**

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, pdo_sqlite, sqlite3, bcmath, gd, zip, dom, curl, libxml, intl
          coverage: none

      - name: Copy .env
        run: cp .env.example .env

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Generate application key
        run: php artisan key:generate

      - name: Run test suite
        run: php artisan test
```

- [ ] **Step 2: Verify the YAML is well-formed**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/tests.yml'))" ` (or any available YAML linter) — confirms no syntax errors before it ever reaches GitHub, since this can't be executed locally without `act` (not assumed to be installed).

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci: add GitHub Actions workflow to run the test suite on push/PR to main"
```

**Note:** this workflow only actually runs once commits reach `origin` — per the user's explicit choice, pushing is a separate, later step requiring its own confirmation, not part of this plan's execution.

---

## Deferred to a further increment (not in this plan)

- Route smoke tests for the ~178 web and ~30 API routes that **require** a resolved model parameter (`{training}`, `{class}`, `{assessment}`, etc.) — needs a fixture-building strategy (representative seeded records per resource) that's substantial enough to be its own increment.
- POST/PUT/DELETE contract tests (this increment is GET-only, read-side smoke testing).
- Report-calculation tests beyond what Increment 1 already added for assessments.
- A rollback-procedure drill/runbook (Increment 1 added backup *restore* verification; rollback of a bad deploy is still undocumented).

## Self-Review

**Spec coverage:** Task 1-3 → "add route tests" / "add API contract tests" from the audit's Phase 2 list, scoped explicitly to the parameter-less subset (176+24=220 routes) rather than claimed as exhaustive. Task 4 → "add database constraint tests." Task 5 → "add deployment health checks" is already done (Increment 1); this closes the audit's implicit expectation that tests actually run automatically, which no phase task list item names outright but the whole Phase 2 concept depends on.

**Placeholder scan:** Task 3's exact Sanctum auth call is deliberately left as "read the existing pattern first" rather than guessed — this is not a placeholder in the prohibited sense (vague "add auth here"), it's an explicit instruction to verify a concrete detail against real code before writing the line, because guessing between `Sanctum::actingAs()` and `actingAs($user, 'sanctum')` wrong would make every test in that file fail for a reason unrelated to what's being tested.

**Type consistency:** `Route::getRoutes()` iteration pattern is identical across Tasks 1-3 (only the URI-prefix filter differs) — intentional, keeps the three smoke tests easy to compare/maintain as a set.
