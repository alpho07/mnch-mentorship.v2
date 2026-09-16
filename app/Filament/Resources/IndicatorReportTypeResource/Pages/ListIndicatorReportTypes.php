<?php

namespace App\Filament\Resources\IndicatorReportTypeResource\Pages;

use App\Filament\Resources\IndicatorReportTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIndicatorReportTypes extends ListRecords
{
    protected static string $resource = IndicatorReportTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
