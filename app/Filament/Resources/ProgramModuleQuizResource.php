<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgramModuleQuizResource\Pages;
use App\Filament\Resources\ProgramModuleQuizResource\RelationManagers;
use App\Models\ProgramModule;
use App\Models\ProgramModuleQuiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProgramModuleQuizResource extends Resource
{
    protected static ?string $model = ProgramModuleQuiz::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Quiz Management';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_program::module::quiz');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Quiz Details')
                ->schema([
                    Forms\Components\Select::make('program_module_id')
                        ->label('Module / Track')
                        ->relationship('programModule', 'name')
                        ->options(function (): array {
                            return ProgramModule::orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('title')
                        ->label('Quiz Title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('type')
                        ->label('Quiz Type')
                        ->options([
                            'pre_test' => 'Pre-test only',
                            'post_test' => 'Post-test only',
                            'both' => 'Both pre-test and post-test',
                        ])
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('pass_mark_percentage')
                        ->label('Pass Mark (%)')
                        ->numeric()
                        ->default(85)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->required(),

                    Forms\Components\TextInput::make('time_limit_minutes')
                        ->label('Time Limit')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->maxValue(180)
                        ->suffix('minutes')
                        ->helperText('Leave blank for no time limit.'),

                    Forms\Components\TextInput::make('order_sequence')
                        ->label('Order Sequence')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('programModule.name')
                    ->label('Module / Track')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pre_test' => 'Pre-test',
                        'post_test' => 'Post-test',
                        'both' => 'Pre & Post',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pre_test' => 'info',
                        'post_test' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pass_mark_percentage')
                    ->label('Pass Mark')
                    ->suffix('%')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('time_limit_minutes')
                    ->label('Time Limit')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} min" : 'No limit')
                    ->badge()
                    ->color(fn (?int $state): string => $state ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Questions')
                    ->counts('questions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'pre_test' => 'Pre-test',
                        'post_test' => 'Post-test',
                        'both' => 'Both',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('program_module_id')
                    ->label('Module / Track')
                    ->relationship('programModule', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->modalHeading(fn (ProgramModuleQuiz $record): string => "Preview: {$record->title}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (ProgramModuleQuiz $record): \Illuminate\Contracts\View\View => view(
                        'filament.resources.program-module-quiz.preview',
                        ['quiz' => $record->load('questions.options')]
                    )),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('No Quizzes Yet')
            ->emptyStateDescription('Quizzes are attached to a module as its pre-test or post-test.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramModuleQuizzes::route('/'),
            'create' => Pages\CreateProgramModuleQuiz::route('/create'),
            'view' => Pages\ViewProgramModuleQuiz::route('/{record}'),
            'edit' => Pages\EditProgramModuleQuiz::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'programModule.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Module' => $record->programModule?->name,
            'Type' => $record->type,
        ];
    }
}
