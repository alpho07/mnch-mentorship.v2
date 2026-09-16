<?php

namespace App\Filament\Resources\IndicatorReportTypeResource\Pages;

use App\Filament\Resources\IndicatorReportTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIndicatorReportType extends EditRecord
{
    protected static string $resource = IndicatorReportTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
