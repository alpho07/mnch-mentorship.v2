<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\MentorshipStallReminderService;
use Illuminate\Console\Command;

class SendMentorshipStallReminders extends Command
{
    protected $signature = 'mentorships:send-stall-reminders {--force : Send even if Setting::STALL_REMINDER_ENABLED is off}';

    protected $description = 'Email mentors whose facility mentorships have stalled in draft (no class, no mentee, or no modules assigned).';

    public function handle(MentorshipStallReminderService $service): int
    {
        if (! $this->option('force') && ! Setting::getBool(Setting::STALL_REMINDER_ENABLED, true)) {
            $this->info('Stall reminders are disabled (Mentorship Settings) — skipping.');

            return self::SUCCESS;
        }

        $result = $service->sendDueReminders();

        $this->info("Sent {$result['sent']} stall reminder(s): ".
            "{$result['buckets']['no_class']} no-class, ".
            "{$result['buckets']['no_mentee']} no-mentee, ".
            "{$result['buckets']['no_modules']} no-modules.");

        return self::SUCCESS;
    }
}
