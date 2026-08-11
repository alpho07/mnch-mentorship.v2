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

        if ($lastRun && Carbon::parse($lastRun)->greaterThanOrEqualTo($scheduledToday)) {
            return false;
        }

        return true;
    }
}
