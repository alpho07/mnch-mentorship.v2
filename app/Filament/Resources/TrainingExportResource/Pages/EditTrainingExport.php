<?php

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainingExport extends EditRecord
{
    protected static string $resource = TrainingExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
