<?php

namespace App\Livewire;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Training;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The undo surface for StalledMentorships::deactivateMentorship() /
 * deleteMentorship() — cancelled (still present) or soft-deleted facility
 * mentorships from the last 90 days, each reversible. A separate component
 * (not a second table() on StalledMentorships) because Filament pages only
 * support one table per HasTable component; extends TableWidget rather than
 * a plain Livewire\Component so the HasActions/HasForms/HasTable contracts
 * (needed for the confirm-modal row actions) come for free, then embedded
 * directly via @livewire() rather than a page's widget slots.
 */
class RecentlyActionedMentorshipsTable extends TableWidget
{
    private const RETENTION_DAYS = 90;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        return $table
            ->query(
                Training::withTrashed()
                    ->where('type', 'facility_mentorship')
                    ->where(function (Builder $query) use ($cutoff) {
                        $query->where(function (Builder $q) use ($cutoff) {
                            $q->whereNull('deleted_at')
                                ->where('status', 'cancelled')
                                ->where('updated_at', '>=', $cutoff);
                        })->orWhere(function (Builder $q) use ($cutoff) {
                            $q->whereNotNull('deleted_at')
                                ->where('deleted_at', '>=', $cutoff);
                        });
                    })
                    ->with('mentor')
            )
            ->heading('Recently Actioned')
            ->description('Deactivated or deleted in the last '.self::RETENTION_DAYS.' days — reverse here.')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Mentorship')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (Training $record): string => MentorshipTrainingResource::getUrl('edit', ['record' => $record->id])),
                Tables\Columns\TextColumn::make('mentor.name')
                    ->label('Mentor')
                    ->searchable()
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->state(fn (Training $record): string => $record->trashed() ? 'deleted' : 'inactive')
                    ->formatStateUsing(fn (string $state): string => $state === 'deleted' ? 'Deleted' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'deleted' ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('When')
                    ->state(fn (Training $record) => ($record->deleted_at ?? $record->updated_at)?->diffForHumans()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->options(['deleted' => 'Deleted', 'inactive' => 'Inactive'])
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $data['value'] === 'deleted'
                            ? $query->whereNotNull('deleted_at')
                            : $query->whereNull('deleted_at')->where('status', 'cancelled');
                    }),
                Tables\Filters\SelectFilter::make('mentor_id')
                    ->label('Mentor')
                    ->relationship('mentor', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?: trim("{$record->first_name} {$record->last_name}") ?: "User #{$record->id}")
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Training $record): bool => $record->trashed())
                    ->action(fn (Training $record) => $this->restoreMentorship($record->id)),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Training $record): bool => ! $record->trashed())
                    ->action(fn (Training $record) => $this->reactivateMentorship($record->id)),
            ])
            ->emptyStateHeading('Nothing deactivated or deleted in the last '.self::RETENTION_DAYS.' days.');
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
}
