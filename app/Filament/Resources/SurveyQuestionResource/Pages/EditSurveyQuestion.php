<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSurveyQuestion extends EditRecord
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['question_type'] ?? null, ['repeater', 'matrix'], true) && ! empty($this->form->getRawState()['options_json'] ?? null)) {
            $decoded = json_decode($this->form->getRawState()['options_json'], true);
            if (is_array($decoded)) {
                $data['options'] = $decoded;
            }
        }

        return $data;
    }
}
