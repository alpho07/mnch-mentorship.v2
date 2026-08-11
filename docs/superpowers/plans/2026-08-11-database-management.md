# Database Management (Backup / Schedule / Restore) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `super_admin`-only "Database Management" page under a new "App Configuration" nav group: on-demand backup, scheduled backup (daily/weekly/monthly), a list of backups with live status, download, and restore-with-automatic-safety-backup.

**Architecture:** Wrap the same `mysqldump`/`mysql` CLI tools the team already uses manually, behind a `DatabaseBackupService`, two DB tables for audit trail (`database_backups`, `database_restores`), two queued jobs so nothing blocks the HTTP request, a `Setting`-backed schedule config checked every 5 minutes by a new artisan command, and a Filament page with a polling table.

**Tech Stack:** Laravel 12 (`Process` facade, `Storage` facade, queue), Filament v3, Spatie Permission (roles), MySQL (`mysqldump`/`mysql` binaries must be on the app server's `$PATH` — same requirement as today's manual backups).

## Global Constraints

- `canAccess()` on the Filament page must check `auth()->user()->hasRole('super_admin')` directly, **not** a Shield permission — `RolePermissionSeeder::seedAdmin()` grants `Permission::all()` to the `admin` role too, so a permission-based gate would let `admin` in as well, which contradicts the super_admin-only decision.
- Every DB credential handling must go through a temp `--defaults-extra-file` (MySQL option file), never appear in a shell command string directly, and the temp file must be deleted in a `finally` block even on failure.
- `DatabaseBackupService::createBackup()` and `::restore()` must never throw past their own boundary — callers (the queued jobs) rely on them always resolving to a `completed` or `failed` row, since a job exception would just leave the row stuck at `running` with no user-visible explanation.
- Retention pruning only runs after a `manual` or `scheduled` backup completes, never after a `pre_restore_safety` one (a restore always has exactly one pruning pass, driven by the outer backup call it made, not a second one from inside the restore flow).
- All new Blade/PHP files follow this codebase's PSR-12 style (brace on its own line for classes/methods) — match `app/Filament/Pages/MentorshipSettings.php`, not the same-line-brace style used in `app/Http/Controllers/Api`.

---

## Task 1: Migrations, models, and the `backups` storage disk

**Files:**
- Create: `database/migrations/2026_08_11_160000_create_database_backups_table.php`
- Create: `database/migrations/2026_08_11_160001_create_database_restores_table.php`
- Create: `app/Models/DatabaseBackup.php`
- Create: `app/Models/DatabaseRestore.php`
- Modify: `config/filesystems.php`
- Test: `tests/Unit/DatabaseBackupModelTest.php`

**Interfaces:**
- Produces (used by every later task): `DatabaseBackup` (columns: `filename`, `disk`, `size_bytes`, `type` enum `manual`/`scheduled`/`pre_restore_safety`, `status` enum `pending`/`running`/`completed`/`failed`, `triggered_by`, `error_message`, `started_at`, `completed_at`), with `triggeredBy(): BelongsTo`, `restores(): HasMany`, `isDownloadable(): bool`. `DatabaseRestore` (columns: `database_backup_id`, `safety_backup_id`, `status`, `error_message`, `restored_by`, `started_at`, `completed_at`), with `backup(): BelongsTo`, `safetyBackup(): BelongsTo`, `restoredBy(): BelongsTo`. Storage disk name `'backups'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DatabaseBackupModelTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\DatabaseBackup;
use App\Models\DatabaseRestore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_is_downloadable_only_when_completed_and_the_file_exists(): void
    {
        Storage::fake('backups');

        $backup = DatabaseBackup::create([
            'filename' => 'backup-test.sql.gz',
            'disk' => 'backups',
            'type' => 'manual',
            'status' => 'completed',
        ]);

        $this->assertFalse($backup->isDownloadable());

        Storage::disk('backups')->put('backup-test.sql.gz', 'fake gzip content');
        $this->assertTrue($backup->fresh()->isDownloadable());

        $backup->status = 'failed';
        $this->assertFalse($backup->isDownloadable());
    }

    public function test_restore_links_to_its_backup_safety_backup_and_the_user_who_triggered_it(): void
    {
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'a.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        $safety = DatabaseBackup::create(['filename' => 'safety.sql.gz', 'disk' => 'backups', 'type' => 'pre_restore_safety', 'status' => 'completed']);

        $restore = DatabaseRestore::create([
            'database_backup_id' => $backup->id,
            'safety_backup_id' => $safety->id,
            'status' => 'completed',
            'restored_by' => $user->id,
        ]);

        $this->assertTrue($restore->backup->is($backup));
        $this->assertTrue($restore->safetyBackup->is($safety));
        $this->assertTrue($restore->restoredBy->is($user));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DatabaseBackupModelTest`
