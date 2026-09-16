<?php

namespace App\Filament\Resources\SubcountyResource\Pages;

use App\Filament\Resources\SubcountyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubcounties extends ListRecords
{
    protected static string $resource = SubcountyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}