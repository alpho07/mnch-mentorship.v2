<?php

namespace App\Filament\Resources\FacilityTypeResource\Pages;

use App\Filament\Resources\FacilityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacilityTypes extends ListRecords
{
    protected static string $resource = FacilityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
