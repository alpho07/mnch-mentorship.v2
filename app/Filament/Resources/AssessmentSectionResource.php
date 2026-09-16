<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentSectionResource\Pages;
use App\Filament\Resources\AssessmentSectionResource\RelationManagers;
use App\Models\AssessmentSection;
use App\Models\AssessmentQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class AssessmentSectionResource extends Resource {

    protected static ?string $model = AssessmentSection::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Assessment Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Sections & Questions';
    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->can('view_any_assessment::section');}

    public static function canAccess(): bool {
        return auth()->check() && auth()->user()->can('view_any_assessment::section');}

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Section Identity')
                            ->description('Basic identification and display configuration')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('name')
                                    ->label('Section Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Infrastructure, Skills Lab')
                                    ->columnSpan(2),
                                    Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100)
                                    ->placeholder('e.g., infrastructure')
                                    ->helperText('Lowercase, no spaces. Used by DynamicFormBuilder.')
                                    ->alphaDash(),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('icon')
                                    ->label('Heroicon Name')
                                    ->placeholder('heroicon-o-building-office')
                                    ->helperText('Used in dashboard progress view'),
                                    Forms\Components\TextInput::make('order')
                                    ->label('Display Order')
                                    ->numeric()
                                    ->default(fn() => (AssessmentSection::max('order') ?? 0) + 10)
                                    ->required()
                                    ->helperText('Lower number = shown first'),
                                ]),
                                Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->rows(2)
                                ->placeholder('Briefly describe what this section covers')
                                ->columnSpanFull(),
                            ]),
                            Forms\Components\Section::make('Section Configuration')
                            ->description('Type and scoring settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Select::make('section_type')
                                    ->label('Section Type')
                                    ->options([
                                        'dynamic_questions' => 'Dynamic Questions',
                                        'structured_data' => 'Structured Data',
                                        'commodity_matrix' => 'Commodity Matrix',
                                    ])
                                    ->default('dynamic_questions')
                                    ->required()
                                    ->helperText('Determines how questions are rendered'),
                                    Forms\Components\Toggle::make('is_scored')
                                    ->label('Include in Scoring')
                                    ->default(true)
                                    ->helperText('Scored sections contribute to overall %'),
                                    Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                ]),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->columns([
                            Tables\Columns\TextColumn::make('order')
                            ->label('#')
                            ->sortable()
                            ->alignCenter()
                            ->width(50),
                            Tables\Columns\TextColumn::make('name')
                            ->label('Section')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold')
                            ->description(fn(AssessmentSection $record): string => $record->description ?? ''),
                            Tables\Columns\TextColumn::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('gray')
                            ->searchable(),
                            Tables\Columns\BadgeColumn::make('section_type')
                            ->label('Type')
                            ->colors([
                                'primary' => 'dynamic_questions',
                                'warning' => 'structured_data',
                                'info' => 'commodity_matrix',
                            ])
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'dynamic_questions' => 'Dynamic',
                                        'structured_data' => 'Structured',
                                        'commodity_matrix' => 'Commodity',
                                        default => $state,
                                    }),
                            Tables\Columns\TextColumn::make('questions_count')
                            ->label('Questions')
                            ->counts('questions')
                            ->badge()
                            ->color('success')
                            ->alignCenter(),
                            Tables\Columns\TextColumn::make('active_questions_count')
                            ->label('Active Q')
                            ->counts(['questions' => fn($q) => $q->where('is_active', true)])
                            ->badge()
                            ->color('info')
                            ->alignCenter(),
                            Tables\Columns\IconColumn::make('is_scored')
                            ->label('Scored')
                            ->boolean()
                            ->alignCenter(),
                            Tables\Columns\IconColumn::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->alignCenter(),
                        ])
                        ->defaultSort('order')
                        ->reorderable('order')
                        ->filters([
                            Tables\Filters\SelectFilter::make('section_type')
                            ->options([
                                'dynamic_questions' => 'Dynamic Questions',
                                'structured_data' => 'Structured Data',
                                'commodity_matrix' => 'Commodity Matrix',
                            ]),
                            Tables\Filters\TernaryFilter::make('is_active')
                            ->label('Active')
                            ->default(true),
                            Tables\Filters\TernaryFilter::make('is_scored')
                            ->label('Scored'),
                        ])
                        ->actions([
                            Tables\Actions\Action::make('manage_questions')
                            ->label('Questions')
                            ->icon('heroicon-o-list-bullet')
                            ->color('primary')
                            ->url(fn(AssessmentSection $record): string => static::getUrl('edit', ['record' => $record])),
                            Tables\Actions\EditAction::make()
                            ->icon('heroicon-o-pencil'),
                            Tables\Actions\Action::make('duplicate_section')
                            ->label('Duplicate')
                            ->icon('heroicon-o-document-duplicate')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->modalHeading('Duplicate Section')
                            ->modalDescription('This will create a copy of this section with all its questions.')
                            ->action(function (AssessmentSection $record) {
                                $newSection = $record->replicate();
                                $newSection->name = $record->name . ' (Copy)';
                                $newSection->code = $record->code . '_copy_' . time();
                                $newSection->order = ($record->order ?? 0) + 5;
                                $newSection->save();

                                // Duplicate questions
                                foreach ($record->questions()->orderBy('order')->get() as $q) {
                                    $newQ = $q->replicate();
                                    $newQ->assessment_section_id = $newSection->id;
                                    $newQ->question_code = $q->question_code . '_copy_' . time();
                                    $newQ->save();
                                }

                                \Filament\Notifications\Notification::make()
                                        ->title('Section duplicated')
                                        ->body("Created '{$newSection->name}' with " . $record->questions()->count() . ' questions.')
                                        ->success()
                                        ->send();
                            }),
                            Tables\Actions\DeleteAction::make()
                            ->before(function (AssessmentSection $record) {
                                if ($record->questions()->count() > 0) {
                                    \Filament\Notifications\Notification::make()
                                            ->title('Cannot delete')
                                            ->body('This section has questions. Reassign or delete them first.')
                                            ->danger()
                                            ->send();
                                    return false;
                                }
                            }),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check-circle')
                                ->action(fn($records) => $records->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate Selected')
                                ->icon('heroicon-o-x-circle')
                                ->color('warning')
                                ->action(fn($records) => $records->each->update(['is_active' => false]))
                                ->deselectRecordsAfterCompletion(),
                            ]),
                        ])
                        ->emptyStateHeading('No Assessment Sections')
                        ->emptyStateDescription('Create your first section to start building assessment forms.')
                        ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INFOLIST
    // ─────────────────────────────────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist->schema([
                            Infolists\Components\Section::make('Section Details')
                            ->schema([
                                Infolists\Components\Grid::make(3)->schema([
                                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    Infolists\Components\TextEntry::make('code')->badge()->color('gray'),
                                    Infolists\Components\TextEntry::make('section_type')->badge()->color('primary'),
                                ]),
                                Infolists\Components\TextEntry::make('description')->prose(),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RELATIONS & PAGES
    // ─────────────────────────────────────────────────────────────────────────

    public static function getRelations(): array {
        return [
            RelationManagers\QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListAssessmentSections::route('/'),
            'create' => Pages\CreateAssessmentSection::route('/create'),
            'edit' => Pages\EditAssessmentSection::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = AssessmentSection::where('is_active', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'primary';
    }
}
