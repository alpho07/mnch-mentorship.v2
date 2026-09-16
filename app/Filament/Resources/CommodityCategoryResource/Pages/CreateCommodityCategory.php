<?php

namespace App\Filament\Resources\CommodityCategoryResource\Pages;

use App\Filament\Resources\CommodityCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommodityCategory extends CreateRecord {

    protected static string $resource = CommodityCategoryResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
