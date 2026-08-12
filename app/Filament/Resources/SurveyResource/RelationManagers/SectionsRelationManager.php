<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Survey Sections';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->columnSpan(2),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->helperText('Unique within this survey'),
                Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
                Forms\Components\Toggle::make('is_scored')->default(true),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpan(2),
            ]),
            Forms\Components\CheckboxList::make('events')
                ->relationship('events', 'name')
                ->label('Shown at events')
                ->helperText('Leave all unchecked to show this section at every event. Check specific events to show it only at those.')
                ->columns(2)
                ->visible(fn () => $this->getOwnerRecord()->events()->exists())
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('order')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('questions_count')->label('Questions')->counts('questions')->badge()->color('info'),
                Tables\Columns\IconColumn::make('is_scored')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! isset($data['order']) || $data['order'] === 0) {
                            $data['order'] = ($this->getOwnerRecord()->sections()->max('order') ?? 0) + 10;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_questions')
                    ->label('Questions')
                    ->icon('heroicon-o-queue-list')
                    ->url(fn ($record): string => SurveyQuestionResource::getUrl('index', [
                        'tableFilters' => ['survey_section_id' => ['value' => $record->id]],
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->questions()->count() > 0) {
                            Notification::make()->title('Cannot delete — has questions')->danger()->send();

                            return false;
                        }
                    }),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
