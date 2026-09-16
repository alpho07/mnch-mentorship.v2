<?php

namespace App\Filament\Resources\AssessmentCategoryResource\Pages;

use App\Filament\Resources\AssessmentCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentCategories extends ListRecords
{
    protected static string $resource = AssessmentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
