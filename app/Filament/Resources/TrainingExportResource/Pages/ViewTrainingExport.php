<?php

namespace App\Filament\Resources\TrainingExportResource\Pages;

use App\Filament\Resources\TrainingExportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainingExport extends ViewRecord
{
    protected static string $resource = TrainingExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
