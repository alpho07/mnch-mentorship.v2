<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\MentorshipStallReminderService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The "needs attention" list for stalled facility mentorships, scoped to
 * who's looking:
 *   - Above-site users (super_admin, admin, division, national,
 *     division_lead, national_mentor_lead — see User::isAboveSite()) see
 *     everyone's.
 *   - Lead mentors (county/subcounty/facility/spoke lead roles) see their
 *     own plus anything in their geographic scope (User::scopedCountyIds()).
 *   - Everyone else sees only mentorships they're the mentor on.
 * See StalledMentorships for the admin ops page (reminders, deactivate,
 * delete) — this page is view + continue only, no destructive actions,
 * since a lead seeing a peer's mentorship shouldn't be able to delete it.
 */
class PendingMentorships extends Page
{
    private const LEAD_ROLES = [
        'county_mentor_lead',
        'subcounty_mentor_lead',
        'facility_mentor_lead',
        'spoke_mentor_lead',
    ];

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

        $count = static::scopedStalled(auth()->user())->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Collection<int, array{training: \App\Models\Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool}>
     */
    private static function scopedStalled(User $user): Collection
    {
        $service = app(MentorshipStallReminderService::class);

        if ($user->isAboveSite()) {
            return $service->stalled();
        }

        if ($user->hasRole(self::LEAD_ROLES)) {
            return $service->stalled(mentorId: $user->id, countyIds: $user->scopedCountyIds()->toArray());
        }

        return $service->stalled(mentorId: $user->id);
    }

    /**
     * @return Collection<int, array{training: \App\Models\Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool, continueUrl: string}>
     */
    public function getPending(): Collection
    {
        $service = app(MentorshipStallReminderService::class);

        return static::scopedStalled(auth()->user())
            ->sortByDesc('days_stalled')
            ->map(function (array $row) use ($service) {
                $row['continueUrl'] = $service->continueUrl($row['training'], $row['class'], $row['bucket']);

                return $row;
            })
            ->values();
    }

    protected function getViewData(): array
    {
        return [
            'pending' => $this->getPending(),
            'showsEveryone' => auth()->user()->isAboveSite() || auth()->user()->hasRole(self::LEAD_ROLES),
        ];
    }
}
