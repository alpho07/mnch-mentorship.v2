<?php

namespace App\Filament\Resources\IndicatorReportTypeResource\Pages;

use App\Filament\Resources\IndicatorReportTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIndicatorReportType extends ViewRecord
{
    protected static string $resource = IndicatorReportTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
