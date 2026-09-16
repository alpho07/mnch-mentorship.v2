<?php

namespace App\Filament\Resources\AssessmentDepartmentResource\Pages;

use App\Filament\Resources\AssessmentDepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentDepartment extends CreateRecord {

    protected static string $resource = AssessmentDepartmentResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
