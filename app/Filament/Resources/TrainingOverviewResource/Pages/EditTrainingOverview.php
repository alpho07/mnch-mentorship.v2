<?php

namespace App\Filament\Resources\TrainingOverviewResource\Pages;

use App\Filament\Resources\TrainingOverviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainingOverview extends EditRecord
{
    protected static string $resource = TrainingOverviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