Expected: FAIL — `Base table or view not found` (tables/models don't exist yet).

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_08_11_160000_create_database_backups_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk')->default('backups');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->enum('type', ['manual', 'scheduled', 'pre_restore_safety']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
```

Create `database/migrations/2026_08_11_160001_create_database_restores_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('database_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_backup_id')->constrained('database_backups')->cascadeOnDelete();
            $table->foreignId('safety_backup_id')->nullable()->constrained('database_backups')->nullOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('restored_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_restores');
    }
};
```

- [ ] **Step 4: Create the models**

Create `app/Models/DatabaseBackup.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'filename',
        'disk',
        'size_bytes',
        'type',
        'status',
        'triggered_by',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function restores(): HasMany
    {
        return $this->hasMany(DatabaseRestore::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && Storage::disk($this->disk)->exists($this->filename);
    }
}
```

Create `app/Models/DatabaseRestore.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseRestore extends Model
{
    protected $fillable = [
        'database_backup_id',
        'safety_backup_id',
        'status',
        'error_message',
        'restored_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(DatabaseBackup::class, 'database_backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(DatabaseBackup::class, 'safety_backup_id');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }
}
```

- [ ] **Step 5: Add the `backups` storage disk**

In `config/filesystems.php`, inside the `'disks'` array, add after the existing `'resources'` disk entry:

```php
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => false,
        ],
```

- [ ] **Step 6: Run the migrations and the test**

Run: `php artisan migrate` then `php artisan test --filter=DatabaseBackupModelTest`
Expected: PASS (both tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_11_160000_create_database_backups_table.php database/migrations/2026_08_11_160001_create_database_restores_table.php app/Models/DatabaseBackup.php app/Models/DatabaseRestore.php config/filesystems.php tests/Unit/DatabaseBackupModelTest.php
git commit -m "feat: add database_backups/database_restores tables, models, and backups disk"
```

---

## Task 2: `Setting` keys + `DatabaseBackupService::createBackup()` and `pruneOldBackups()`

**Files:**
- Modify: `app/Models/Setting.php`
- Create: `app/Services/DatabaseBackupService.php`
- Test: `tests/Unit/DatabaseBackupServiceTest.php`

