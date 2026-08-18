<?php

namespace App\Services;

use App\Mail\EmoncNotificationMail;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * Emails invited mentees when a class or module starts or ends. Applies to
 * every mentorship program (not just EmONC) — MentorshipClass::start()/
 * complete() and ClassModule::start()/complete() are the shared lifecycle
 * chokepoints used by both the API and Filament actions, so hooking in here
 * covers every creation/management path uniformly.
 */
class ClassLifecycleNotificationService
{
    public function classStarted(MentorshipClass $class): void
    {
        $trainingTitle = $class->training?->title ?? 'your mentorship';

        $this->notifyInvitedMentees(
            $class,
            'Class Started',
            "{$class->name} Has Started",
            "\"{$class->name}\" ({$trainingTitle}) has started. You can now access its modules and confirm attendance as sessions run."
        );
    }

    public function classCompleted(MentorshipClass $class): void
    {
        $trainingTitle = $class->training?->title ?? 'your mentorship';

        $this->notifyEnrolledMentees(
            $class,
            'Class Completed',
            "{$class->name} Has Ended",
            "\"{$class->name}\" ({$trainingTitle}) has ended. Thank you for participating — check your progress for final results."
        );
    }

    public function moduleStarted(ClassModule $classModule): void
    {
        $moduleName = $classModule->programModule?->name ?? 'A module';
        $class = $classModule->mentorshipClass;

        if (! $class) {
            return;
        }

        $this->notifyInvitedMentees(
            $class,
            'Module Started',
            "{$moduleName} Has Started",
            "\"{$moduleName}\" has started in {$class->name}. Confirm your attendance once the session begins."
        );
    }

    // Module *completion* already has a per-mentee notification —
    // ClassModule::complete() calls EmoncNotificationService::moduleCompleted()
    // for each attended participant's own progress record. Adding a second,
    // broadcast "module ended" email here would double-notify. classStarted()/
    // classCompleted()/moduleStarted() above are genuinely new coverage.

    private function notifyInvitedMentees(MentorshipClass $class, string $databaseTitle, string $emailSubject, string $emailMessage): void
    {
        $this->notifyMentees($class, $databaseTitle, $emailSubject, $emailMessage, onlyInvitedWithEmail: true);
    }

    private function notifyEnrolledMentees(MentorshipClass $class, string $databaseTitle, string $emailSubject, string $emailMessage): void
    {
        $this->notifyMentees($class, $databaseTitle, $emailSubject, $emailMessage, onlyInvitedWithEmail: false);
    }

    private function notifyMentees(MentorshipClass $class, string $databaseTitle, string $emailSubject, string $emailMessage, bool $onlyInvitedWithEmail): void
    {
        $actionUrl = Route::has('mentee.class.progress') ? route('mentee.class.progress', $class->id) : null;

        $participants = $class->participants()
            ->whereIn('status', ['enrolled', 'active']);

        if ($onlyInvitedWithEmail) {
            $participants
                ->whereNotNull('invitation_sent_at')
                ->whereHas('user', fn ($query) => $query->whereNotNull('email')->where('email', '!=', ''));
        }

        $users = $participants->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($users as $user) {
            Notification::make()
                ->title($databaseTitle)
                ->body($emailMessage)
                ->success()
                ->sendToDatabase($user);

            if (empty($user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)
                    ->queue(new EmoncNotificationMail($user, $emailSubject, $emailSubject, $emailMessage, $actionUrl, 'View My Progress'));
            } catch (\Throwable $e) {
                // Fail silently — don't block class/module lifecycle transitions for email errors.
                report($e);
            }
        }
    }
}
