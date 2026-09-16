<?php

namespace App\Filament\Resources\IndicatorGroupResource\Pages;

use App\Filament\Resources\IndicatorGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewIndicatorGroup extends ViewRecord
{
    protected static string $resource = IndicatorGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