**Interfaces:**
- Consumes: `DatabaseBackup` (Task 1), `'backups'` disk (Task 1).
- Produces (used by Task 3, Task 4, Task 5, Task 6): `DatabaseBackupService::createBackup(?int $userId, string $type = 'manual'): DatabaseBackup`, `DatabaseBackupService::pruneOldBackups(): void`. `Setting` constants: `BACKUP_SCHEDULE_ENABLED`, `BACKUP_SCHEDULE_FREQUENCY`, `BACKUP_SCHEDULE_TIME`, `BACKUP_SCHEDULE_DAY_OF_WEEK`, `BACKUP_SCHEDULE_DAY_OF_MONTH`, `BACKUP_RETENTION_COUNT`, `BACKUP_LAST_SCHEDULED_RUN_AT`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/DatabaseBackupServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\DatabaseBackup;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_backup_records_a_completed_row_on_success(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result()]);
        $user = User::factory()->create();

        $backup = app(DatabaseBackupService::class)->createBackup($user->id, 'manual');

        $this->assertSame('completed', $backup->status);
        $this->assertSame('manual', $backup->type);
        $this->assertSame($user->id, $backup->triggered_by);
        $this->assertNotNull($backup->completed_at);
        $this->assertStringEndsWith('.sql.gz', $backup->filename);
    }

    public function test_create_backup_records_a_failed_row_with_the_error_on_failure(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result(exitCode: 1, errorOutput: 'Access denied for user')]);

        $backup = app(DatabaseBackupService::class)->createBackup(null, 'manual');

        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('Access denied for user', $backup->error_message);
    }

    public function test_create_backup_never_throws_even_when_the_process_layer_errors(): void
    {
        Storage::fake('backups');
        Process::fake(function () {
            throw new \RuntimeException('process spawn failed');
        });

        $backup = app(DatabaseBackupService::class)->createBackup(null, 'manual');

        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('process spawn failed', $backup->error_message);
    }

    public function test_manual_and_scheduled_backups_trigger_pruning_but_safety_backups_do_not(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result()]);
        Setting::set(Setting::BACKUP_RETENTION_COUNT, 1);

        DatabaseBackup::create(['filename' => 'old.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed', 'created_at' => now()->subDay()]);

        app(DatabaseBackupService::class)->createBackup(null, 'manual');
        $this->assertSame(1, DatabaseBackup::where('type', 'manual')->count());

        app(DatabaseBackupService::class)->createBackup(null, 'pre_restore_safety');
        $this->assertSame(2, DatabaseBackup::count(), 'a safety backup should not prune the manual one down further than retention already did');
    }

    public function test_prune_old_backups_keeps_only_the_configured_count(): void
    {
        Storage::fake('backups');
        Setting::set(Setting::BACKUP_RETENTION_COUNT, 2);

        foreach (range(1, 4) as $i) {
            $backup = DatabaseBackup::create([
                'filename' => "backup-{$i}.sql.gz",
                'disk' => 'backups',
                'type' => 'manual',
                'status' => 'completed',
            ]);
            Storage::disk('backups')->put("backup-{$i}.sql.gz", 'x');
            $backup->forceFill(['created_at' => now()->addMinutes($i)])->save();
        }

        app(DatabaseBackupService::class)->pruneOldBackups();

        $remaining = DatabaseBackup::orderByDesc('created_at')->pluck('filename')->all();
        $this->assertSame(['backup-4.sql.gz', 'backup-3.sql.gz'], $remaining);
        $this->assertFalse(Storage::disk('backups')->exists('backup-1.sql.gz'));
        $this->assertFalse(Storage::disk('backups')->exists('backup-2.sql.gz'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DatabaseBackupServiceTest`
Expected: FAIL — `Target class [App\Services\DatabaseBackupService] does not exist.`

- [ ] **Step 3: Add the `Setting` constants**

In `app/Models/Setting.php`, add after the existing `PROGRAM_SCOPING_ENABLED` constant:

```php
    public const PROGRAM_SCOPING_ENABLED = 'program_scoping_enabled';

    public const BACKUP_SCHEDULE_ENABLED = 'backup_schedule_enabled';

    public const BACKUP_SCHEDULE_FREQUENCY = 'backup_schedule_frequency';

    public const BACKUP_SCHEDULE_TIME = 'backup_schedule_time';

    public const BACKUP_SCHEDULE_DAY_OF_WEEK = 'backup_schedule_day_of_week';

    public const BACKUP_SCHEDULE_DAY_OF_MONTH = 'backup_schedule_day_of_month';

    public const BACKUP_RETENTION_COUNT = 'backup_retention_count';

    public const BACKUP_LAST_SCHEDULED_RUN_AT = 'backup_last_scheduled_run_at';
```

- [ ] **Step 4: Create `DatabaseBackupService`**

Create `app/Services/DatabaseBackupService.php`:

```php
<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\Setting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DatabaseBackupService
{
    public function createBackup(?int $userId, string $type = 'manual'): DatabaseBackup
    {
        $prefix = $type === 'pre_restore_safety' ? 'safety' : 'backup';
        $filename = "{$prefix}-".now()->format('Ymd_His').'.sql.gz';

        $backup = DatabaseBackup::create([
            'filename' => $filename,
            'disk' => 'backups',
            'type' => $type,
            'status' => 'running',
            'triggered_by' => $userId,
            'started_at' => now(),
        ]);

        $credentialsFile = null;

        try {
            $credentialsFile = $this->writeCredentialsFile();
            $destPath = Storage::disk('backups')->path($filename);

            $command = sprintf(
                'mysqldump --defaults-extra-file=%s %s | gzip > %s',
                escapeshellarg($credentialsFile),
                escapeshellarg(config('database.connections.mysql.database')),
                escapeshellarg($destPath)
            );

            $result = Process::timeout(600)->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException($result->errorOutput() ?: 'mysqldump exited with a non-zero status.');
            }

            $backup->update([
                'status' => 'completed',
                'size_bytes' => is_file($destPath) ? filesize($destPath) : null,
                'completed_at' => now(),
            ]);

            if (in_array($type, ['manual', 'scheduled'], true)) {
                $this->pruneOldBackups();
            }
        } catch (Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'completed_at' => now(),
            ]);
        } finally {
            if ($credentialsFile) {
                @unlink($credentialsFile);
            }
        }

        return $backup->fresh();
    }

    public function pruneOldBackups(): void
    {
        $keep = max(0, (int) Setting::get(Setting::BACKUP_RETENTION_COUNT, 14));

        $keepIds = DatabaseBackup::query()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit($keep)
            ->pluck('id');

        DatabaseBackup::query()
            ->where('status', 'completed')
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(function (DatabaseBackup $backup): void {
                Storage::disk($backup->disk)->delete($backup->filename);
                $backup->delete();
            });
    }

    private function writeCredentialsFile(): string
    {
        $config = config('database.connections.mysql');
        $path = tempnam(sys_get_temp_dir(), 'dbcnf');

        file_put_contents($path, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password']
        ));
        chmod($path, 0600);

        return $path;
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=DatabaseBackupServiceTest`
Expected: PASS (all 5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Setting.php app/Services/DatabaseBackupService.php tests/Unit/DatabaseBackupServiceTest.php
git commit -m "feat: add DatabaseBackupService with mysqldump-backed createBackup and retention pruning"
```

---

## Task 3: `DatabaseBackupService::restore()`

**Files:**
- Modify: `app/Services/DatabaseBackupService.php`
- Test: `tests/Unit/DatabaseBackupServiceTest.php` (append)

**Interfaces:**
- Consumes: `DatabaseBackup`, `DatabaseRestore` (Task 1), `createBackup()` (Task 2, called internally for the safety backup).
- Produces (used by Task 4): `DatabaseBackupService::restore(DatabaseBackup $backup, int $userId): DatabaseRestore`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/DatabaseBackupServiceTest.php` (add `use App\Models\DatabaseRestore;` to the imports):

```php
    public function test_restore_takes_a_safety_backup_first_then_restores(): void
    {
        Storage::fake('backups');
        Process::fake([
            'mysqldump*' => Process::result(),
            'gunzip*' => Process::result(),
        ]);
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'target.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        Storage::disk('backups')->put('target.sql.gz', 'fake dump');

        $restore = app(DatabaseBackupService::class)->restore($backup, $user->id);

        $this->assertSame('completed', $restore->status);
        $this->assertSame($user->id, $restore->restored_by);
        $this->assertNotNull($restore->safety_backup_id);
        $this->assertSame('pre_restore_safety', DatabaseBackup::find($restore->safety_backup_id)->type);
    }

    public function test_restore_aborts_without_touching_mysql_if_the_safety_backup_fails(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result(exitCode: 1, errorOutput: 'disk full')]);
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'target.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);

        $restore = app(DatabaseBackupService::class)->restore($backup, $user->id);

        $this->assertSame('failed', $restore->status);
        $this->assertStringContainsString('safety backup failed', $restore->error_message);
        Process::assertNotRun(fn ($process): bool => str_starts_with($process->command, 'gunzip'));
    }

    public function test_restore_records_failure_when_the_mysql_import_itself_fails(): void
    {
        Storage::fake('backups');
        Process::fake([
            'mysqldump*' => Process::result(),
            'gunzip*' => Process::result(exitCode: 1, errorOutput: 'syntax error near line 40'),
        ]);
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'target.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        Storage::disk('backups')->put('target.sql.gz', 'fake dump');

        $restore = app(DatabaseBackupService::class)->restore($backup, $user->id);

        $this->assertSame('failed', $restore->status);
        $this->assertStringContainsString('syntax error near line 40', $restore->error_message);
        $this->assertNotNull($restore->safety_backup_id, 'the safety backup should still exist even though the restore itself failed');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DatabaseBackupServiceTest`
Expected: FAIL — `Call to undefined method App\Services\DatabaseBackupService::restore()`.

- [ ] **Step 3: Add `restore()` to `DatabaseBackupService`**

In `app/Services/DatabaseBackupService.php`, add the import `use App\Models\DatabaseRestore;` at the top, then add this method after `createBackup()`:

```php
    public function restore(DatabaseBackup $backup, int $userId): DatabaseRestore
    {
        $restore = DatabaseRestore::create([
            'database_backup_id' => $backup->id,
            'status' => 'running',
            'restored_by' => $userId,
            'started_at' => now(),
        ]);

        $safetyBackup = $this->createBackup($userId, 'pre_restore_safety');
        $restore->update(['safety_backup_id' => $safetyBackup->id]);

        if ($safetyBackup->status !== 'completed') {
            $restore->update([
                'status' => 'failed',
                'error_message' => 'Restore aborted: the automatic safety backup failed — '.$safetyBackup->error_message,
                'completed_at' => now(),
            ]);

            return $restore->fresh();
        }

        $credentialsFile = null;

        try {
            $credentialsFile = $this->writeCredentialsFile();
            $sourcePath = Storage::disk($backup->disk)->path($backup->filename);

            $command = sprintf(
                'gunzip < %s | mysql --defaults-extra-file=%s %s',
                escapeshellarg($sourcePath),
                escapeshellarg($credentialsFile),
                escapeshellarg(config('database.connections.mysql.database'))
            );

            $result = Process::timeout(900)->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException($result->errorOutput() ?: 'mysql restore exited with a non-zero status.');
            }

            $restore->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (Throwable $e) {
            $restore->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'completed_at' => now(),
            ]);
        } finally {
            if ($credentialsFile) {
                @unlink($credentialsFile);
            }
        }

        return $restore->fresh();
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=DatabaseBackupServiceTest`
Expected: PASS (all 8 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/DatabaseBackupService.php tests/Unit/DatabaseBackupServiceTest.php
git commit -m "feat: add restore() with an automatic pre-restore safety backup"
```

