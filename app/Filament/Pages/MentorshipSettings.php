<?php

namespace App\Filament\Pages;

use App\Models\Program;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A settings hub for mentorship-wide configuration, starting with program
 * activation. Deactivating a program here hides it from the mentorship
 * creation flow for most roles (Program::isSelectableBy()) — the
 * Program & Schedule picker still lists it, but disabled and labelled "Not
 * Active" rather than hidden outright. Full program editing (name,
 * description, per-role visibility overrides) stays under Curriculum →
 * Programs; this page is deliberately just the on/off switch.
 */
class MentorshipSettings extends Page implements HasActions, HasForms, Tables\Contracts\HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Mentorship Settings';

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.mentorship-settings';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('update_program');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('update_program');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Program::query())
            ->heading('Program Activation')
            ->description('Turn a program off to hide it from the mentorship creation flow for most roles. It still appears in the picker, greyed out and marked "Not Active", instead of disappearing outright.')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Program')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Program $record): ?string => $record->description
                        ? str($record->description)->limit(60)->toString()
                        : null),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (Program $record): string => $record->is_active ? 'Active — visible to all' : 'Deactivated'),

                Tables\Columns\TextColumn::make('visible_to_roles')
                    ->label('Still visible when off')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => \App\Filament\Resources\ProgramResource::roleOptions()[$state] ?? $state)
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (Program $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Program $record): string => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Program $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Program $record): string => $record->is_active
                        ? "Deactivate \"{$record->name}\"?"
                        : "Activate \"{$record->name}\"?")
                    ->modalDescription(fn (Program $record): string => $record->is_active
                        ? 'This program will show as "Not Active" and can\'t be picked when starting a new mentorship, for most roles.'
                        : 'This program becomes selectable again in the mentorship creation flow for everyone.')
                    ->action(function (Program $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Program activated' : 'Program deactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
