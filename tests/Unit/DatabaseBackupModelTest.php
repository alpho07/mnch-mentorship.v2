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
