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
        // Bulk query-builder update — bypasses Eloquent events, so the
        // Training::canActivate() guard doesn't run here. Mirror its rule
        // directly: only auto-complete mentorships that genuinely started
        // (a non-draft class with at least one enrolled mentee). Pilots are
        // exempt from the guard, same as canActivate().
        $count = Training::where('type', 'facility_mentorship')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(function ($query) {
                $query->where('is_pilot', true)
                    ->orWhereHas('mentorshipClasses', function ($classQuery) {
                        $classQuery->whereIn('status', ['active', 'completed'])
                            ->whereHas('participants', fn ($q) => $q->whereIn('status', ['enrolled', 'active', 'completed']));
                    });
            })
            ->update(['status' => 'completed']);

        $this->info("Auto-closed {$count} mentorships.");

        return Command::SUCCESS;
    }
}
