<?php

namespace App\Console\Commands;

use App\Models\Training;
use Illuminate\Console\Command;

class AutoCloseMentorships extends Command
{
    protected $signature = 'mentorships:auto-close';
    protected $description = 'Mark facility mentorships whose end_date has passed as completed';

    public function handle(): int
    {
        $count = Training::where('type', 'facility_mentorship')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->update(['status' => 'completed']);

        $this->info("Auto-closed {$count} mentorships.");

        return Command::SUCCESS;
    }
}
