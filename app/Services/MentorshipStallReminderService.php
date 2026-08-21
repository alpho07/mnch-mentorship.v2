<?php

namespace App\Services;

use App\Mail\EmoncNotificationMail;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MentorshipClass;
use App\Models\MentorshipStallReminder;
use App\Models\Setting;
use App\Models\Training;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * Finds facility mentorships stalled in "draft" — never started, and stuck
 * at one of three points: no class created at all, a class with nobody
 * enrolled, or a class with a mentee but no curriculum modules assigned —
 * and reminds the assigned mentor. Complements (doesn't replace)
 * PendingGuidedSetupNotice, which only nudges the current mentor's own
 * most-recent *unfinished-wizard* training on their dashboard; this covers
 * every mentor, every stalled mentorship, past the wizard, via email.
 */
class MentorshipStallReminderService
{
    /**
     * Every currently-stalled mentorship, with its bucket, last-activity
     * date, days stalled, and whether it's due for a reminder right now
     * (past the threshold since last activity, and either never reminded or
     * the last reminder was itself more than a threshold-period ago). Used
     * by both the admin center listing and the scheduled command.
     *
     * $countyIds, when given, scopes to mentorships in those counties
     * (a lead mentor's geographic scope) — combined with $mentorId (if both
     * given) as "mine OR in my counties", not an intersection, so a lead
     * still sees their own mentorship even if it's missing a county_id.
     *
     * @return Collection<int, array{training: Training, class: ?MentorshipClass, bucket: string, last_activity_at: Carbon, days_stalled: int, last_reminded_at: ?Carbon, due: bool}>
     */
    public function stalled(?int $thresholdDays = null, ?int $mentorId = null, ?array $countyIds = null): Collection
    {
        $thresholdDays ??= Setting::getInt(Setting::STALL_REMINDER_THRESHOLD_DAYS, 7);

        $trainings = Training::where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->where('status', 'draft')
            ->when($mentorId && $countyIds, fn ($query) => $query->where(function ($q) use ($mentorId, $countyIds) {
                $q->where('mentor_id', $mentorId)->orWhereIn('county_id', $countyIds);
            }))
            ->when($mentorId && ! $countyIds, fn ($query) => $query->where('mentor_id', $mentorId))
            ->when(! $mentorId && $countyIds, fn ($query) => $query->whereIn('county_id', $countyIds))
            ->with(['mentorshipClasses.participants', 'mentorshipClasses.classModules'])
            ->get();

        $lastReminders = MentorshipStallReminder::whereIn('training_id', $trainings->pluck('id'))
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('training_id')
            ->map(fn (Collection $reminders) => $reminders->first()->sent_at);

        return $trainings
            ->map(function (Training $training) use ($lastReminders, $thresholdDays) {
                $classification = $this->classify($training);

                if (! $classification) {
                    // Has a started class + mentee — Training::canActivate()
                    // would already allow this to be active; it just hasn't
                    // been flipped yet. Not this service's concern.
                    return null;
                }

                [$bucket, $lastActivityAt, $class] = $classification;
                $lastRemindedAt = $lastReminders->get($training->id);

                return [
                    'training' => $training,
                    'class' => $class,
                    'bucket' => $bucket,
                    'last_activity_at' => $lastActivityAt,
                    'days_stalled' => (int) $lastActivityAt->diffInDays(now()),
                    'last_reminded_at' => $lastRemindedAt,
                    'due' => $this->isDue($lastActivityAt, $lastRemindedAt, $thresholdDays),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{training: Training, bucket: string, last_activity_at: Carbon, days_stalled: int, last_reminded_at: ?Carbon, due: bool}>
     */
    public function due(?int $thresholdDays = null): Collection
    {
        return $this->stalled($thresholdDays)->filter(fn (array $row) => $row['due'])->values();
    }

    /**
     * Sends reminders for every currently-due mentorship. $sentBy is null
     * for the scheduled command, or the acting admin for a manual send.
     *
     * @return array{sent: int, buckets: array<string, int>}
     */
    public function sendDueReminders(?User $sentBy = null, ?int $thresholdDays = null): array
    {
        $due = $this->due($thresholdDays);
        $buckets = ['no_class' => 0, 'no_mentee' => 0, 'no_modules' => 0];

        foreach ($due as $row) {
            $this->send($row['training'], $row['bucket'], $sentBy);
            $buckets[$row['bucket']]++;
        }

        return ['sent' => $due->count(), 'buckets' => $buckets];
    }

    public function send(Training $training, string $bucket, ?User $sentBy = null): void
    {
        $mentor = $training->mentor;

        MentorshipStallReminder::create([
            'training_id' => $training->id,
            'bucket' => $bucket,
            'sent_by' => $sentBy?->id,
            'sent_at' => now(),
        ]);

        if (! $mentor) {
            return;
        }

        [$heading, $message] = $this->copyFor($bucket, $training);
        $actionUrl = Route::has('filament.admin.resources.mentorship.edit')
            ? route('filament.admin.resources.mentorship.edit', ['record' => $training->id])
            : null;

        Notification::make()
            ->title($heading)
            ->body($message)
            ->warning()
            ->sendToDatabase($mentor);

        if (empty($mentor->email)) {
            return;
        }

        try {
            Mail::to($mentor->email)
                ->queue(new EmoncNotificationMail($mentor, $heading, $heading, $message, $actionUrl, 'Open Mentorship'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{0: string, 1: Carbon, 2: ?MentorshipClass}|null null if the training doesn't belong in any stalled bucket.
     */
    private function classify(Training $training): ?array
    {
        $classes = $training->mentorshipClasses;

        if ($classes->isEmpty()) {
            return [MentorshipStallReminder::BUCKET_NO_CLASS, Carbon::parse($training->created_at), null];
        }

        // A started class already means Training::canActivate() would pass —
        // that's a data-consistency gap for the app-level guard, not a
        // "stalled setup" case this reminder handles.
        if ($classes->contains(fn (MentorshipClass $c) => in_array($c->status, ['active', 'completed'], true))) {
            return null;
        }

        $latestClass = $classes->sortByDesc('created_at')->first();
        $participants = $latestClass->participants->whereIn('status', ['enrolled', 'active', 'completed']);

        if ($participants->isEmpty()) {
            return [MentorshipStallReminder::BUCKET_NO_MENTEE, Carbon::parse($latestClass->created_at), $latestClass];
        }

        if ($latestClass->classModules->isEmpty()) {
            $lastActivity = $participants->max(fn (ClassParticipant $p) => $p->enrolled_at ?? $p->created_at);

            return [MentorshipStallReminder::BUCKET_NO_MODULES, Carbon::parse($lastActivity), $latestClass];
        }

        // Has mentee(s) and modules but still draft — start() just hasn't
        // been clicked. Treat "no_modules" copy as close enough; the mentor
        // action either way is "open the mentorship and press Start".
        $lastActivity = $latestClass->classModules->max(fn (ClassModule $m) => $m->created_at);

        return [MentorshipStallReminder::BUCKET_NO_MODULES, Carbon::parse($lastActivity), $latestClass];
    }

    private function isDue(Carbon $lastActivityAt, ?Carbon $lastRemindedAt, int $thresholdDays): bool
    {
        if ($lastActivityAt->gt(now()->subDays($thresholdDays))) {
            return false;
        }

        if ($lastRemindedAt && $lastRemindedAt->gt(now()->subDays($thresholdDays))) {
            return false;
        }

        return true;
    }

    /**
     * The right next screen for a mentor to resolve a stalled row — jumps
     * straight to whichever step is actually blocking, not just the
     * mentorship's edit page. Shared by the admin center and the
     * mentor-facing "Pending Mentorships" page.
     */
    public function continueUrl(Training $training, ?MentorshipClass $class, string $bucket): string
    {
        return match ($bucket) {
            MentorshipStallReminder::BUCKET_NO_CLASS => \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]),
            MentorshipStallReminder::BUCKET_NO_MENTEE => $class
                ? \App\Filament\Resources\MentorshipTrainingResource::getUrl('class-mentees', ['training' => $training->id, 'class' => $class->id])
                : \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]),
            MentorshipStallReminder::BUCKET_NO_MODULES => $class
                ? \App\Filament\Resources\MentorshipTrainingResource::getUrl('class-modules', ['training' => $training->id, 'class' => $class->id])
                : \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]),
            default => \App\Filament\Resources\MentorshipTrainingResource::getUrl('edit', ['record' => $training->id]),
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function copyFor(string $bucket, Training $training): array
    {
        $title = $training->title ?: 'Your mentorship';

        return match ($bucket) {
            MentorshipStallReminder::BUCKET_NO_CLASS => [
                'Mentorship needs a class',
                "\"{$title}\" was set up but has no class yet. Create a class to start enrolling mentees.",
            ],
            MentorshipStallReminder::BUCKET_NO_MENTEE => [
                'Mentorship needs mentees',
                "\"{$title}\" has a class but no mentees enrolled yet. Add mentees to get this mentorship moving.",
            ],
            MentorshipStallReminder::BUCKET_NO_MODULES => [
                'Mentorship ready to start',
                "\"{$title}\" has mentees enrolled and just needs curriculum modules assigned (or Start pressed) to begin.",
            ],
            default => [
                'Mentorship needs attention',
                "\"{$title}\" hasn't started yet.",
            ],
        };
    }
}
