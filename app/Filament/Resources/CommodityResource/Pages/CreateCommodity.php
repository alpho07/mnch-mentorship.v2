<?php

namespace App\Filament\Resources\CommodityResource\Pages;

use App\Filament\Resources\CommodityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommodity extends CreateRecord {

    protected static string $resource = CommodityResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }
}
