<?php

namespace App\Filament\Resources\AssessmentTypeResource\Pages;

use App\Filament\Resources\AssessmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAssessmentType extends ViewRecord
{
    protected static string $resource = AssessmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
