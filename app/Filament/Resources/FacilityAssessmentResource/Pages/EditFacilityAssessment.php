<?php

namespace App\Filament\Resources\FacilityAssessmentResource\Pages;

use App\Filament\Resources\FacilityAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFacilityAssessment extends EditRecord
{
    protected static string $resource = FacilityAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
