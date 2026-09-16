<?php

namespace App\Filament\Resources\AssessmentSectionResource\Pages;

use App\Filament\Resources\AssessmentSectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentSection extends CreateRecord {

    protected static string $resource = AssessmentSectionResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
