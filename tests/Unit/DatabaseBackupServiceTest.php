<?php

namespace Tests\Unit;

use App\Models\DatabaseBackup;
use App\Models\DatabaseRestore;
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

        $old = DatabaseBackup::create(['filename' => 'old.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);
        $old->forceFill(['created_at' => now()->subDay()])->save();

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
        Process::assertNotRan(fn ($process): bool => str_starts_with($process->command, 'gunzip'));
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
}
