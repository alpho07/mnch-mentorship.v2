<?php

namespace App\Filament\Resources\FacilityOwnershipResource\Pages;

use App\Filament\Resources\FacilityOwnershipResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFacilityOwnership extends EditRecord
{
    protected static string $resource = FacilityOwnershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
