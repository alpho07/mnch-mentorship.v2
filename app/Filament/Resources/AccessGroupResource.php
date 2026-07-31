<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessGroupResource\Pages;
use App\Models\AccessGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccessGroupResource extends Resource {

    protected static ?string $model = AccessGroup::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_access::group');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_access::group');}

    public static function canCreate(): bool {
        return static::canAccess();
    }

    public static function canEdit($record): bool {
        return static::canAccess();
    }

    public static function canDelete($record): bool {
        return static::canAccess();
    }

    public static function form(Form $form): Form {
        return $form
                        ->schema([
                            Forms\Components\Section::make('Group Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_active')
                                ->default(true)
                                ->label('Active'),
                            ])
                            ->columns(2),
                            Forms\Components\Section::make('Members')
                            ->schema([
                                Forms\Components\Select::make('users')
                                ->relationship('users', 'first_name')
                                ->multiple()
                                ->searchable()
                                ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                                ->columnSpanFull(),
                            ]),
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->sortable()
                            ->description(fn(AccessGroup $record): string =>
                                    $record->description ?: ''
                            ),
                            Tables\Columns\TextColumn::make('user_count')
                            ->label('Members')
                            ->numeric()
                            ->sortable()
                            ->counts('users'),
                            Tables\Columns\TextColumn::make('resource_count')
                            ->label('Resources')
                            ->numeric()
                            ->sortable()
                            ->counts('resources'),
                            Tables\Columns\IconColumn::make('is_active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->dateTime()
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])
                        ->filters([
                            Tables\Filters\Filter::make('is_active')
                            ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                            ->label('Active Only'),
                            Tables\Filters\Filter::make('has_members')
                            ->query(fn(Builder $query): Builder => $query->has('users'))
                            ->label('Has Members'),
                            Tables\Filters\Filter::make('has_resources')
                            ->query(fn(Builder $query): Builder => $query->has('resources'))
                            ->label('Has Resources'),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_active' => true])
                                        ))
                                ->color('success'),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-o-x-circle')
                                ->action(fn($records) => $records->each(fn($record) =>
                                                $record->update(['is_active' => false])
                                        ))
                                ->color('danger'),
                            ]),
                        ])
                        ->defaultSort('name')
                        ->emptyStateHeading('No Access Groups Yet')
                        ->emptyStateDescription('Access groups control which restricted resources a facility or role can see.')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }

    public static function getRelations(): array {
        return [
                //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAccessGroups::route('/'),
            'create' => Pages\CreateAccessGroup::route('/create'),
            //'view' => Pages\ViewAccessGroup::route('/{record}'),
            'edit' => Pages\EditAccessGroup::route('/{record}/edit'),
        ];
    }
}