---

## Task 4: Queued jobs

**Files:**
- Create: `app/Jobs/RunDatabaseBackupJob.php`
- Create: `app/Jobs/RestoreDatabaseJob.php`
- Test: `tests/Unit/DatabaseBackupJobsTest.php`

**Interfaces:**
- Consumes: `DatabaseBackupService::createBackup()`, `::restore()` (Task 2, Task 3).
- Produces (used by Task 5, Task 6): `RunDatabaseBackupJob::dispatch(string $type, ?int $userId)`, `RestoreDatabaseJob::dispatch(int $backupId, int $userId)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DatabaseBackupJobsTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_database_backup_job_creates_a_backup_via_the_service(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result()]);
        $user = User::factory()->create();

        (new RunDatabaseBackupJob('manual', $user->id))->handle(app(\App\Services\DatabaseBackupService::class));

        $backup = DatabaseBackup::sole();
        $this->assertSame('completed', $backup->status);
        $this->assertSame($user->id, $backup->triggered_by);
    }

    public function test_restore_database_job_restores_via_the_service(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result(), 'gunzip*' => Process::result()]);
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'target.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        Storage::disk('backups')->put('target.sql.gz', 'fake dump');

        (new RestoreDatabaseJob($backup->id, $user->id))->handle(app(\App\Services\DatabaseBackupService::class));

        $this->assertSame('completed', $backup->restores()->sole()->status);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=DatabaseBackupJobsTest`
