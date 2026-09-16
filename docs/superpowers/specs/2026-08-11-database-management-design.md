# Database Management (Backup / Schedule / Restore)

**Date:** 2026-08-11
**Status:** Approved

## Context

The team currently backs up the database by hand — `mysqldump | gzip` run manually before any risky migration, with the results sitting in `database/dbsql/*.sql.gz` (git-ignored, not part of the app). There's no scheduled backup, no restore UI, and no record of what was backed up or when. This adds a proper "Database Management" admin page: on-demand backup, scheduled backup, restore, and a list of what's available — reusing the same underlying `mysqldump`/`mysql` tooling the team already trusts, rather than introducing a new backup package or format.

## Goal

`super_admin`-only Filament page under a new "App Configuration" nav group where an admin can: trigger a backup now, configure a recurring backup schedule (daily/weekly/monthly + time, with retention), see a list of backups with status/size, download a backup, and restore from one — with a mandatory type-to-confirm step and an automatic safety backup taken immediately before any restore.

## Out of scope

- Cloud storage (S3, etc.) — the storage layer is built behind Laravel's `Storage::disk('backups')` abstraction specifically so this is a config change later, not a rewrite, but no cloud disk is configured now.
- Backing up anything other than the database (no file/storage backups).
- Point-in-time recovery / incremental backups — every backup is a full `mysqldump`.
- Multi-tenancy / per-database selection — always backs up the single configured `DB_DATABASE`.

## Data model

### Migration: `create_database_backups_table`

```php
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
```

### Migration: `create_database_restores_table`

```php
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
```

### Models

`App\Models\DatabaseBackup` — `belongsTo(User::class, 'triggered_by')`, `hasMany(DatabaseRestore::class)`. Helper: `isDownloadable(): bool` (status === completed && file still exists on disk).

`App\Models\DatabaseRestore` — `belongsTo(DatabaseBackup::class)`, `belongsTo(DatabaseBackup::class, 'safety_backup_id')` as `safetyBackup()`, `belongsTo(User::class, 'restored_by')`.

## Storage

`config/filesystems.php` — new disk:

```php
'backups' => [
    'driver' => 'local',
    'root' => storage_path('app/backups'),
    'throw' => false,
],
```

Filenames: `backup-{Ymd_His}.sql.gz` (manual/scheduled), `safety-{Ymd_His}.sql.gz` (pre-restore).

## Service: `App\Services\DatabaseBackupService`

```php
public function createBackup(?int $userId, string $type = 'manual'): DatabaseBackup
```
Creates a `DatabaseBackup` row (status `running`, `started_at` now), runs `mysqldump` against a temp `--defaults-extra-file` (host/port/user/password from `config('database.connections.mysql')`, temp file deleted in a `finally` block regardless of outcome) piped to `gzip`, writes into `Storage::disk('backups')`, then sets `status completed`, `size_bytes`, `completed_at` — or `status failed` + `error_message` on any `Process` failure. Returns the row either way (never throws past this method — callers check `status`).

```php
public function restore(DatabaseBackup $backup, int $userId): DatabaseRestore
```
Creates a `DatabaseRestore` row (status `running`). Step 1: calls `createBackup($userId, 'pre_restore_safety')` and records it as `safety_backup_id` — if *that* fails, the restore is aborted before touching anything (status `failed`, error explains the safety backup failed). Step 2: `gunzip < {backup path} | mysql --defaults-extra-file=...`. Sets `completed`/`failed` accordingly.

```php
public function pruneOldBackups(): void
```
Reads `Setting::get('backup_retention_count', 14)`. Across **all** types together (manual/scheduled/pre_restore_safety pooled — simplest mental model), keeps the newest N `completed` backups by `created_at`, deletes the file + row for the rest. Called at the end of `createBackup()` when `type` is `manual` or `scheduled` (not after a safety backup — pruning happens once per restore, not mid-restore).

## Jobs

`App\Jobs\RunDatabaseBackupJob(int $type, ?int $userId)` — thin wrapper calling `DatabaseBackupService::createBackup()`, queued so the HTTP request / scheduler tick returns immediately.

`App\Jobs\RestoreDatabaseJob(int $backupId, int $userId)` — thin wrapper calling `DatabaseBackupService::restore()`.

