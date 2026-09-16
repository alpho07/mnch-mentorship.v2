<?php

namespace App\Filament\Resources\CadreResource\Pages;

use App\Filament\Resources\CadreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCadre extends CreateRecord
{
    protected static string $resource = CadreResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
