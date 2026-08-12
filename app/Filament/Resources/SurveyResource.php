<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResource\Pages;
use App\Filament\Resources\SurveyResource\RelationManagers;
use App\Models\Survey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Surveys';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Survey Details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Survey Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('Unique identifier, e.g. TRAINING_FEEDBACK_2026'),
                        Forms\Components\TextInput::make('version')
                            ->default('1.0')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Only active surveys are selectable for filling out.'),
                        Forms\Components\Toggle::make('is_public')
                            ->default(false)
                            ->helperText('Enables the "Get Link" action, which generates a shareable public link.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpan(2),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Survey $record): ?string => $record->description),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_public')->boolean()->label('Public'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->default(true),
            ])
            ->actions([
                Tables\Actions\Action::make('get_link')
                    ->label('Get Link')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Survey $record): bool => $record->is_public)
                    ->action(function (Survey $record) {
                        if (! $record->access_token) {
                            $record->update(['access_token' => Str::random(32)]);
                        }

                        Notification::make()
                            ->title('Public link ready')
                            ->body(url('/survey/'.$record->fresh()->access_token))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'create' => Pages\CreateSurvey::route('/create'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Survey::active()->count();
    }
}
