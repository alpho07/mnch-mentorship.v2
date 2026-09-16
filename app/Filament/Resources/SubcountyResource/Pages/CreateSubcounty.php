<?php


namespace App\Filament\Resources\SubcountyResource\Pages;

use App\Filament\Resources\SubcountyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubcounty extends CreateRecord
{
    protected static string $resource = SubcountyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
