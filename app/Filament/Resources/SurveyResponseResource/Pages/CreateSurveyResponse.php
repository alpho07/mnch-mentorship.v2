<?php

namespace App\Filament\Resources\SurveyResponseResource\Pages;

use App\Filament\Resources\SurveyResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurveyResponse extends CreateRecord
{
    protected static string $resource = SurveyResponseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SurveyResponseResource::getUrl('edit', ['record' => $this->record]);
    }
}
