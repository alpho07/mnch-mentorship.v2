<?php

namespace App\Filament\Resources\IndicatorGroupResource\Pages;

use App\Filament\Resources\IndicatorGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIndicatorGroup extends EditRecord
{
    protected static string $resource = IndicatorGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
