<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Resources\DepartmentResource\RelationManagers\CadresRelationManager;
use App\Filament\Resources\DepartmentResource\RelationManagers\UsersRelationManager;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class DepartmentResource extends Resource {

    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Organizational Structure';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_department');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Department Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->label('Department Name')
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
                            ->label('Department Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('user_count')
                            ->label('Staff')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('training_count')
                            ->label('Trainings')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('participant_count')
                            ->label('Participants')
                            ->badge()
                            ->color('warning'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('name')
                        ->filters([
                            Tables\Filters\Filter::make('has_users')
                            ->label('Has Staff')
                            ->query(fn($query) => $query->withUsers()),
                            Tables\Filters\Filter::make('has_trainings')
                            ->label('Has Trainings')
                            ->query(fn($query) => $query->withTrainings()),
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
                            Infolists\Components\Section::make('Department Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                ->label('Department Name')
                                ->size('lg')
                                ->weight('bold'),
                            ])
                            ->columns(1),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('user_count')
                                ->label('Total Staff')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('training_count')
                                ->label('Trainings Conducted')
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('participant_count')
                                ->label('Training Participants')
                                ->badge()
                                ->color('warning'),
                            ])
                            ->columns(3),
                            Infolists\Components\Section::make('Metadata')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                            ])
                            ->columns(2)
                            ->collapsible(),
        ]);
    }

    public static function getRelations(): array {
        return [
            // Only Users relation manager - Cadres tab removed
            \App\Filament\Resources\Shared\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name'];
    }
}
