<?php

namespace App\Filament\Resources\StockLevelResource\Pages;

use App\Filament\Resources\StockLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockLevels extends ListRecords
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
