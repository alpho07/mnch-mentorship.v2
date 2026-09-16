<?php

namespace App\Filament\Resources\AccessGroupResource\Pages;

use App\Filament\Resources\AccessGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccessGroups extends ListRecords
{
    protected static string $resource = AccessGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
