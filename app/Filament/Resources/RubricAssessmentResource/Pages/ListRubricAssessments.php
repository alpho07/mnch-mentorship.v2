<?php

namespace App\Filament\Resources\RubricAssessmentResource\Pages;

use App\Filament\Resources\RubricAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRubricAssessments extends ListRecords
{
    protected static string $resource = RubricAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('conduct')
                ->label('Conduct Assessment')
                ->icon('heroicon-o-plus')
                ->url(fn () => RubricAssessmentResource::getUrl('create'))
                ->color('primary'),
        ];
    }
}
