<?php

namespace App\Filament\Resources\AssessmentSectionResource\Pages;

use App\Filament\Resources\AssessmentSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentSections extends ListRecords {

    protected static string $resource = AssessmentSectionResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('New Section')
                    ->icon('heroicon-o-plus'),
        ];
    }
}
