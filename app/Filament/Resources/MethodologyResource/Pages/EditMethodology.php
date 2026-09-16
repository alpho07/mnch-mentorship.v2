<?php

namespace App\Filament\Resources\MethodologyResource\Pages;

use App\Filament\Resources\MethodologyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMethodology extends EditRecord
{
    protected static string $resource = MethodologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
