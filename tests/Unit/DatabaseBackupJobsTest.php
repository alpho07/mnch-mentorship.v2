<?php

namespace Tests\Unit;

use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_database_backup_job_runs_an_already_pending_backup_via_the_service(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result()]);
        $user = User::factory()->create();
        $pending = app(DatabaseBackupService::class)->createPendingBackup($user->id, 'manual');

        (new RunDatabaseBackupJob($pending->id))->handle(app(DatabaseBackupService::class));

        $backup = $pending->fresh();
        $this->assertSame('completed', $backup->status);
        $this->assertSame($user->id, $backup->triggered_by);
    }

    public function test_restore_database_job_runs_an_already_pending_restore_via_the_service(): void
    {
        Storage::fake('backups');
        Process::fake(['mysqldump*' => Process::result(), 'gunzip*' => Process::result()]);
        $user = User::factory()->create();
        $backup = DatabaseBackup::create(['filename' => 'target.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        Storage::disk('backups')->put('target.sql.gz', 'fake dump');
        $pending = app(DatabaseBackupService::class)->createPendingRestore($backup, $user->id);

        (new RestoreDatabaseJob($pending->id))->handle(app(DatabaseBackupService::class));

        $this->assertSame('completed', $pending->fresh()->status);
    }
}
