<?php

namespace App\Filament\Resources\RubricAssessmentResource\Pages;

use App\Filament\Resources\RubricAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRubricAssessment extends ViewRecord
{
    protected static string $resource = RubricAssessmentResource::class;

    protected static string $view = 'filament.pages.view-rubric-assessment';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Edit Assessment')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn () => EditRubricAssessment::getUrl(['record' => $this->record->id])),
        ];
    }
}
