<?php

namespace App\Filament\Resources\FacilityLevelResource\Pages;

use App\Filament\Resources\FacilityLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFacilityLevel extends EditRecord
{
    protected static string $resource = FacilityLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
