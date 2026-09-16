<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentCategoryResource\Pages;
use App\Models\AssessmentCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Grid;

class AssessmentCategoryResource extends Resource
{
    protected static ?string $model = AssessmentCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Assessment Settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

 

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Category Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g., Pre-Test, Practical Skills'),

                                Select::make('assessment_method')
                                    ->label('Assessment Method')
                                    ->options([
                                        'Written Test' => 'Written Test',
                                        'Multiple Choice Assessment' => 'Multiple Choice Assessment',
                                        'Multiple Choice Question' => 'Multiple Choice Question', 
                                        'Practical Demonstration' => 'Practical Demonstration',
                                        'Oral Examination' => 'Oral Examination',
                                        'Case Study' => 'Case Study',
                                        'Portfolio Review' => 'Portfolio Review',
                                        'Peer Assessment' => 'Peer Assessment',
                                        'Observation' => 'Clinical Observation',
                                        'Simulation' => 'Simulation Exercise',
                                    ])
                                    ->required()
                                    ->default('Practical Demonstration'),
                            ]),

                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Describe what this assessment category evaluates...')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Assessment Settings')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('weight_percentage')
                                    ->label('Weight (%)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(25)
                                    ->suffix('%')
                                    ->required()
                                    ->helperText('How important is this category in overall assessment?'),

                                TextInput::make('order_sequence')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->helperText('Order in which this appears in assessments'),

                                Toggle::make('is_required')
                                    ->label('Required for Pass')
                                    ->default(true)
                                    ->helperText('Must pass this category to pass overall'),
                            ]),

                        Forms\Components\Placeholder::make('usage_note')
                            ->content(function () {
                                return new \Illuminate\Support\HtmlString('
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <h4 class="font-medium text-blue-900 mb-1">Assessment Categories Usage</h4>
                                        <ul class="text-sm text-blue-800 space-y-1">
                                            <li>• Categories can be reused across multiple mentorship programs</li>
                                            <li>• Required categories must be passed for overall program completion</li>
                                            <li>• Weight determines influence on final weighted score calculation</li>
                                            <li>• Assessment method guides mentors on evaluation approach</li>
                                        </ul>
                                    </div>
                                ');
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('assessment_method')
                    ->label('Method')
                    ->badge()
                    ->color('info'),

                TextColumn::make('weight_percentage')
                    ->label('Weight')
                    ->suffix('%')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                BadgeColumn::make('is_required')
                    ->label('Required')
                    //->trueColor('success')
                    //->falseColor('secondary')
                    //->trueIcon('heroicon-o-check-circle')
                    //->falseIcon('heroicon-o-x-circle')
                    ->formatStateUsing(fn ($state) => $state ? 'Required' : 'Optional'),

                TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('usage_count')
                    ->label('Used In')
                    ->getStateUsing(fn ($record) => $record->assessmentResults()->count() . ' assessments')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_required')
                    ->label('Required Categories')
                    ->placeholder('All categories')
                    ->trueLabel('Required only')
                    ->falseLabel('Optional only'),

                Tables\Filters\SelectFilter::make('assessment_method')
                    ->multiple()
                    ->options([
                        'Written Test' => 'Written Test',
                        'Practical Demonstration' => 'Practical Demonstration',
                        'Oral Examination' => 'Oral Examination',
                        'Case Study' => 'Case Study',
                        'Portfolio Review' => 'Portfolio Review',
                        'Peer Assessment' => 'Peer Assessment',
                        'Observation' => 'Clinical Observation',
                        'Simulation' => 'Simulation Exercise',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->before(function (AssessmentCategory $record) {
                        // Check if category is being used
                        $usageCount = $record->assessmentResults()->count();
                        if ($usageCount > 0) {
                            throw new \Exception("Cannot delete category that has {$usageCount} assessment results. Remove assessments first.");
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('order_sequence')
            ->reorderable('order_sequence')
            ->emptyStateHeading('No Assessment Categories')
            ->emptyStateDescription('Create your first assessment category to start evaluating mentees.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentCategories::route('/'),
            'create' => Pages\CreateAssessmentCategory::route('/create'),
            'edit' => Pages\EditAssessmentCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}