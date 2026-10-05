<?php

namespace App\Filament\Pages;

use App\Models\Training;
use App\Models\User;
use App\Services\MentorshipStallReminderService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The "needs attention" list for facility mentorships stalled in "draft"
 * — never started because no class exists, no mentee was enrolled, or
 * mentees are enrolled but no curriculum modules were assigned. This is the
 * mentor-facing view (view + continue only, no destructive actions).
 *
 * Scoping rules, matching StalledMentorships:
 *   - Above-site users (super_admin, admin, division, national,
 *     division_lead, national_mentor_lead — see User::isAboveSite()) see
 *     everyone's.
 *   - Lead mentors (county/subcounty/facility/spoke lead roles) see their
 *     own plus anything in their geographic scope (User::scopedCountyIds()).
 *   - Everyone else sees only mentorships they're the mentor on.
 *
 * The table's underlying query is Training::query() over the stalled ids
 * (real columns: title, mentor, county, created_at — so Filament's
 * search/sort/pagination work natively), but bucket/days-stalled/due/
 * last-reminded are computed values from
 * MentorshipStallReminderService::stalled() — the single source of truth
 * also used by the scheduled command and the admin StalledMentorships page.
 * Those computed values are memoized per-request into $stalledById and
 * looked up in column/filter/action closures rather than recomputed, so the
 * stall classification logic is never duplicated here.
 */
class PendingMentorships extends Page implements HasTable
{
    use InteractsWithTable;

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

    /** @var Collection<int, array{training: Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool, continueUrl: string}>|null */
    private ?Collection $stalledById = null;

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
     * Every currently-stalled mentorship for the given user, unscoped.
     *
     * @return Collection<int, array{training: Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool}>
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
     * Memoized per request — the table() method's columns/filters/actions and
     * the navigation badge all read from this without re-querying the service.
     */
    private function stalledById(): Collection
    {
        return $this->stalledById ??= static::scopedStalled(auth()->user())
            ->mapWithKeys(function (array $row) {
                $row['continueUrl'] = app(MentorshipStallReminderService::class)
                    ->continueUrl($row['training'], $row['class'], $row['bucket']);

                return [$row['training']->id => $row];
            });
    }

    public function showsEveryone(): bool
    {
        $user = auth()->user();

        return $user->isAboveSite() || $user->hasRole(self::LEAD_ROLES);
    }

    public function table(Table $table): Table
    {
        $stalledById = $this->stalledById();

        return $table
            ->query(
                Training::query()
                    ->whereIn('id', $stalledById->keys())
                    ->with(['mentor', 'county'])
            )
            ->heading('Pending Mentorships')
            ->description(function () {
                if ($this->showsEveryone()) {
                    return 'Mentorships that haven\'t started yet — yours and everyone in your scope. Pick up where they left off.';
                }

                return 'Mentorships you\'re set as mentor for that haven\'t started yet — pick up where you left off.';
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Mentorship')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (Training $record): string => $stalledById[$record->id]['continueUrl']),
                ...($this->showsEveryone() ? [
                    Tables\Columns\TextColumn::make('mentor.name')
                        ->label('Mentor')
                        ->searchable()
                        ->sortable()
                        ->placeholder('Unassigned'),
                ] : []),
                Tables\Columns\TextColumn::make('bucket')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Training $record): string => $stalledById[$record->id]['bucket'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'no_class' => 'Needs a class',
                        'no_mentee' => 'Needs mentees',
                        'no_modules' => 'Needs modules / start',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'no_class' => 'danger',
                        'no_mentee' => 'warning',
                        'no_modules' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('days_stalled')
                    ->label('Stalled')
                    ->state(fn (Training $record): string => $stalledById[$record->id]['days_stalled'].' '.str($stalledById[$record->id]['days_stalled'] === 1 ? 'day' : 'days')),
                Tables\Columns\TextColumn::make('last_reminded_at')
                    ->label('Last reminded')
                    ->state(fn (Training $record) => $stalledById[$record->id]['last_reminded_at']?->diffForHumans() ?? 'Never')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('due')
                    ->label('Due')
                    ->badge()
                    ->state(fn (Training $record): string => $stalledById[$record->id]['due'] ? 'Due' : '—')
                    ->color(fn (Training $record): string => $stalledById[$record->id]['due'] ? 'warning' : 'gray')
                    ->visible($this->showsEveryone()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bucket')
                    ->label('Status')
                    ->options([
                        'no_class' => 'No class created',
                        'no_mentee' => 'No mentees enrolled',
                        'no_modules' => 'No modules assigned',
                    ])
                    ->query(function (Builder $query, array $data) use ($stalledById) {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $ids = $stalledById->filter(fn (array $row) => $row['bucket'] === $data['value'])->keys();
                        $query->whereIn('id', $ids);
                    }),
                Tables\Filters\Filter::make('due')
                    ->label('Due for reminder')
                    ->toggle()
                    ->query(function (Builder $query) use ($stalledById) {
                        $ids = $stalledById->filter(fn (array $row) => $row['due'])->keys();
                        $query->whereIn('id', $ids);
                    }),
                ...($this->showsEveryone() ? [
                    Tables\Filters\SelectFilter::make('mentor_id')
                        ->label('Mentor')
                        ->relationship(
                            'mentor',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => $query->whereIn('id', Training::query()->whereIn('id', $stalledById->keys())->pluck('mentor_id'))
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?: trim("{$record->first_name} {$record->last_name}") ?: "User #{$record->id}")
                        ->searchable()
                        ->preload(),
                    Tables\Filters\SelectFilter::make('county_id')
                        ->label('County')
                        ->relationship('county', 'name')
                        ->searchable()
                        ->preload(),
                ] : []),
            ])
            ->actions([
                Tables\Actions\Action::make('continue')
                    ->label('Continue')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Training $record): string => $stalledById[$record->id]['continueUrl']),
            ])
            ->emptyStateHeading('No pending mentorships right now.')
            ->emptyStateDescription('Nothing pending — every mentorship in scope has been started.')
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    protected function getViewData(): array
    {
        return [
            'showsEveryone' => $this->showsEveryone(),
        ];
    }
}
