# Phase 2 Testing Safety Net (Increment 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lock in executable, regression-catching coverage for the highest-priority findings from `docs/PHASE1-DISCOVERY-BASELINE.md` §9 (Risk Register) — the RBAC bypass, the missing `monthly_reports` table, the `RolePolicy` stub tokens, the untested health-check endpoints, and the never-tested assessment report generators — plus a first, non-destructive backup-restore verification tool. This is Increment 1 of the audit's Phase 2 ("Testing Safety Net"); it does not attempt full route/API/DB-constraint coverage across all ~503 routes — see "Deferred to later increments" below.

**Architecture:** Every task in this plan is a **characterization test**, not a bug fix: it writes an automated test that asserts the system's *current* observed behavior (including known-broken behavior), so that (a) a future intentional fix can prove it changed something on purpose, and (b) any accidental regression during unrelated work is caught immediately. No production code is modified by this plan except the one non-destructive addition in Task 6 (a new, opt-in shell script). This follows the audit's non-negotiable rule: nothing that currently works may be silently changed.

**Tech Stack:** PHPUnit 11 (via `php artisan test`), Laravel 12 Feature/Unit test conventions already established in this repo (`RefreshDatabase`, `Livewire::test()`, `Spatie\Permission` role/permission factories), bash for the backup-restore script.

## Global Constraints

