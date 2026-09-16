<?php

namespace App\Filament\Resources\IndicatorGroupResource\Pages;

use App\Filament\Resources\IndicatorGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIndicatorGroups extends ListRecords
{
    protected static string $resource = IndicatorGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
