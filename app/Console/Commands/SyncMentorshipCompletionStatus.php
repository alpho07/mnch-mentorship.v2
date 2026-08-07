<?php

namespace App\Console\Commands;

use App\Models\ClassParticipant;
use Illuminate\Console\Command;

class SyncMentorshipCompletionStatus extends Command
{
    protected $signature = 'mentorship:sync-completion-status {--apply : Actually apply the changes instead of only reporting them}';

    protected $description = 'Backfill ClassParticipant.status to completed for participants who already satisfy hasCompletedAllModules() but are stuck at an earlier status.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $candidates = ClassParticipant::where('status', '!=', 'completed')->get();

        $ready = $candidates->filter(fn (ClassParticipant $p) => $p->hasCompletedAllModules());

        if ($ready->isEmpty()) {
            $this->info('No stuck participants found — nothing to do.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Applying' : 'Would apply (dry run — pass --apply to actually change data)')." completion status for {$ready->count()} participant(s):");

        foreach ($ready as $participant) {
            $this->line("  - ClassParticipant #{$participant->id} (user_id={$participant->user_id}, mentorship_class_id={$participant->mentorship_class_id})");

            if ($apply) {
                $participant->syncCompletionStatus();
            }
        }

        return self::SUCCESS;
    }
}
