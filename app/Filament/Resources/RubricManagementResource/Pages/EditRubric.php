<?php

namespace App\Filament\Resources\RubricManagementResource\Pages;

use App\Filament\Resources\RubricManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRubric extends EditRecord
{
    protected static string $resource = RubricManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