Expected: FAIL — `Class "App\Jobs\RunDatabaseBackupJob" not found`.

- [ ] **Step 3: Create the jobs**

Create `app/Jobs/RunDatabaseBackupJob.php`:

```php
<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 700;

    public function __construct(
        public string $type,
        public ?int $userId,
    ) {}

    public function handle(DatabaseBackupService $service): void
    {
        $service->createBackup($this->userId, $this->type);
    }
}
```

Create `app/Jobs/RestoreDatabaseJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreDatabaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1000;

    public function __construct(
        public int $backupId,
        public int $userId,
    ) {}

    public function handle(DatabaseBackupService $service): void
    {
        $service->restore(DatabaseBackup::findOrFail($this->backupId), $this->userId);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=DatabaseBackupJobsTest`
Expected: PASS (both tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/RunDatabaseBackupJob.php app/Jobs/RestoreDatabaseJob.php tests/Unit/DatabaseBackupJobsTest.php
git commit -m "feat: add queued jobs for running backups and restores in the background"
```

---

## Task 5: Scheduled backup check command

**Files:**
- Create: `app/Console/Commands/CheckScheduledDatabaseBackup.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/CheckScheduledDatabaseBackupTest.php`

**Interfaces:**
- Consumes: `Setting` constants (Task 2), `RunDatabaseBackupJob` (Task 4).
- Produces: artisan command `db:backup:check`, registered on the scheduler every 5 minutes.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CheckScheduledDatabaseBackupTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\RunDatabaseBackupJob;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckScheduledDatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_when_scheduling_is_disabled(): void
    {
        Queue::fake();
        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, false);

        $this->artisan('db:backup:check')->assertSuccessful();

        Queue::assertNotPushed(RunDatabaseBackupJob::class);
    }

    public function test_fires_a_daily_backup_when_the_configured_time_is_now(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(2, 1));
        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, true);
        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily');
        Setting::set(Setting::BACKUP_SCHEDULE_TIME, '02:00');

        $this->artisan('db:backup:check')->assertSuccessful();

        Queue::assertPushed(RunDatabaseBackupJob::class, fn ($job) => $job->type === 'scheduled' && $job->userId === null);
    }

    public function test_does_not_fire_a_daily_backup_outside_the_configured_time_window(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(14, 0));
        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, true);
        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily');
        Setting::set(Setting::BACKUP_SCHEDULE_TIME, '02:00');

        $this->artisan('db:backup:check')->assertSuccessful();

        Queue::assertNotPushed(RunDatabaseBackupJob::class);
    }

    public function test_weekly_only_fires_on_the_configured_day(): void
    {
        Queue::fake();
        $monday = now()->startOfWeek()->setTime(2, 0);
        $this->travelTo($monday);
        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, true);
        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, 'weekly');
        Setting::set(Setting::BACKUP_SCHEDULE_TIME, '02:00');
        Setting::set(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, $monday->copy()->addDay()->dayOfWeek);

        $this->artisan('db:backup:check')->assertSuccessful();
        Queue::assertNotPushed(RunDatabaseBackupJob::class);

        $this->travelTo($monday->copy()->addDay());
        $this->artisan('db:backup:check')->assertSuccessful();
        Queue::assertPushed(RunDatabaseBackupJob::class);
    }

    public function test_does_not_double_fire_within_the_same_window(): void
    {
        Queue::fake();
        $this->travelTo(now()->setTime(2, 0));
        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, true);
        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily');
        Setting::set(Setting::BACKUP_SCHEDULE_TIME, '02:00');

        $this->artisan('db:backup:check')->assertSuccessful();
        $this->travelTo(now()->addMinutes(3));
        $this->artisan('db:backup:check')->assertSuccessful();

        Queue::assertPushed(RunDatabaseBackupJob::class, 1);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=CheckScheduledDatabaseBackupTest`
Expected: FAIL — `Command "db:backup:check" is not defined.`

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/CheckScheduledDatabaseBackup.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\RunDatabaseBackupJob;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckScheduledDatabaseBackup extends Command
{
    protected $signature = 'db:backup:check';

    protected $description = 'Run a scheduled database backup if the configured cadence is due right now.';

    public function handle(): int
    {
        if (! Setting::getBool(Setting::BACKUP_SCHEDULE_ENABLED, false)) {
            return self::SUCCESS;
        }

        if (! $this->isDue()) {
            return self::SUCCESS;
        }

        Setting::set(Setting::BACKUP_LAST_SCHEDULED_RUN_AT, now()->toIso8601String());
        RunDatabaseBackupJob::dispatch('scheduled', null);

        return self::SUCCESS;
    }

    private function isDue(): bool
    {
        $frequency = Setting::get(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily');
        $time = Setting::get(Setting::BACKUP_SCHEDULE_TIME, '02:00');
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $now = now();
        $scheduledToday = $now->copy()->setTime($hour, $minute);

        if (abs($now->getTimestamp() - $scheduledToday->getTimestamp()) > 300) {
            return false;
        }

        if ($frequency === 'weekly' && $now->dayOfWeek !== (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, 0)) {
            return false;
        }

        if ($frequency === 'monthly' && $now->day !== (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_MONTH, 1)) {
            return false;
        }

        $lastRun = Setting::get(Setting::BACKUP_LAST_SCHEDULED_RUN_AT);

        if ($lastRun && Carbon::parse($lastRun)->greaterThan($scheduledToday)) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 4: Register it on the scheduler**

In `routes/console.php`, add after the existing `Schedule::command('rag:lexicon')...` line:

```php
Schedule::command('mentorships:auto-close')->dailyAt('00:05');
Schedule::command('rag:lexicon')->dailyAt('02:40')->withoutOverlapping();
Schedule::command('db:backup:check')->everyFiveMinutes()->withoutOverlapping();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=CheckScheduledDatabaseBackupTest`
Expected: PASS (all 5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/CheckScheduledDatabaseBackup.php routes/console.php tests/Feature/CheckScheduledDatabaseBackupTest.php
git commit -m "feat: add scheduled backup check command, polled every 5 minutes"
```

---

## Task 6: Permissions, nav group, and the Filament page

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `app/Filament/Pages/DatabaseManagement.php`
- Create: `resources/views/filament/pages/database-management.blade.php`
- Test: `tests/Feature/DatabaseManagementPageTest.php`

**Interfaces:**
- Consumes: `DatabaseBackup` (Task 1), `RunDatabaseBackupJob`, `RestoreDatabaseJob` (Task 4), `Setting` constants (Task 2).
- Produces: `/admin/database-management` page, accessible only to `super_admin`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DatabaseManagementPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Pages\DatabaseManagement;
use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatabaseManagementPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_role_alone_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertFalse(DatabaseManagement::canAccess());
    }

    public function test_super_admin_can_access_the_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertTrue(DatabaseManagement::canAccess());
        $this->get(DatabaseManagement::getUrl())->assertOk();
    }

    public function test_page_lists_backups(): void
    {
        $this->actingAsSuperAdmin();
        DatabaseBackup::create(['filename' => 'listed-backup.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);

        $response = $this->get(DatabaseManagement::getUrl());

        $response->assertOk();
        $response->assertSee('listed-backup.sql.gz');
    }

    public function test_backup_now_dispatches_the_job(): void
    {
        Queue::fake();
        $user = $this->actingAsSuperAdmin();

        Livewire::test(DatabaseManagement::class)->callAction('backup_now');

        Queue::assertPushed(RunDatabaseBackupJob::class, fn ($job) => $job->type === 'manual' && $job->userId === $user->id);
    }

    public function test_restore_action_requires_the_exact_filename_and_then_dispatches_the_job(): void
    {
        Queue::fake();
        $this->actingAsSuperAdmin();
        $backup = DatabaseBackup::create(['filename' => 'restore-me.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);

        Livewire::test(DatabaseManagement::class)
            ->callTableAction('restore', $backup, data: ['confirm_filename' => 'wrong-name.sql.gz']);
        Queue::assertNotPushed(RestoreDatabaseJob::class);

        Livewire::test(DatabaseManagement::class)
            ->callTableAction('restore', $backup, data: ['confirm_filename' => 'restore-me.sql.gz']);
        Queue::assertPushed(RestoreDatabaseJob::class, fn ($job) => $job->backupId === $backup->id);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=DatabaseManagementPageTest`
Expected: FAIL — `Class "App\Filament\Pages\DatabaseManagement" not found`.

- [ ] **Step 3: Register the "App Configuration" nav group**

In `app/Providers/Filament/AdminPanelProvider.php`, in the `navigationGroups([...])` array, add `'App Configuration'` right after `'Organization Units'`:

```php
            ->navigationGroups([
                'Dashboards',
                'Rubric Assessments',
                'Facility Assessment',
                'Training Management',
                'Indicator Catalog',
                'knowledge Base',
                'Reporting',
                'Curriculum',
                'Organization Units',
                'App Configuration',
                'Inventory',
                'Report Management',
                'Reports & Analytics',
                'System Administration',
            ])
```

- [ ] **Step 4: Add the Shield permission**

In `database/seeders/RolePermissionSeeder.php`, in `ensureExtraPermissions()`, add to the `$extras` array:

```php
        $extras = [
            'page_HeadDrmhDashboard',
            'page_EmoncDashboard',
            'page_HeadDrmhReviewMentee',
            'page_MyCertificates',
            'page_MentorCertificates',
            'page_RagChat',
            'use_rag_chat',
            'page_DatabaseManagement',
        ];
```

- [ ] **Step 5: Create the Filament page**

Create `app/Filament/Pages/DatabaseManagement.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\Setting;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class DatabaseManagement extends Page implements HasActions, HasForms, Tables\Contracts\HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Database Management';

    protected static ?string $navigationGroup = 'App Configuration';

    protected static string $view = 'filament.pages.database-management';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DatabaseBackup::query()->latest())
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'scheduled' => 'gray',
                        'pre_restore_safety' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'running', 'pending' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (DatabaseBackup $record): ?string => $record->status === 'failed' ? $record->error_message : null),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? Number::fileSize($state) : '—'),
                Tables\Columns\TextColumn::make('triggeredBy.name')
                    ->label('Triggered By')
                    ->placeholder('Scheduled'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (DatabaseBackup $record): bool => $record->isDownloadable())
                    ->action(fn (DatabaseBackup $record) => Storage::disk($record->disk)->download($record->filename)),
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (DatabaseBackup $record): bool => $record->status === 'completed')
                    ->form([
                        Forms\Components\Placeholder::make('warning')
                            ->hiddenLabel()
                            ->content('This will overwrite the live database with this backup. A safety backup of the current database is taken automatically first, but this is still a destructive action.'),
                        Forms\Components\TextInput::make('confirm_filename')
                            ->label('Type the filename to confirm')
                            ->required(),
                    ])
                    ->action(function (DatabaseBackup $record, array $data): void {
                        if ($data['confirm_filename'] !== $record->filename) {
                            Notification::make()->danger()->title('Filename did not match — restore cancelled.')->send();

                            return;
                        }

                        RestoreDatabaseJob::dispatch($record->id, auth()->id());

                        Notification::make()->warning()->title('Restore started')->body('Taking a safety backup, then restoring. This runs in the background.')->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->action(function (DatabaseBackup $record): void {
                        Storage::disk($record->disk)->delete($record->filename);
                        $record->delete();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('backup_now')
                    ->label('Backup Now')
                    ->icon('heroicon-o-server-stack')
                    ->color('primary')
                    ->action(function (): void {
                        RunDatabaseBackupJob::dispatch('manual', auth()->id());

                        Notification::make()->success()->title('Backup started')->body('Running in the background — refresh to see progress.')->send();
                    }),
                Tables\Actions\Action::make('schedule_settings')
                    ->label('Schedule Settings')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable scheduled backups')
                            ->default(fn () => Setting::getBool(Setting::BACKUP_SCHEDULE_ENABLED, false)),
                        Forms\Components\Select::make('frequency')
                            ->label('Frequency')
                            ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                            ->default(fn () => Setting::get(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily'))
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('day_of_week')
                            ->label('Day of week')
                            ->options([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'])
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, 0))
                            ->visible(fn (Forms\Get $get) => $get('frequency') === 'weekly')
                            ->required(fn (Forms\Get $get) => $get('frequency') === 'weekly'),
                        Forms\Components\Select::make('day_of_month')
                            ->label('Day of month')
                            ->options(array_combine(range(1, 28), range(1, 28)))
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_MONTH, 1))
                            ->visible(fn (Forms\Get $get) => $get('frequency') === 'monthly')
                            ->required(fn (Forms\Get $get) => $get('frequency') === 'monthly'),
                        Forms\Components\TimePicker::make('time')
                            ->label('Time of day')
                            ->seconds(false)
                            ->default(fn () => Setting::get(Setting::BACKUP_SCHEDULE_TIME, '02:00'))
                            ->required(),
                        Forms\Components\TextInput::make('retention_count')
                            ->label('Keep the last N backups')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_RETENTION_COUNT, 14))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, (bool) $data['enabled']);
                        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, $data['frequency']);
                        Setting::set(Setting::BACKUP_SCHEDULE_TIME, $data['time']);
                        Setting::set(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, (int) ($data['day_of_week'] ?? 0));
                        Setting::set(Setting::BACKUP_SCHEDULE_DAY_OF_MONTH, (int) ($data['day_of_month'] ?? 1));
                        Setting::set(Setting::BACKUP_RETENTION_COUNT, (int) $data['retention_count']);

                        Notification::make()->success()->title('Schedule settings saved')->send();
                    }),
            ]);
    }
}
```

- [ ] **Step 6: Create the view**

Create `resources/views/filament/pages/database-management.blade.php`:

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=DatabaseManagementPageTest`
Expected: PASS (all 5 tests)

- [ ] **Step 8: Run the wider suite to check for regressions**

Run: `php artisan test --filter="RolePermissionSeeder|AdminPanelProvider"` — if no dedicated tests exist for those, instead run: `php artisan db:seed --class=RolePermissionSeeder` against a scratch DB (or trust the Task 7 manual pass) and `php artisan route:list --path=admin/database-management` to confirm the page registers without error.

- [ ] **Step 9: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php app/Providers/Filament/AdminPanelProvider.php app/Filament/Pages/DatabaseManagement.php resources/views/filament/pages/database-management.blade.php tests/Feature/DatabaseManagementPageTest.php
git commit -m "feat: add super_admin-only Database Management page under App Configuration"
```

---

## Task 7: Manual end-to-end verification (real mysqldump/mysql)

Not automatable in CI without a live MySQL binary and a disposable database — this is a one-time manual sign-off in the local dev environment, mirroring the browser-verification pattern already used for the assessment team management feature.

- [ ] **Step 1: Confirm `mysqldump`/`mysql` are on `$PATH`**

Run: `which mysqldump && which mysql`
Expected: both print a path. If not, the feature will always show `failed` backups — this is a prerequisite check.

- [ ] **Step 2: Run the full automated suite**

Run: `php artisan test`
Expected: 0 failures (same pre-existing risky-warning noise as the rest of this session is fine).

- [ ] **Step 3: Real backup through the UI**

Log in as a `super_admin` test account, open **App Configuration → Database Management**, click **Backup Now**, wait for the queue worker to pick it up (`composer run dev` runs `queue:listen`), refresh (or let the 5s poll do it) until status flips to `completed`. Confirm a real `.sql.gz` file exists under `storage/app/backups/` and its size is non-trivial (not a 20-byte empty-gzip file).

- [ ] **Step 4: Real download**

Click **Download** on the completed backup, confirm the browser downloads a `.sql.gz` file matching the one on disk.

- [ ] **Step 5: Real restore against a disposable copy**

Do **not** restore against the primary dev database casually. Either: (a) point `.env`'s `DB_DATABASE` at a throwaway copy of the dev DB for this one test, or (b) create a harmless marker row before restoring (e.g. a test `User` with a distinctive name) and confirm it's gone after restoring an *older* backup that predates it — proving the restore actually replaced data rather than silently no-op'ing. Confirm the **type-to-confirm** modal rejects a mismatched filename first. Confirm a `pre_restore_safety` backup row appears, and that restoring *that* safety backup afterward brings the marker row back.

- [ ] **Step 6: Real scheduled run**

Set the schedule to `daily` at a time a couple of minutes in the future via **Schedule Settings**, then run `php artisan db:backup:check` manually (don't wait for cron) once the clock passes that time. Confirm a new `scheduled`-type backup appears and `backup_last_scheduled_run_at` updates.

- [ ] **Step 7: Retention**

Set **Keep the last N backups** to a small number (e.g. 2) in Schedule Settings, trigger a couple more manual backups, confirm the oldest backups' rows *and* files disappear once the count is exceeded.

- [ ] **Step 8: Clean up**

Delete any test backups/rows created during this verification pass that aren't meant to stay (via the page's Delete action), same spirit as the QA-account cleanup done for the team management feature.