Both jobs run on the default queue (already active per `composer run dev`'s `queue:listen`). No custom retry logic — a failed backup/restore is recorded as `failed` with the error message visible in the UI; retrying is a new manual action, not automatic (an automatic retry of a *restore* in particular would be actively dangerous).

## Scheduling

Reuses the existing `App\Models\Setting` key/value store (already backing `MentorshipSettings`):

| Key | Meaning |
|---|---|
| `backup_schedule_enabled` | bool |
| `backup_schedule_frequency` | `daily` \| `weekly` \| `monthly` |
| `backup_schedule_time` | `HH:MM` |
| `backup_schedule_day_of_week` | 0–6, used when frequency is weekly |
| `backup_schedule_day_of_month` | 1–28 (capped to avoid month-length issues), used when frequency is monthly |
| `backup_retention_count` | int, default 14 |
| `backup_last_scheduled_run_at` | ISO timestamp, written by the check command itself |

New command `App\Console\Commands\CheckScheduledDatabaseBackup` (`db:backup:check`): if `backup_schedule_enabled` is false, no-op. Otherwise computes whether "now" matches the configured cadence and time window (±5 minutes, matching the scheduler tick below) and is strictly after `backup_last_scheduled_run_at`; if due, dispatches `RunDatabaseBackupJob('scheduled', null)` and updates `backup_last_scheduled_run_at` immediately (before the job finishes) so a slow job can't cause a double-fire on the next tick.

`routes/console.php` addition:
```php
Schedule::command('db:backup:check')->everyFiveMinutes()->withoutOverlapping();
```

This depends on `php artisan schedule:run` already being cronned every minute on the server — already required by the existing `mentorships:auto-close`/`rag:lexicon` entries in the same file, so no new operational dependency, just confirming it's live.

## Filament UI

New page `App\Filament\Pages\DatabaseManagement`, `protected static ?string $navigationGroup = 'App Configuration';` (new nav group, registered in `AdminPanelProvider::navigationGroups()`).

```php
public static function canAccess(): bool
{
    return auth()->check() && auth()->user()->hasRole('super_admin');
}
```

Implements `HasTable` (same pattern as `MentorshipSettings`), table backed by `DatabaseBackup::query()->latest()`, `->poll('5s')` so running backups/restores update live without a manual refresh. Columns: filename, type (badge), status (badge — pending/running spin, completed green, failed red with error tooltip), size (human-readable via `Number::fileSize()`), triggered by (name or "Scheduled"), created_at.

Row actions:
- **Download** — visible when `status === 'completed'` and the file still exists; streams via `Storage::disk('backups')->download(...)`.
- **Restore** — visible when `status === 'completed'`; modal requires typing the exact filename to confirm (`TextInput::make('confirm_filename')->rule(...)` checked in the action, not just client-side), explains the automatic safety-backup step, then dispatches `RestoreDatabaseJob`.
- **Delete** — visible always; deletes the file + row (manual cleanup on top of automatic retention).

Header actions:
- **Backup Now** — dispatches `RunDatabaseBackupJob('manual', auth()->id())`, success notification says it's running in the background.
- **Schedule Settings** — modal form (frequency select, conditional day-of-week/day-of-month, time picker, retention number input, enabled toggle), writes through `Setting::set()`.

## Permissions

New Shield permission `page_DatabaseManagement`, granted only to `super_admin` in `RolePermissionSeeder` (mirrors the existing `page_HeadDrmhReviewMentee` pattern already in the codebase). `canAccess()` above checks the role directly rather than the permission, matching how other super-admin-only pages in this codebase already gate access, for defense in depth beyond Shield's nav visibility.

## Testing

- Service unit-ish tests using a fake/mocked `Process` (Laravel's `Process::fake()`) to verify: success path writes a `completed` row with size; failure path writes `failed` + error without throwing; restore takes a safety backup first and aborts cleanly if that fails; retention keeps exactly N and deletes the rest (files + rows).
- `db:backup:check` command tests: doesn't fire when disabled; fires when due for each frequency; doesn't double-fire within the same window; updates `backup_last_scheduled_run_at`.
- Filament page tests: `canAccess()` denies non-super_admin; table lists backups; Restore action requires the exact filename match; header actions dispatch the right jobs (`Queue::fake()`/`Bus::fake()`).
- No real `mysqldump`/`mysql` process is exercised in the automated suite (would require a real MySQL binary + live DB in CI) — that path gets one manual end-to-end verification (real backup → real restore) in the local dev environment as part of implementation sign-off, same as the browser-verification pattern used for the assessment team management feature.
