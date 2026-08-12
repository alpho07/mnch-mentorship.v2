<?php

namespace App\Filament\Resources\SurveyResponseResource\Pages;

use App\Filament\Resources\SurveyResponseResource;
use App\Services\SurveyFormBuilder;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSurveyResponse extends EditRecord
{
    protected static string $resource = SurveyResponseResource::class;

    public function form(Form $form): Form
    {
        return $form->schema(
            SurveyFormBuilder::buildForSurvey($this->record->survey, $this->record->id)
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        foreach ($record->survey->sections()->active()->get() as $section) {
            SurveyFormBuilder::saveResponses($record->id, $section->id, $data);
        }

        return $record->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submit')
                ->label('Submit')
                ->color('success')
                ->action(function () {
                    $this->save();
                    $this->record->markSubmitted();

                    Notification::make()->title('Response submitted')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
