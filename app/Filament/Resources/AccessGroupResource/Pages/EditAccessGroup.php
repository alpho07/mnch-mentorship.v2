<?php

namespace App\Filament\Resources\AccessGroupResource\Pages;

use App\Filament\Resources\AccessGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccessGroup extends EditRecord
{
    protected static string $resource = AccessGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
