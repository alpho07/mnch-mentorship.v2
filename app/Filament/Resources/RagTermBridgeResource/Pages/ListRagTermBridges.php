<?php

namespace App\Filament\Resources\RagTermBridgeResource\Pages;

use App\Filament\Resources\RagTermBridgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRagTermBridges extends ListRecords
{
    protected static string $resource = RagTermBridgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
