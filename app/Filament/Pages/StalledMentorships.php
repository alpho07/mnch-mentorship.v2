<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use App\Services\MentorshipStallReminderService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection; 

/**
 * Admin center for the mentorships stuck in "draft" — never started because
 * no class exists, no mentee was enrolled, or mentees are enrolled but no
 * curriculum modules were assigned. Lets an admin trigger the same reminder
 * the scheduled mentorships:send-stall-reminders command sends, either for
 * one mentorship or for everything currently due, without waiting for the
 * next scheduled run.
 *
 * The table's underlying query is Training::query() (real columns: title,
 * mentor, county, created_at — so Filament's search/sort/pagination work
 * natively), but bucket/days-stalled/due/last-reminded are computed values
 * from MentorshipStallReminderService::stalled() — the single source of
 * truth also used by the scheduled command and the mentor-facing Pending
 * Mentorships page. Those computed values are memoized per-request into
 * $stalledById and looked up in column/filter closures rather than
 * recomputed, so classification logic never gets duplicated here.
 */
class StalledMentorships extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Stalled Mentorships';

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stalled-mentorships';

    /** @var Collection<int, array{training: Training, class: ?\App\Models\MentorshipClass, bucket: string, last_activity_at: \Illuminate\Support\Carbon, days_stalled: int, last_reminded_at: ?\Illuminate\Support\Carbon, due: bool}>|null */
    private ?Collection $stalledById = null;

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

    /**
     * Memoized per request — the table() method's columns/filters and the
     * header "send all due" count all read from this without re-querying.
     */
    private function stalledById(): Collection
    {
        return $this->stalledById ??= app(MentorshipStallReminderService::class)
            ->stalled()
            ->keyBy(fn (array $row) => $row['training']->id);
    }

    public function table(Table $table): Table
    {
        $stalledById = $this->stalledById();
        $dueCount = $stalledById->filter(fn (array $row) => $row['due'])->count();

        return $table
            ->query(
                Training::query()
                    ->whereIn('id', $stalledById->keys())
                    ->with(['mentor', 'county'])
            )
            ->heading('Stalled Mentorships')
            ->description("{$dueCount} ".str($dueCount === 1 ? 'mentorship' : 'mentorships')." due for a reminder right now.")
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Mentorship')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (Training $record): string => MentorshipTrainingResource::getUrl('edit', ['record' => $record->id])),
                Tables\Columns\TextColumn::make('mentor.name')
                    ->label('Mentor')
                    ->searchable()
                    ->placeholder('Unassigned')
                    ->description(fn (Training $record): ?string => $record->mentor
                        ? trim(collect([$record->mentor->email, $record->mentor->phone])->filter()->join(' · '))
                        : null),
                Tables\Columns\TextColumn::make('bucket')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Training $record): string => $stalledById[$record->id]['bucket'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'no_class' => 'No class created',
                        'no_mentee' => 'No mentees enrolled',
                        'no_modules' => 'No modules assigned',
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
                    ->state(fn (Training $record) => $stalledById[$record->id]['last_reminded_at']?->diffForHumans() ?? 'Never'),
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
                Tables\Filters\SelectFilter::make('mentor_id')
                    ->label('Mentor')
                    // Not a plain ->relationship('mentor','name') — some
                    // users have a null `name` column (only first/last name
                    // populated), which crashes Filament's option-label
                    // building. Fall back explicitly instead.
                    ->relationship(
                        'mentor',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereIn('id', Training::query()->whereIn('id', $this->stalledById()->keys())->pluck('mentor_id'))
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?: trim("{$record->first_name} {$record->last_name}") ?: "User #{$record->id}")
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('county_id')
                    ->label('County')
                    ->relationship('county', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('sendReminder')
                    ->label(fn (Training $record): string => $stalledById[$record->id]['due'] ? 'Send reminder' : 'Send anyway')
                    ->icon('heroicon-o-paper-airplane')
                    ->color(fn (Training $record): string => $stalledById[$record->id]['due'] ? 'primary' : 'gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Training $record): string => "Send a stall reminder to {$record->mentor?->name}?")
                    ->action(fn (Training $record) => $this->sendReminder($record->id, $stalledById[$record->id]['bucket'])),
                Tables\Actions\Action::make('deactivate')
                    ->label('Make inactive')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Training $record): string => "Mark \"{$record->title}\" inactive? This can be reversed from Recently Actioned below.")
                    ->action(fn (Training $record) => $this->deactivateMentorship($record->id)),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Training $record): string => "Delete \"{$record->title}\"? This can be restored later from Recently Actioned below.")
                    ->action(fn (Training $record) => $this->deleteMentorship($record->id)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sendAllDue')
                    ->label(fn (): string => "Send all due ({$dueCount})")
                    ->icon('heroicon-o-paper-airplane')
                    ->visible($dueCount > 0)
                    ->requiresConfirmation()
                    ->modalDescription('Send a reminder to every mentor with a due mentorship?')
                    ->action('sendAllDue'),
            ])
            ->emptyStateHeading('No stalled mentorships right now.')
            ->defaultSort('created_at', 'desc');
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
     * cancelled. Reachable again via RecentlyActionedMentorshipsTable's
     * reactivateMentorship().
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

    /**
     * Soft delete — Training uses SoftDeletes, so this is reversible from
     * the Recently Actioned table below (a separate Livewire component —
     * see RecentlyActionedMentorshipsTable). Classes/participants aren't
     * touched.
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
}
