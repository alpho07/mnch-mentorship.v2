<?php

namespace Tests\Feature;

use App\Filament\Pages\DatabaseManagement;
use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\DatabaseRestore;
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
        $user = User::factory()->create(['name' => 'Test Super Admin']);
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

    public function test_backup_now_creates_a_pending_row_immediately_and_dispatches_the_job(): void
    {
        Queue::fake();
        $user = $this->actingAsSuperAdmin();

        Livewire::test(DatabaseManagement::class)->callTableAction('backup_now');

        // The row must exist the instant the action runs — not only once a
        // queue worker eventually processes the job — otherwise nothing is
        // visible if no worker happens to be running.
        $backup = DatabaseBackup::sole();
        $this->assertSame('pending', $backup->status);
        $this->assertSame('manual', $backup->type);
        $this->assertSame($user->id, $backup->triggered_by);
        Queue::assertPushed(RunDatabaseBackupJob::class, fn ($job) => $job->backupId === $backup->id);
    }

    public function test_restore_action_requires_the_exact_filename_and_then_creates_a_pending_row_and_dispatches_the_job(): void
    {
        Queue::fake();
        $this->actingAsSuperAdmin();
        $backup = DatabaseBackup::create(['filename' => 'restore-me.sql.gz', 'disk' => 'backups', 'type' => 'manual', 'status' => 'completed']);

        Livewire::test(DatabaseManagement::class)
            ->callTableAction('restore', $backup, data: ['confirm_filename' => 'wrong-name.sql.gz']);
        Queue::assertNotPushed(RestoreDatabaseJob::class);
        $this->assertSame(0, DatabaseRestore::count());

        Livewire::test(DatabaseManagement::class)
            ->callTableAction('restore', $backup, data: ['confirm_filename' => 'restore-me.sql.gz']);

        $restore = DatabaseRestore::sole();
        $this->assertSame('pending', $restore->status);
        $this->assertSame($backup->id, $restore->database_backup_id);
        Queue::assertPushed(RestoreDatabaseJob::class, fn ($job) => $job->restoreId === $restore->id);
    }
}
