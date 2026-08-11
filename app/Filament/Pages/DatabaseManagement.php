<?php

namespace App\Filament\Pages;

use App\Jobs\RestoreDatabaseJob;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\Setting;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class DatabaseManagement extends Page implements HasActions, HasForms, Tables\Contracts\HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Database Management';

    protected static ?string $navigationGroup = 'App Configuration';

    protected static string $view = 'filament.pages.database-management';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DatabaseBackup::query()->latest())
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'scheduled' => 'gray',
                        'pre_restore_safety' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'running', 'pending' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (DatabaseBackup $record): ?string => $record->status === 'failed' ? $record->error_message : null),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state ? Number::fileSize($state) : '—'),
                Tables\Columns\TextColumn::make('triggeredBy.name')
                    ->label('Triggered By')
                    ->placeholder('Scheduled'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (DatabaseBackup $record): bool => $record->isDownloadable())
                    ->action(fn (DatabaseBackup $record) => Storage::disk($record->disk)->download($record->filename)),
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (DatabaseBackup $record): bool => $record->status === 'completed')
                    ->form([
                        Forms\Components\Placeholder::make('warning')
                            ->hiddenLabel()
                            ->content('This will overwrite the live database with this backup. A safety backup of the current database is taken automatically first, but this is still a destructive action.'),
                        Forms\Components\TextInput::make('confirm_filename')
                            ->label('Type the filename to confirm')
                            ->required(),
                    ])
                    ->action(function (DatabaseBackup $record, array $data): void {
                        if ($data['confirm_filename'] !== $record->filename) {
                            Notification::make()->danger()->title('Filename did not match — restore cancelled.')->send();

                            return;
                        }

                        $restore = app(\App\Services\DatabaseBackupService::class)->createPendingRestore($record, auth()->id());
                        RestoreDatabaseJob::dispatch($restore->id);

                        Notification::make()->warning()->title('Restore started')->body('Taking a safety backup, then restoring. Watch this list for live progress.')->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->action(function (DatabaseBackup $record): void {
                        Storage::disk($record->disk)->delete($record->filename);
                        $record->delete();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('backup_now')
                    ->label('Backup Now')
                    ->icon('heroicon-o-server-stack')
                    ->color('primary')
                    ->action(function (): void {
                        $backup = app(\App\Services\DatabaseBackupService::class)->createPendingBackup(auth()->id(), 'manual');
                        RunDatabaseBackupJob::dispatch($backup->id);

                        Notification::make()->success()->title('Backup started')->body('It now appears in the list below — watch it move from pending to running to completed.')->send();
                    }),
                Tables\Actions\Action::make('schedule_settings')
                    ->label('Schedule Settings')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->form([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable scheduled backups')
                            ->default(fn () => Setting::getBool(Setting::BACKUP_SCHEDULE_ENABLED, false)),
                        Forms\Components\Select::make('frequency')
                            ->label('Frequency')
                            ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                            ->default(fn () => Setting::get(Setting::BACKUP_SCHEDULE_FREQUENCY, 'daily'))
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('day_of_week')
                            ->label('Day of week')
                            ->options([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'])
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, 0))
                            ->visible(fn (Forms\Get $get) => $get('frequency') === 'weekly')
                            ->required(fn (Forms\Get $get) => $get('frequency') === 'weekly'),
                        Forms\Components\Select::make('day_of_month')
                            ->label('Day of month')
                            ->options(array_combine(range(1, 28), range(1, 28)))
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_SCHEDULE_DAY_OF_MONTH, 1))
                            ->visible(fn (Forms\Get $get) => $get('frequency') === 'monthly')
                            ->required(fn (Forms\Get $get) => $get('frequency') === 'monthly'),
                        Forms\Components\TimePicker::make('time')
                            ->label('Time of day')
                            ->seconds(false)
                            ->default(fn () => Setting::get(Setting::BACKUP_SCHEDULE_TIME, '02:00'))
                            ->required(),
                        Forms\Components\TextInput::make('retention_count')
                            ->label('Keep the last N backups')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn () => (int) Setting::get(Setting::BACKUP_RETENTION_COUNT, 14))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        Setting::setBool(Setting::BACKUP_SCHEDULE_ENABLED, (bool) $data['enabled']);
                        Setting::set(Setting::BACKUP_SCHEDULE_FREQUENCY, $data['frequency']);
                        Setting::set(Setting::BACKUP_SCHEDULE_TIME, $data['time']);
                        Setting::set(Setting::BACKUP_SCHEDULE_DAY_OF_WEEK, (int) ($data['day_of_week'] ?? 0));
                        Setting::set(Setting::BACKUP_SCHEDULE_DAY_OF_MONTH, (int) ($data['day_of_month'] ?? 1));
                        Setting::set(Setting::BACKUP_RETENTION_COUNT, (int) $data['retention_count']);

                        Notification::make()->success()->title('Schedule settings saved')->send();
                    }),
            ]);
    }
}
