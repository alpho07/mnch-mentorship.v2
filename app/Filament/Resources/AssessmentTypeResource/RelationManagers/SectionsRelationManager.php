<?php

namespace App\Filament\Resources\AssessmentTypeResource\RelationManagers;

use App\Models\AssessmentSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Assessment Sections';

    private const KIND_OPTIONS = [
        AssessmentSection::KIND_QUESTION_GROUP => 'Dynamic Questions (Q&A)',
        AssessmentSection::KIND_HUMAN_RESOURCES => 'Human Resources (Cadre Matrix)',
        AssessmentSection::KIND_COMMODITY_MATRIX => 'Health Products (Commodity Matrix)',
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Section Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., 1.0 GENERAL INFORMATION')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->maxLength(255)
                            ->placeholder('e.g., SECTION_1')
                            ->alphaDash()
                            ->helperText('Unique identifier for this section'),

                        Forms\Components\Select::make('section_type')
                            ->label('Kind')
                            ->options(self::KIND_OPTIONS)
                            ->default(AssessmentSection::KIND_QUESTION_GROUP)
                            ->required()
                            ->live()
                            ->helperText('Human Resources and Health Products reuse their existing dedicated pages — a template can include at most one of each.')
                            ->rule(fn (?AssessmentSection $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                if (! in_array($value, [AssessmentSection::KIND_HUMAN_RESOURCES, AssessmentSection::KIND_COMMODITY_MATRIX], true)) {
                                    return;
                                }

                                // section_type='structured_data' alone is
                                // ambiguous — it's shared with the
                                // informational facility_profile/bed_capacity
                                // rows, which don't count as a real
                                // "Human Resources" section. Exclude them by
                                // code, not just by the raw column value.
                                $exists = $this->getOwnerRecord()->sections()
                                    ->where('section_type', $value)
                                    ->whereNotIn('code', AssessmentSection::INFORMATIONAL_CODES)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                                    ->exists();

                                if ($exists) {
                                    $label = self::KIND_OPTIONS[$value];
                                    $fail("This template already has a \"{$label}\" section — only one is allowed.");
                                }
                            }),

                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Sections are displayed in ascending order'),

                        Forms\Components\Toggle::make('is_scored')
                            ->label('Scored')
                            ->default(true)
                            ->visible(fn (Get $get) => $get('section_type') === AssessmentSection::KIND_QUESTION_GROUP)
                            ->helperText('Whether this section contributes to the overall assessment score'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpan(2)
                            ->placeholder('Optional description of this section'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Section Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record): ?string => $record->description),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('section_type')
                    ->label('Kind')
                    ->formatStateUsing(fn (?string $state): string => self::KIND_OPTIONS[$state] ?? 'Dynamic Questions (Q&A)')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AssessmentSection::KIND_HUMAN_RESOURCES => 'warning',
                        AssessmentSection::KIND_COMMODITY_MATRIX => 'success',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-increment order if not provided
                        if (! isset($data['order']) || $data['order'] === 0) {
                            $maxOrder = $this->getOwnerRecord()->sections()->max('order') ?? 0;
                            $data['order'] = $maxOrder + 10;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_questions')
                    ->label('Manage Questions')
                    ->icon('heroicon-o-queue-list')
                    ->color('info')
                    ->visible(fn (AssessmentSection $record): bool => $record->section_type === AssessmentSection::KIND_QUESTION_GROUP)
                    ->url(fn (AssessmentSection $record): string => \App\Filament\Resources\AssessmentQuestionResource::getUrl('index', [
                        'activeTab' => $record->code,
                    ])),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
