<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResponseResource\Pages;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Responses';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::response');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::response');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('survey_id')
                ->label('Survey')
                ->options(fn () => Survey::active()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->live(),
            Forms\Components\Select::make('survey_event_id')
                ->label('Event')
                ->options(fn (Forms\Get $get) => \App\Models\SurveyEvent::where('survey_id', $get('survey_id'))->ordered()->pluck('name', 'id'))
                ->visible(fn (Forms\Get $get) => $get('survey_id') && \App\Models\SurveyEvent::where('survey_id', $get('survey_id'))->exists())
                ->live()
                ->helperText(fn (Forms\Get $get) => optional(\App\Models\SurveyEvent::find($get('survey_event_id')))->repeatable
                    ? 'Repeatable — a new occurrence number is assigned automatically for the selected subject.'
                    : null),
            Forms\Components\Select::make('subject_type')
                ->label('Subject type')
                ->options([
                    \App\Models\Facility::class => 'Facility',
                    \App\Models\User::class => 'User',
                    \App\Models\MentorshipClass::class => 'Mentorship Class',
                ])
                ->live()
                ->visible(fn (Forms\Get $get) => filled($get('survey_event_id'))),
            Forms\Components\Select::make('subject_id')
                ->label('Subject')
                ->options(fn (Forms\Get $get) => match ($get('subject_type')) {
                    \App\Models\Facility::class => \App\Models\Facility::query()->pluck('name', 'id'),
                    \App\Models\User::class => \App\Models\User::query()->pluck('name', 'id'),
                    \App\Models\MentorshipClass::class => \App\Models\MentorshipClass::query()->pluck('name', 'id'),
                    default => [],
                })
                ->searchable()
                ->visible(fn (Forms\Get $get) => filled($get('subject_type'))),
            Forms\Components\TextInput::make('respondent_name')->maxLength(255),
            Forms\Components\TextInput::make('respondent_email')->email()->maxLength(255),
            Forms\Components\TextInput::make('respondent_contact')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('survey.name')->label('Survey')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('event.name')->label('Event')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('event_instance_number')->label('Instance #')->alignCenter()->placeholder('—'),
                Tables\Columns\TextColumn::make('respondent_name')->label('Respondent')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'submitted' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('overall_percentage')->label('Score')->suffix('%')->placeholder('—'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('survey_id')->relationship('survey', 'name'),
                Tables\Filters\SelectFilter::make('survey_event_id')->label('Event')->relationship('event', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'submitted' => 'Submitted']),
                Tables\Filters\Filter::make('subject')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Subject name contains'),
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['name'])) {
                            return $query;
                        }

                        return $query->whereHasMorph(
                            'subject',
                            [\App\Models\Facility::class, \App\Models\User::class, \App\Models\MentorshipClass::class],
                            fn ($subQuery) => $subQuery->where('name', 'like', '%'.$data['name'].'%')
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Fill / View'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyResponses::route('/'),
            'create' => Pages\CreateSurveyResponse::route('/create'),
            'edit' => Pages\EditSurveyResponse::route('/{record}/edit'),
        ];
    }
}