- Every new test must run against the existing `phpunit.xml` config (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — do not modify `phpunit.xml` or `tests/TestCase.php`. The existing per-test `use RefreshDatabase;` pattern (used by 57 of 61 current test files) is the established convention here — follow it, don't refactor the base class.
- No task may modify any file under `app/`, `database/migrations/`, `routes/`, or `config/` — this plan is test-only plus one new opt-in script under `scripts/`.
- Every test class must extend `Tests\TestCase` and live under `tests/Unit/` or `tests/Feature/` matching existing naming (`{Subject}Test.php`, PascalCase, `test_` prefixed snake-ish method names — mirror the style already in `tests/Feature/MentorshipTrainingListFiltersTest.php` and `tests/Unit/AssessmentSummaryQueryServiceTest.php`).
- Every assertion that documents a *known-bad* current behavior must include a failure message that (a) points at the exact Risk Register section in `docs/PHASE1-DISCOVERY-BASELINE.md`, and (b) tells a future engineer what to do when the test starts failing (i.e., "this is expected to fail once you fix X — update this test then").
- Run `php artisan test` after every task and confirm the full suite is still green (313 pre-existing + new) before committing.

---

### Task 1: Regression test for `User::canAccessFacility()`

**Files:**
- Create: `tests/Unit/UserCanAccessFacilityTest.php`

**Interfaces:**
- Consumes: `App\Models\User::canAccessFacility(int $facilityId): bool` (`app/Models/User.php:304-307`, currently `return true;` unconditionally — read-only, not modified by this task).
- Produces: nothing consumed by later tasks — fully independent.

- [ ] **Step 1: Write the characterization test**

```php
<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCanAccessFacilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_facility_assignment_can_currently_access_any_facility(): void
    {
        $user = User::factory()->create(['facility_id' => null]);
        $otherFacility = Facility::factory()->create();

        $this->assertTrue(
            $user->canAccessFacility($otherFacility->id),
            'canAccessFacility() currently always returns true — the real check is commented out '
            . '(see docs/PHASE1-DISCOVERY-BASELINE.md §9.1). This test locks in that known-bad behavior. '
            . 'If this assertion starts failing, the check was restored — flip this test to assertFalse '
            . 'and add a companion test proving an above-site/scoped user still gets true.'
        );
    }

    public function test_a_user_assigned_to_one_facility_can_currently_access_a_different_facility_too(): void
    {
        $ownFacility = Facility::factory()->create();
        $otherFacility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $ownFacility->id]);

        $this->assertTrue(
            $user->canAccessFacility($otherFacility->id),
            'Real scoping (isAboveSite() || scopedFacilityIds()->contains()) is commented out in '
            . 'User::canAccessFacility() — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1. Once fixed, this '
            . 'specific case (a facility-scoped user checking a facility they do NOT belong to) should '
            . 'assertFalse instead.'
        );
    }
}
```

- [ ] **Step 2: Run it to confirm it passes against current (known-bad) code**

Run: `php artisan test tests/Unit/UserCanAccessFacilityTest.php`
Expected: `PASS` — both assertions succeed because the method currently always returns `true`.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/UserCanAccessFacilityTest.php
git commit -m "test: lock in current canAccessFacility() bypass behavior (Phase 1 risk 9.1)"
```

---

### Task 2: Regression test documenting that `monthly_reports` has no migration

**Files:**
- Create: `tests/Feature/MonthlyReportResourceAvailabilityTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Facades\Schema::hasTable()` — read-only introspection, no app code touched.
- Produces: nothing consumed by later tasks — fully independent. (Note for a future increment: once `monthly_reports` gets a real migration, replace this test with real `MonthlyReportResource` facility-scoping coverage using the pattern in `tests/Feature/MentorshipTrainingListFiltersTest.php`.)

- [ ] **Step 1: Write the characterization test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonthlyReportResourceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_monthly_reports_table_does_not_exist_yet(): void
    {
        $this->assertFalse(
            Schema::hasTable('monthly_reports'),
            'monthly_reports now has a migration and exists — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1a. '
            . 'This test documented a known-broken feature (MonthlyReportResource, MonthlyReportObserver, and '
            . 'the reports:generate-monthly command all reference a table with no migration). Now that it '
            . 'exists: delete this test and replace it with real MonthlyReportResource facility-scoping '
            . 'coverage — see docs/PHASE1-DISCOVERY-BASELINE.md §9.1 for what to test (getEloquentQuery() '
            . 'scoping, canViewAny(), and the EditMonthlyReport canAccessFacility() check all need coverage '
            . 'once the underlying table is real).'
        );
    }
}
```

- [ ] **Step 2: Run it to confirm it passes against current (known-broken) state**

Run: `php artisan test tests/Feature/MonthlyReportResourceAvailabilityTest.php`
Expected: `PASS` — the in-memory test DB is migrated from the same `database/migrations/` directory as production, so it has no `monthly_reports` table either.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/MonthlyReportResourceAvailabilityTest.php
git commit -m "test: document that monthly_reports has no migration (Phase 1 risk 9.1a)"
```

---

### Task 3: Regression tests for `RolePolicy` Shield stub tokens

**Files:**
- Create: `tests/Unit/RolePolicyTest.php`

**Interfaces:**
- Consumes: `App\Policies\RolePolicy` (`app/Policies/RolePolicy.php`) — instantiated directly, not via Gate resolution (no policy-registration wiring was found for this class, so testing it directly avoids depending on undocumented auto-discovery).
- Produces: nothing consumed by later tasks — fully independent.

- [ ] **Step 1: Write the characterization tests**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_currently_always_denies_even_with_every_real_permission_granted(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'restore_role', 'guard_name' => 'web']);
        $user->givePermissionTo('restore_role');
        $role = Role::create(['name' => 'some_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->restore($user, $role),
            'RolePolicy::restore() checks the literal permission name "{{ Restore }}", an un-replaced Shield '
            . 'stub token that can never exist as a real permission (see docs/PHASE1-DISCOVERY-BASELINE.md '
            . '§9.2). This test locks in the current fail-closed (always-deny) behavior. Once the stub is '
            . 'replaced with a real slug (e.g. restore_role), update this test to assertTrue given the '
            . 'restore_role grant above.'
        );
    }

    public function test_force_delete_any_currently_always_denies_for_the_same_reason(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'force_delete_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('force_delete_any_role');

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->forceDeleteAny($user),
            'Same defect as restore() — forceDeleteAny() checks "{{ ForceDeleteAny }}". '
            . 'See docs/PHASE1-DISCOVERY-BASELINE.md §9.2.'
        );
    }

    public function test_replicate_currently_always_denies_for_the_same_reason(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'replicate_role', 'guard_name' => 'web']);
        $user->givePermissionTo('replicate_role');
        $role = Role::create(['name' => 'another_role', 'guard_name' => 'web']);

        $policy = new RolePolicy();

        $this->assertFalse(
            $policy->replicate($user, $role),
            'Same defect — replicate() checks "{{ Replicate }}". See docs/PHASE1-DISCOVERY-BASELINE.md §9.2.'
        );
    }

    public function test_view_any_correctly_grants_when_the_real_permission_slug_is_held(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_role', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_role');

        $policy = new RolePolicy();

        $this->assertTrue(
            $policy->viewAny($user),
            'viewAny() uses a real permission slug (view_any_role) and works correctly — included as a '
            . 'control case to prove the denials above are specifically about the stub tokens, not about '
            . 'RolePolicy or Spatie permissions being broken in general.'
        );
    }
}
```

- [ ] **Step 2: Run it to confirm current behavior**

Run: `php artisan test tests/Unit/RolePolicyTest.php`
Expected: `PASS` — all four assertions succeed against current code (three deny, one grants).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/RolePolicyTest.php
git commit -m "test: lock in RolePolicy Shield stub-token deny-by-default behavior (Phase 1 risk 9.2)"
```

---

### Task 4: Regression tests for existing health-check endpoints

**Files:**
- Create: `tests/Feature/HealthCheckEndpointsTest.php`

**Interfaces:**
- Consumes: the Laravel-default `/up` route (registered via `bootstrap/app.php:13`, `health: '/up'`), the custom `/health` route (`routes/web.php:473-482`), and the public `/api/v1/health` route (`routes/api.php:42-47`) — all pre-existing, none modified by this task.
- Produces: nothing consumed by later tasks — fully independent.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_laravel_default_up_route_reports_healthy(): void
    {
        $this->get('/up')->assertSuccessful();
    }

    public function test_the_public_health_route_returns_ok_status_with_expected_keys(): void
    {
        $response = $this->get('/health');

        $response->assertSuccessful();
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonStructure(['status', 'timestamp', 'resources_count', 'categories_count']);
    }

    public function test_the_api_v1_health_route_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test tests/Feature/HealthCheckEndpointsTest.php`
Expected: `PASS` on all three. If `/health` fails on `resources_count`/`categories_count` because `App\Models\Resource` or `App\Models\ResourceCategory` don't have a `published()`/`active()` scope reachable with zero seeded rows, that itself is a real finding — capture the actual error message before adjusting the assertion, don't just delete it.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/HealthCheckEndpointsTest.php
git commit -m "test: add regression coverage for existing health-check endpoints"
```

---

### Task 5: Regression tests for assessment report generation (PDF/HTML/CSV)

**Files:**
- Create: `tests/Feature/AssessmentReportGenerationTest.php`

**Interfaces:**
- Consumes: `App\Services\AssessmentPdfReportService::generateHtmlReport(Assessment $assessment): string`, `::generateExecutiveReport(Assessment $assessment)` (returns a `Barryvdh\DomPDF\PDF`), and `App\Services\AssessmentExportService::exportAssessmentToCSV(Assessment $assessment): string` — all pre-existing, none modified by this task. Reuses the `Assessment::create()` + `actingAs()` pattern already proven in `tests/Unit/AssessmentSummaryQueryServiceTest.php::makeAssessment()` (assessor_name is auto-filled from the authenticated user via a model `creating` hook in `app/Models/Assessment.php:56-65`, so `actingAs()` must be called before `Assessment::create()`).
- Produces: nothing consumed by later tasks — fully independent.

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use App\Models\User;
use App\Services\AssessmentExportService;
use App\Services\AssessmentPdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssessment(User $assessor, Facility $facility): Assessment
    {
        $this->actingAs($assessor);
        $type = AssessmentType::firstOrCreate(
            ['code' => 'STANDARD_FACILITY_ASSESSMENT'],
            ['name' => 'Standard Facility Assessment', 'version' => '1.0', 'is_active' => true]
        );

        return Assessment::create([
            'facility_id' => $facility->id,
            'assessment_type_id' => $type->id,
            'assessment_type' => 'baseline',
            'assessment_date' => now(),
            'status' => 'completed',
            'overall_percentage' => 82.5,
            'overall_grade' => 'green',
        ]);
    }

    public function test_html_report_contains_the_facility_name_and_assessor_name(): void
    {
        $facility = Facility::factory()->create(['name' => 'Kericho District Hospital']);
        $assessor = User::factory()->create(['name' => 'Jane Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $html = app(AssessmentPdfReportService::class)->generateHtmlReport($assessment);

        $this->assertStringContainsString('Kericho District Hospital', $html);
        $this->assertStringContainsString('Jane Assessor', $html);
    }

    public function test_pdf_report_generates_a_valid_pdf_stream_without_throwing(): void
    {
        $facility = Facility::factory()->create();
        $assessor = User::factory()->create(['name' => 'PDF Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $pdf = app(AssessmentPdfReportService::class)->generateExecutiveReport($assessment);
        $bytes = $pdf->output();

        $this->assertStringStartsWith(
            '%PDF',
            $bytes,
            'DomPDF output should start with the standard PDF file signature — if this fails, PDF '
            . 'generation for facility assessments is broken.'
        );
    }

    public function test_csv_export_contains_the_expected_section_headers(): void
    {
        $facility = Facility::factory()->create(['name' => 'Nakuru Level 4 Hospital']);
        $assessor = User::factory()->create(['name' => 'CSV Assessor']);
        $assessment = $this->makeAssessment($assessor, $facility);

        $csv = app(AssessmentExportService::class)->exportAssessmentToCSV($assessment);

        $this->assertStringContainsString('Nakuru Level 4 Hospital', $csv);
        $this->assertStringContainsString('INFRASTRUCTURE SECTION', $csv);
        $this->assertStringContainsString('SKILLS LAB SECTION', $csv);
        $this->assertStringContainsString('HUMAN RESOURCES SECTION', $csv);
        $this->assertStringContainsString('HEALTH PRODUCTS SECTION', $csv);
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test tests/Feature/AssessmentReportGenerationTest.php`
Expected: `PASS` on all three. The PDF test is the slowest (DomPDF rendering) — if it times out or fails in this environment specifically because of a missing font/extension, keep the HTML and CSV tests (they cover the same `prepareReportData()` code path plus the CSV builder) and note the PDF-specific gap in the commit message rather than deleting the whole file.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AssessmentReportGenerationTest.php
git commit -m "test: add regression coverage for assessment PDF/HTML/CSV report generation"
```

---

### Task 6: Non-destructive backup-restore verification script

**Files:**
- Create: `scripts/verify-backup-restore.sh`

**Interfaces:**
- Consumes: `.env` (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` only — never reads or targets `DB_DATABASE`), a dump file path passed as `$1`.
- Produces: a scratch MySQL database named `mnch_backup_verify_<timestamp>_<pid>`, dropped automatically on exit unless `--keep` is passed. Nothing else in the repo depends on this script's output — it's a standalone operational tool, not test-suite infrastructure.

