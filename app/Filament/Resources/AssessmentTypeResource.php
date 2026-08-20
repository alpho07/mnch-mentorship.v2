<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentTypeResource\Pages;
use App\Filament\Resources\AssessmentTypeResource\RelationManagers;
use App\Models\AssessmentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssessmentTypeResource extends Resource
{
    protected static ?string $model = AssessmentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Assessment Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Assessment Templates';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment::type');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment::type');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Assessment Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Skillslab Status Assessment (2025 Aug)')
                                    ->helperText('This is what shows in the picker when starting a new assessment — include the period in the title if it matters, e.g. "(2025 Aug)".')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->placeholder('e.g., SKILLSLAB_STATUS_2025_AUG')
                                    ->alphaDash()
                                    ->helperText('Unique identifier (alphanumeric, underscores, hyphens only)'),
                                Forms\Components\TextInput::make('version')
                                    ->label('Version')
                                    ->default('1.0')
                                    ->required()
                                    ->placeholder('1.0')
                                    ->helperText('Version number for tracking changes'),
                                Forms\Components\DatePicker::make('period_start')
                                    ->label('Period Start')
                                    ->native(false)
                                    ->helperText('Optional — the period this assessment covers'),
                                Forms\Components\DatePicker::make('period_end')
                                    ->label('Period End')
                                    ->native(false)
                                    ->afterOrEqual('period_start'),
                                Forms\Components\TextInput::make('validity_period_days')
                                    ->label('Validity Period (Days)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('365')
                                    ->helperText('How long this assessment remains valid (optional)'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active assessment templates are selectable when creating a new assessment.'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(4)
                                    ->columnSpan(2)
                                    ->placeholder('Detailed description of this assessment template'),
                            ]),
                    ]),
                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Property')
                            ->helperText('Store any additional configuration data'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Assessment Title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (AssessmentType $record): ?string => $record->description),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn (AssessmentType $record): ?string => $record->period_start
                            ? $record->period_start->format('M Y').($record->period_end && ! $record->period_end->isSameMonth($record->period_start) ? ' – '.$record->period_end->format('M Y') : '')
                            : null)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('assessments_count')
                    ->label('Assessments')
                    ->counts('assessments')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('validity_period_days')
                    ->label('Validity (Days)')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('No limit'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->default(true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_questions_csv')
                    ->label('Download Questions (CSV)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function (AssessmentType $record) {
                        $csv = app(\App\Services\AssessmentExportService::class)->exportTemplateQuestionsToCSV($record);

                        $filename = sprintf('mnch-template-questions-%s.csv', \Illuminate\Support\Str::slug($record->name));

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, $filename, [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentTypes::route('/'),
            'create' => Pages\CreateAssessmentType::route('/create'),
            'view' => Pages\ViewAssessmentType::route('/{record}'),
            'edit' => Pages\EditAssessmentType::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }
}
