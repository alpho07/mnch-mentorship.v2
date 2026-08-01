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

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip(fn (Program $record): string => $record->is_active ? 'Active — visible to all' : 'Deactivated')
                    ->afterStateUpdated(function (Program $record, bool $state): void {
                        Notification::make()
                            ->title($state ? 'Program activated' : 'Program deactivated')
                            ->success()
                            ->send();
                    }),

                Tables\Columns\TextColumn::make('visible_to_roles')
                    ->label('Still visible when off')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => \App\Filament\Resources\ProgramResource::roleOptions()[$state] ?? $state)
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
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
