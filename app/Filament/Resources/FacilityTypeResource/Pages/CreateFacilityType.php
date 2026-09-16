<?php

namespace App\Filament\Resources\FacilityTypeResource\Pages;

use App\Filament\Resources\FacilityTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacilityType extends CreateRecord
{
    protected static string $resource = FacilityTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}