**⚠️ This task creates and drops a real MySQL database on whatever host `.env` points to.** It is scoped defensively (fixed safety-prefixed name, drops on exit, never touches the configured `DB_DATABASE`), but running Step 3 below is a real side-effecting action against a database server — confirm with whoever owns that MySQL instance before running it outside a local dev box.

- [ ] **Step 1: Write the script**

```bash
#!/usr/bin/env bash
#
# Verify that a database backup dump can actually be restored, without touching
# the real application database. Creates a uniquely-named scratch database,
# restores the given dump into it, runs a few sanity checks, then drops it.
#
# Usage: scripts/verify-backup-restore.sh path/to/dump.sql[.gz] [--keep]
#
# Reads DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD from .env in the project
# root. Never reads or uses DB_DATABASE from .env — the restore target is
# always a freshly generated scratch database under the mnch_backup_verify_
# prefix, and the script refuses to DROP anything outside that prefix.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 path/to/dump.sql[.gz] [--keep]" >&2
    echo "" >&2
    echo "Known dumps in database/dbsql/:" >&2
    ls -1 "$PROJECT_ROOT/database/dbsql/" 2>/dev/null | sed 's/^/  /' >&2 || true
    exit 1
fi

DUMP_FILE="$1"
KEEP_DB="${2:-}"

if [[ ! -f "$DUMP_FILE" ]]; then
    echo "ERROR: dump file not found: $DUMP_FILE" >&2
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: .env not found at $ENV_FILE — cannot read DB credentials" >&2
    exit 1
fi

read_env_var() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(read_env_var DB_HOST)"
DB_PORT="$(read_env_var DB_PORT)"
DB_USERNAME="$(read_env_var DB_USERNAME)"
DB_PASSWORD="$(read_env_var DB_PASSWORD)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"

SAFETY_PREFIX="mnch_backup_verify_"
SCRATCH_DB="${SAFETY_PREFIX}$(date +%Y%m%d%H%M%S)_$$"

if [[ "$SCRATCH_DB" != ${SAFETY_PREFIX}* ]]; then
    echo "ERROR: refusing to continue — generated scratch DB name doesn't match safety prefix" >&2
    exit 1
fi

MYSQL_ARGS=(--host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME")
if [[ -n "$DB_PASSWORD" ]]; then
    export MYSQL_PWD="$DB_PASSWORD"
fi

cleanup() {
    if [[ "$KEEP_DB" != "--keep" ]]; then
        echo "Cleaning up: dropping scratch database $SCRATCH_DB"
        mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`${SCRATCH_DB}\`;" || true
    else
        echo "Keeping scratch database $SCRATCH_DB (--keep passed) — drop it manually when done:"
        echo "  mysql -h $DB_HOST -P $DB_PORT -u $DB_USERNAME -p -e \"DROP DATABASE \\\`${SCRATCH_DB}\\\`;\""
    fi
}
trap cleanup EXIT

echo "Creating scratch database: $SCRATCH_DB"
mysql "${MYSQL_ARGS[@]}" -e "CREATE DATABASE \`${SCRATCH_DB}\`;"

echo "Restoring $DUMP_FILE into $SCRATCH_DB ..."
if [[ "$DUMP_FILE" == *.gz ]]; then
    gunzip -c "$DUMP_FILE" | mysql "${MYSQL_ARGS[@]}" "$SCRATCH_DB"
else
    mysql "${MYSQL_ARGS[@]}" "$SCRATCH_DB" < "$DUMP_FILE"
fi

echo "Restore completed without a fatal error. Running sanity checks..."

TABLE_COUNT=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${SCRATCH_DB}';")
echo "Tables restored: $TABLE_COUNT"

if [[ "$TABLE_COUNT" -eq 0 ]]; then
    echo "ERROR: restore produced zero tables — dump is likely empty or invalid" >&2
    exit 1
fi

USERS_TABLE_EXISTS=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${SCRATCH_DB}' AND table_name = 'users';")
if [[ "$USERS_TABLE_EXISTS" -eq 1 ]]; then
    USER_COUNT=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM \`${SCRATCH_DB}\`.users;")
    echo "users row count in restored dump: $USER_COUNT"
else
    echo "WARNING: restored dump has no 'users' table — may be a partial dump, review manually" >&2
fi

echo ""
echo "PASS: $DUMP_FILE restored successfully into a scratch database ($TABLE_COUNT tables)."
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x scripts/verify-backup-restore.sh
```

