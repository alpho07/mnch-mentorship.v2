<?php

namespace App\Filament\Resources\ApprovedTrainingAreaResource\Pages; 

use App\Filament\Resources\ApprovedTrainingAreaResource;
use Filament\Actions; 
use Filament\Resources\Pages\ListRecords;

class ListApprovedTrainingAreas extends ListRecords
{
    protected static string $resource = ApprovedTrainingAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
