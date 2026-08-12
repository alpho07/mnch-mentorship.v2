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

        if (! empty($data['survey_event_id'])) {
            $event = \App\Models\SurveyEvent::find($data['survey_event_id']);

            if ($event?->repeatable) {
                $data['event_instance_number'] = $event->nextInstanceNumberFor(
                    $data['subject_type'] ?? null,
                    $data['subject_id'] ?? null,
                );
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SurveyResponseResource::getUrl('edit', ['record' => $this->record]);
    }
}
