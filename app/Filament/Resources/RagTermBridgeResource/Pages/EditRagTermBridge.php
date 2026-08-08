<?php

namespace App\Filament\Resources\RagTermBridgeResource\Pages;

use App\Filament\Resources\RagTermBridgeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRagTermBridge extends EditRecord
{
    protected static string $resource = RagTermBridgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
