<?php

namespace App\Filament\Resources;

namespace App\Filament\Resources;

use App\Filament\Resources\CadreResource\Pages;
use App\Filament\Resources\CadreResource\RelationManagers\UsersRelationManager;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class CadreResource extends Resource {

    protected static ?string $model = Cadre::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Organizational Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_cadre');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Cadre Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Cadre Name')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->columnSpanFull(),
                            ])
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Cadre Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('user_count')
                            ->label('Users')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('training_participation_count')
                            ->label('Training Participants')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('name')
                        ->filters([
                            Tables\Filters\Filter::make('has_users')
                            ->label('Has Users')
                            ->query(fn($query) => $query->withUsers()),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                            Tables\Actions\DeleteAction::make()
                            ->requiresConfirmation(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist
                        ->schema([
                            Infolists\Components\Section::make('Cadre Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                ->label('Cadre Name')
                                ->size('lg')
                                ->weight('bold'),
                            ])
                            ->columns(1),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('user_count')
                                ->label('Total Users')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('training_participation_count')
                                ->label('Training Participants')
                                ->badge()
                                ->color('success'),
                            ])
                            ->columns(2),
        ]);
    }

    public static function getRelations(): array {
        return [
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListCadres::route('/'),
            'create' => Pages\CreateCadre::route('/create'),
            'view' => Pages\ViewCadre::route('/{record}'),
            'edit' => Pages\EditCadre::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name'];
    }
}
