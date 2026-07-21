<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramModuleResource\Pages;
use App\Filament\Resources\ProgramModuleResource\RelationManagers;
use App\Models\Activity;
use App\Models\Program;
use App\Models\ProgramModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramModuleResource extends Resource
{
    protected static ?string $model = ProgramModule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Curriculum';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_program::module');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program Module Details')
                ->schema([
                    Forms\Components\Select::make('program_id')
                        ->label('Program')
                        ->options(Program::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('parent_id')
                        ->label('Parent Module')
                        ->relationship(
                            'parent',
                            'name',
                            fn (Builder $query, ?ProgramModule $record) => $query
                                ->when($record, fn (Builder $q) => $q->where('id', '!=', $record->id))
                                ->whereNull('parent_id')
                                ->orderBy('name')
                        )
                        ->placeholder('Top-level module')
                        ->searchable()
                        ->preload()
                        ->helperText('Leave empty for a module. Select a module to make this a track.'),

                    Forms\Components\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(4)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('order_sequence')
                        ->label('Order Sequence')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('Start Date')
                        ->native(false),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->native(false)
                        ->afterOrEqual('start_date'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Activities')
                ->schema([
                    Forms\Components\CheckboxList::make('activities')
                        ->label('Attach Activities')
                        ->relationship('activities', 'name')
                        ->options(Activity::where('is_active', true)->pluck('name', 'id'))
                        ->columns(3)
                        ->helperText('Select the activities that apply to this module or track.'),
                ]),

            Forms\Components\Section::make('Learning Objectives')
                ->description('Shown to mentees as "III. Learning Objectives" on the module page.')
                ->schema([
                    Forms\Components\Repeater::make('objectives')
                        ->label('Objectives')
                        ->hiddenLabel()
                        ->simple(
                            Forms\Components\TextInput::make('objective')
                                ->required()
                                ->maxLength(500)
                        )
                        ->addActionLabel('Add Objective')
                        ->reorderable()
                        ->defaultItems(0),
                ]),

            Forms\Components\Section::make('Module Workplan')
                ->description('Shown to mentees as "IV. Module Workplan" — e.g. Drill: 10–12 min, Debrief: 25–30 min.')
                ->schema([
                    Forms\Components\Repeater::make('content')
                        ->label('Workplan Items')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('Activity')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('duration')
                                ->label('Duration')
                                ->required()
                                ->maxLength(100),
                        ])
                        ->columns(2)
                        ->addActionLabel('Add Workplan Item')
                        ->reorderable()
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Module')
                    ->placeholder('Module (no parent)')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('is_track')
                    ->label('Track')
                    ->boolean()
                    ->getStateUsing(fn (ProgramModule $record): bool => $record->isTrack()),

                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('activities.name')
                    ->label('Activities')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_sequence', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('program_id')
                    ->label('Program')
                    ->options(Program::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('parent_id')
                    ->label('Type')
                    ->placeholder('All')
                    ->trueLabel('Tracks only')
                    ->falseLabel('Modules only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNull('parent_id'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChildrenRelationManager::class,
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\ContentsRelationManager::class,
            RelationManagers\QuizzesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramModules::route('/'),
            'create' => Pages\CreateProgramModule::route('/create'),
            'view' => Pages\ViewProgramModule::route('/{record}'),
            'edit' => Pages\EditProgramModule::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description', 'program.name', 'parent.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Program' => $record->program?->name,
            'Type' => $record->isTrack() ? 'Track' : 'Module',
        ];
    }
}
