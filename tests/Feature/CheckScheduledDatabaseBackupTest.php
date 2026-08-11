<?php

namespace Tests\Feature;

use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
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

        // The pending row is inserted synchronously by the command itself
        // (visible immediately, before any worker runs the job) — the job
        // only carries its id.
        $backup = DatabaseBackup::sole();
        $this->assertSame('scheduled', $backup->type);
        $this->assertNull($backup->triggered_by);
        Queue::assertPushed(RunDatabaseBackupJob::class, fn ($job) => $job->backupId === $backup->id);
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
