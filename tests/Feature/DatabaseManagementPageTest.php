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

    public function test_backup_now_dispatches_the_job(): void
    {
        Queue::fake();
        $user = $this->actingAsSuperAdmin();

        Livewire::test(DatabaseManagement::class)->callTableAction('backup_now');

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
