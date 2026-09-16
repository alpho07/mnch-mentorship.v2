<?php

namespace App\Filament\Resources\TrainingOverviewResource\Pages;

use App\Filament\Resources\TrainingOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainingOverview extends ViewRecord
{
    protected static string $resource = TrainingOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