- [ ] **Step 3: Run it against the most recent-looking dump (requires explicit confirmation — this touches a real MySQL server)**

Run: `./scripts/verify-backup-restore.sh "database/dbsql/02-02-2026 backup.sql.gz"`
Expected: exits 0, prints a table count and (if present) a `users` row count, then drops the scratch database automatically. If it fails, that is itself a Phase 1 finding worth adding to the Risk Register (a backup that doesn't actually restore is worse than no backup, because it creates false confidence) — do not treat a script bug and a bad dump as the same thing without checking which one it is.

- [ ] **Step 4: Commit**

```bash
git add scripts/verify-backup-restore.sh
git commit -m "chore: add non-destructive backup-restore verification script (Phase 1 risk 9.9)"
```

---

## Deferred to later Phase 2 increments (not in this plan)

Per the audit's own Phase 2 task list, these remain open and should become their own plans once this increment lands and is reviewed:
- Broad route-level smoke testing across all ~503 registered routes (this increment covers 3 health-check routes only).
- API contract tests for the ~40+ `/api/v1/*` mobile endpoints (some already have coverage under `tests/Feature/Api/` — that existing coverage was not touched or re-audited here).
- Database constraint tests (foreign keys, unique constraints) — none written in this increment.
- Deployment health checks beyond the existing `/up`, `/health`, `/api/v1/health` routes (e.g. queue-worker liveness, storage-disk writability).
- A rollback-procedure test/runbook (this increment only verifies restore, not a full rollback drill).
- CI wiring to run `php artisan test` automatically — no CI exists in this repo at all (Phase 1 finding §9.9); adding one is a bigger, separate decision (which provider, secrets handling) that shouldn't be bundled into a test-writing plan.

## Self-Review

**Spec coverage:** Task 1 → Risk 9.1 (facility-scoping bypass). Task 2 → Risk 9.1a (missing table). Task 3 → Risk 9.2 (RolePolicy stubs). Task 4 → Phase 2's "add deployment health checks" (targeting the 3 endpoints that already exist per Phase 1's integration map). Task 5 → Phase 2's "add report calculation tests" (targeting the report-generation gap confirmed empty in Phase 1's report inventory). Task 6 → Phase 2's "add backup restoration test" / Risk 9.9. Every task traces to a specific, already-verified Phase 1 finding — no speculative coverage was added.

**Placeholder scan:** No TBD/TODO/"add error handling" placeholders — every step has complete, real code verified against the actual current source (`User.php`, `RolePolicy.php`, `AssessmentExportService.php`, `AssessmentPdfReportService.php`, `routes/web.php`, `routes/api.php`, `Assessment.php`'s creating hook, `UserFactory.php`, `FacilityFactory.php`) rather than assumed signatures.

**Type consistency:** All service method calls (`generateHtmlReport(Assessment $assessment): string`, `generateExecutiveReport(Assessment $assessment)`, `exportAssessmentToCSV(Assessment $assessment): string`, `canAccessFacility(int $facilityId): bool`) match their actual current signatures read directly from source, not guessed. `RolePolicy` method names (`restore`, `forceDeleteAny`, `replicate`, `viewAny`) match the actual class read in full above.
