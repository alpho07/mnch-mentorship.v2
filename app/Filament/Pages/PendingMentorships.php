<?php

namespace App\Filament\Pages;

use App\Services\MentorshipStallReminderService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * A mentor's own "needs attention" list — every facility mentorship they
 * own that's stalled in draft (no class, no mentee, or no modules
 * assigned), with a direct link to whichever step is actually blocking it.
 * Scoped strictly to the logged-in mentor via mentor_id — see
 * StalledMentorships for the admin-facing, all-mentors equivalent.
 */
class PendingMentorships extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Pending Mentorships';

    protected static ?string $navigationGroup = 'Training Management';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.pending-mentorships';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('create_mentorship::training');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('create_mentorship::training');
    }

    public function getTitle(): string
    {
        return 'Pending Mentorships';
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $count = app(MentorshipStallReminderService::class)->stalled(mentorId: auth()->id())->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Collection<int, array{training: \App\Models\Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool, continueUrl: string}>
     */
    public function getPending(): Collection
    {
        $service = app(MentorshipStallReminderService::class);

        return $service->stalled(mentorId: auth()->id())
            ->sortByDesc('days_stalled')
            ->map(function (array $row) use ($service) {
                $row['continueUrl'] = $service->continueUrl($row['training'], $row['class'], $row['bucket']);

                return $row;
            })
            ->values();
    }

    protected function getViewData(): array
    {
        return ['pending' => $this->getPending()];
    }
}
