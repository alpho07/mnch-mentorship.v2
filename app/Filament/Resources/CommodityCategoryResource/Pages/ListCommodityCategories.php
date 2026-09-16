<?php

namespace App\Filament\Resources\CommodityCategoryResource\Pages;

use App\Filament\Resources\CommodityCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommodityCategories extends ListRecords {

    protected static string $resource = CommodityCategoryResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('New Category')
                    ->icon('heroicon-o-plus'),
        ];
    }
}
