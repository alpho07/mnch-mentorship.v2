<?php

namespace App\Filament\Resources\RubricManagementResource\Pages;

use App\Filament\Resources\RubricManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRubrics extends ListRecords
{
    protected static string $resource = RubricManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New Rubric'),
        ];
    }
}
