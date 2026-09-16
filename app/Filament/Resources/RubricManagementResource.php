<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RubricManagementResource\Pages;
use App\Models\ModuleRubric;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RubricManagementResource extends Resource
{
    protected static ?string $model = ModuleRubric::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Rubric Definitions';

    protected static ?string $navigationGroup = 'Curriculum';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Rubric';

    protected static ?string $pluralModelLabel = 'Rubrics';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_rubric::management');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rubric Details')
                ->schema([
                    Forms\Components\Select::make('program_module_id')
                        ->label('Program Module')
                        ->relationship('programModule', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('title')
                        ->label('Rubric Title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('total_marks')
                            ->label('Total Marks')
                            ->integer()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('pass_marks')
                            ->label('Pass Marks')
                            ->integer()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('pass_percentage')
                            ->label('Pass % (auto-display)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Calculated'),
                    ]),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\TextInput::make('order_sequence')
                        ->label('Order')
                        ->integer()
                        ->default(1),
                ])
                ->columns(2),

            Forms\Components\Section::make('Case Scenario')
                ->schema([
                    Forms\Components\Textarea::make('case_scenario')
                        ->label('Case Scenario Text')
                        ->rows(8)
                        ->columnSpanFull()
                        ->helperText('The clinical scenario presented during the practical assessment.'),
                ]),

            Forms\Components\Section::make('Equipment & Supplies')
                ->schema([
                    Forms\Components\TagsInput::make('equipment_supplies')
                        ->label('Equipment / Supplies Required')
                        ->placeholder('Add item and press Enter')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Debrief Questions')
                ->schema([
                    Forms\Components\TagsInput::make('debrief_questions')
                        ->label('Debrief Questions (mentor reference)')
                        ->placeholder('Add question and press Enter')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Checklist Items')
                ->description('Define the observable steps / criteria. Order them as they should appear on the assessment sheet.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship('items')
                        ->schema([
                            Forms\Components\TextInput::make('order_sequence')
                                ->label('#')
                                ->integer()
                                ->default(1)
                                ->required()
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('description')
                                ->label('Step / Criterion')
                                ->required()
                                ->rows(2)
                                ->columnSpan(4),

                            Forms\Components\Textarea::make('guidance')
                                ->label('Guidance Notes (mentor only)')
                                ->rows(2)
                                ->columnSpan(3),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->columnSpan(1),
                        ])
                        ->columns(9)
                        ->reorderable('order_sequence')
                        ->cloneable()
                        ->addActionLabel('Add Item')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('programModule.name')
                    ->label('Module / Track')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Rubric Title')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pass_marks')
                    ->label('Pass Mark')
                    ->formatStateUsing(fn ($record) => $record->pass_marks . ' / ' . $record->total_marks . ' (' . $record->pass_percentage . '%)'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('programModule.name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRubrics::route('/'),
            'create' => Pages\CreateRubric::route('/create'),
            'edit' => Pages\EditRubric::route('/{record}/edit'),
        ];
    }
}
