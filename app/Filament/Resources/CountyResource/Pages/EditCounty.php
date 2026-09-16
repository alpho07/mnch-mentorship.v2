<?php

namespace App\Filament\Resources\CountyResource\Pages;

use App\Filament\Resources\CountyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCounty extends EditRecord
{
    protected static string $resource = CountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
