<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Services\MentorshipStallReminderService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Admin center for the mentorships stuck in "draft" — never started because
 * no class exists, no mentee was enrolled, or mentees are enrolled but no
 * curriculum modules were assigned. Lets an admin trigger the same reminder
 * the scheduled mentorships:send-stall-reminders command sends, either for
 * one mentorship or for everything currently due, without waiting for the
 * next scheduled run.
 */
class StalledMentorships extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Stalled Mentorships';

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stalled-mentorships';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_StalledMentorships');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_StalledMentorships');
    }

    public function getTitle(): string
    {
        return 'Stalled Mentorships';
    }

    public function sendReminder(int $trainingId, string $bucket): void
    {
        $training = Training::find($trainingId);

        if (! $training) {
            Notification::make()->title('Mentorship not found')->danger()->send();

            return;
        }

        app(MentorshipStallReminderService::class)->send($training, $bucket, auth()->user());

        Notification::make()
            ->title('Reminder sent to '.($training->mentor?->name ?? 'the mentor'))
            ->success()
            ->send();
    }

    public function sendAllDue(): void
    {
        $result = app(MentorshipStallReminderService::class)->sendDueReminders(auth()->user());

        Notification::make()
            ->title("Sent {$result['sent']} reminder(s)")
            ->body("No class: {$result['buckets']['no_class']} · No mentee: {$result['buckets']['no_mentee']} · No modules: {$result['buckets']['no_modules']}")
            ->success()
            ->send();
    }

    /**
     * "Make inactive" — a soft, reversible pause. Distinct from delete: the
     * record and all its classes/mentees stay intact, just parked as
     * cancelled. Reachable again via reactivateMentorship().
     */
    public function deactivateMentorship(int $trainingId): void
    {
        $training = Training::find($trainingId);

        if (! $training) {
            Notification::make()->title('Mentorship not found')->danger()->send();

            return;
        }

        $training->update(['status' => 'cancelled']);

        Notification::make()->title('Mentorship marked inactive')->success()->send();
    }

    public function reactivateMentorship(int $trainingId): void
    {
        $training = Training::find($trainingId);

        if (! $training) {
            Notification::make()->title('Mentorship not found')->danger()->send();

            return;
        }

        // Back to draft, not active — Training::canActivate() would reject
        // active/completed here anyway unless it already has a started
        // class with a mentee; draft is always the safe landing state.
        $training->update(['status' => 'draft']);

        Notification::make()->title('Mentorship reactivated')->success()->send();
    }

    /**
     * Soft delete — Training uses SoftDeletes, so this is reversible via
     * restoreMentorship(). Classes/participants aren't touched.
     */
    public function deleteMentorship(int $trainingId): void
    {
        $training = Training::find($trainingId);

        if (! $training) {
            Notification::make()->title('Mentorship not found')->danger()->send();

            return;
        }

        $training->delete();

        Notification::make()->title('Mentorship deleted')->success()->send();
    }

    public function restoreMentorship(int $trainingId): void
    {
        $training = Training::withTrashed()->find($trainingId);

        if (! $training) {
            Notification::make()->title('Mentorship not found')->danger()->send();

            return;
        }

        $training->restore();

        Notification::make()->title('Mentorship restored')->success()->send();
    }

    /**
     * @return Collection<int, array{training: Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool, editUrl: string, continueUrl: string}>
     */
    public function getStalled(): Collection
    {
        $service = app(MentorshipStallReminderService::class);

        return $service->stalled()
            ->sortByDesc('days_stalled')
            ->map(function (array $row) use ($service) {
                $row['editUrl'] = MentorshipTrainingResource::getUrl('edit', ['record' => $row['training']->id]);
                $row['continueUrl'] = $service->continueUrl($row['training'], $row['class'], $row['bucket']);

                return $row;
            })
            ->values();
    }

    /**
     * Cancelled (still present) or soft-deleted facility mentorships — the
     * undo surface for deactivateMentorship() / deleteMentorship(). Limited
     * to the last 90 days so this doesn't grow unbounded forever.
     *
     * @return Collection<int, Training>
     */
    public function getRecentlyActioned(): Collection
    {
        $cutoff = now()->subDays(90);

        $cancelled = Training::where('type', 'facility_mentorship')
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', $cutoff)
            ->with('mentor')
            ->get();

        $deleted = Training::onlyTrashed()
            ->where('type', 'facility_mentorship')
            ->where('deleted_at', '>=', $cutoff)
            ->with('mentor')
            ->get();

        return $cancelled->concat($deleted)->sortByDesc(fn (Training $t) => $t->deleted_at ?? $t->updated_at)->values();
    }

    protected function getViewData(): array
    {
        $stalled = $this->getStalled();

        return [
            'stalled' => $stalled,
            'dueCount' => $stalled->where('due', true)->count(),
            'recentlyActioned' => $this->getRecentlyActioned(),
        ];
    }
}
