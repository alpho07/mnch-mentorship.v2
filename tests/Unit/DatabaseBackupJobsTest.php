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
