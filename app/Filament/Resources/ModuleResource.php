<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Filament\Resources\ModuleResource\RelationManagers\TopicsRelationManager;
use App\Models\Module;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class ModuleResource extends Resource {

    protected static ?string $model = Module::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Curriculum';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_module');}

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Module Details')
                            ->schema([
                                Forms\Components\Select::make('program_id')
                                ->label('Program')
                                ->options(Program::pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),
                                Forms\Components\TextInput::make('name')
                                ->label('Module Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(4)
                                ->columnSpanFull(),
                            ])
        ]);
    }

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('program.name')
                            ->label('Program')
                            ->sortable()
                            ->searchable()
                            ->badge()
                            ->color('primary'),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Module Name')
                            ->sortable()
                            ->searchable()
                            ->weight('bold'),
                            Tables\Columns\TextColumn::make('description')
                            ->label('Description')
                            ->limit(50)
                            ->wrap(),
                            Tables\Columns\TextColumn::make('session_count')
                            ->label('Sessions')
                            ->badge()
                            ->color('info'),
                            Tables\Columns\TextColumn::make('training_count')
                            ->label('Trainings')
                            ->badge()
                            ->color('success'),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Created')
                            ->date()
                            ->sortable(),
                        ])
                        ->defaultSort('created_at', 'desc')
                        ->filters([
                            Tables\Filters\SelectFilter::make('program_id')
                            ->label('Program')
                            ->options(Program::pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                            Tables\Filters\Filter::make('has_sessions')
                            ->label('Has Sessions')
                            ->query(fn($query) => $query->withSessions()),
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
                            Infolists\Components\Section::make('Module Overview')
                            ->schema([
                                Infolists\Components\TextEntry::make('program.name')
                                ->label('Program')
                                ->badge()
                                ->color('primary'),
                                Infolists\Components\TextEntry::make('name')
                                ->label('Module Name')
                                ->size('lg')
                                ->weight('bold'),
                                Infolists\Components\TextEntry::make('description')
                                ->label('Description')
                                ->prose()
                                ->columnSpanFull(),
                            ])
                            ->columns(2),
                            Infolists\Components\Section::make('Statistics')
                            ->schema([
                                Infolists\Components\TextEntry::make('session_count')
                                ->label('Training Sessions')
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('training_count')
                                ->label('Trainings Using Module')
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('total_objectives')
                                ->label('Total Objectives')
                                ->badge()
                                ->color('warning'),
                                Infolists\Components\TextEntry::make('skill_objectives_count')
                                ->label('Skill Objectives')
                                ->badge()
                                ->color('primary'),
                            ])
                            ->columns(4),
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
                // TopicsRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'view' => Pages\ViewModule::route('/{record}'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'description', 'program.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array {
        return [
            'Program' => $record->program?->name,
        ];
    }
}
