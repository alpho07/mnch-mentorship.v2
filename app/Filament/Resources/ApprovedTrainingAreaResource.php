<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovedTrainingAreaResource\Pages;
use App\Models\ApprovedTrainingArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;

class ApprovedTrainingAreaResource extends Resource {

    protected static ?string $model = ApprovedTrainingArea::class;
    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?string $navigationLabel = 'Training Areas';
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?int $navigationSort = 0; // Display before MOH trainings
    protected static ?string $slug = 'approved-training-areas';
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_approved::training::area');}

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Training Area Details')
                            ->description('Define an approved area for training programs')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Area Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., HIV/AIDS Care and Treatment')
                                ->columnSpanFull(),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(3)
                                ->placeholder('Describe the focus and scope of this training area')
                                ->columnSpanFull(),
                                Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Lower numbers appear first in lists'),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active areas can be selected for new trainings'),
                                ]),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->description(fn($record): string => $record->description ? \Illuminate\Support\Str::limit($record->description, 60) : ''),
                            BadgeColumn::make('is_active')
                            ->label('Status')
                            ->colors([
                                'success' => true,
                                'danger' => false,
                            ])
                            ->formatStateUsing(fn(bool $state): string => $state ? 'Active' : 'Inactive')
                            ->icons([
                                'heroicon-o-check-circle' => true,
                                'heroicon-o-x-circle' => false,
                            ]),
                            TextColumn::make('training_count')
                            ->label('Total Trainings')
                            ->getStateUsing(fn($record) => $record->trainings()->count())
                            ->badge()
                            ->color('info')
                            ->sortable(),
                            TextColumn::make('active_training_count')
                            ->label('Active Trainings')
                            ->getStateUsing(fn($record) => $record->trainings()->count())
                            ->badge()
                            ->color('success')
                            ->sortable(),
                            TextColumn::make('sort_order')
                            ->label('Order')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                            TextColumn::make('created_at')
                            ->label('Created')
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Filter::make('active')
                            ->label('Active Only')
                            ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                            ->toggle(),
                            Filter::make('with_trainings')
                            ->label('With Trainings')
                            ->query(fn(Builder $query): Builder => $query->has('trainings'))
                            ->toggle(),
                        ])
                        ->actions([
                            ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->color('info'),
                                Tables\Actions\EditAction::make()
                                ->color('warning'),
                                Tables\Actions\Action::make('toggle_status')
                                ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                                ->icon(fn($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                                ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                                ->action(function ($record) {
                                    $record->update(['is_active' => !$record->is_active]);
                                })
                                ->requiresConfirmation(),
                                Tables\Actions\DeleteAction::make()
                                ->before(function ($record, $action) {
                                    if (!$record->canBeDeleted()) {
                                        \Filament\Notifications\Notification::make()
                                                ->title('Cannot Delete')
                                                ->body('This training area has associated trainings and cannot be deleted.')
                                                ->danger()
                                                ->send();
                                        $action->cancel();
                                    }
                                }),
                            ])
                            ->label('Actions')
                            ->icon('heroicon-m-ellipsis-horizontal')
                            ->size('sm')
                            ->color('gray')
                            ->button()
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->action(fn($records) => $records->each->activate())
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate Selected')
                                ->icon('heroicon-o-x-circle')
                                ->color('danger')
                                ->action(fn($records) => $records->each->deactivate())
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\DeleteBulkAction::make()
                                ->before(function ($records, $action) {
                                    $hasTrainings = $records->filter(fn($record) => !$record->canBeDeleted());
                                    if ($hasTrainings->count() > 0) {
                                        \Filament\Notifications\Notification::make()
                                                ->title('Cannot Delete Some Areas')
                                                ->body("{$hasTrainings->count()} area(s) have associated trainings and cannot be deleted.")
                                                ->warning()
                                                ->send();
                                        $action->cancel();
                                    }
                                }),
                            ]),
                        ])
                        ->defaultSort('sort_order', 'asc')
                        ->emptyStateHeading('No Training Areas Found')
                        ->emptyStateDescription('Create your first approved training area to get started.')
                        ->emptyStateIcon('heroicon-o-bookmark-square')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make()
                            ->label('Create Training Area')
                            ->icon('heroicon-o-plus'),
        ]);
    }

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListApprovedTrainingAreas::route('/'),
            'create' => Pages\CreateApprovedTrainingArea::route('/create'),
            'view' => Pages\ViewApprovedTrainingArea::route('/{record}'),
            'edit' => Pages\EditApprovedTrainingArea::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = static::getModel()::active()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'success';
    }
}
