<?php

namespace App\Filament\Resources\FacilityOwnershipResource\Pages;

use App\Filament\Resources\FacilityOwnershipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacilityOwnerships extends ListRecords
{
    protected static string $resource = FacilityOwnershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
