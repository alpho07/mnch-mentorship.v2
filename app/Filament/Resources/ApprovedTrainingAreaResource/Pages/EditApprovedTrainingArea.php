<?php

namespace App\Filament\Resources\ApprovedTrainingAreaResource\Pages;

use App\Filament\Resources\ApprovedTrainingAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApprovedTrainingArea extends EditRecord
{
    protected static string $resource = ApprovedTrainingAreaResource::class; 

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
