<?php

namespace App\Filament\Resources\FacilityAssessmentResource\Pages;

use App\Filament\Resources\FacilityAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacilityAssessments extends ListRecords
{
    protected static string $resource = FacilityAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
