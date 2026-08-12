<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurveyQuestion extends CreateRecord
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->decodeOptionsJson($data);
    }

    private function decodeOptionsJson(array $data): array
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
