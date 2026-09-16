<?php

namespace App\Filament\Resources\AssessmentDepartmentResource\Pages;

use App\Filament\Resources\AssessmentDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentDepartments extends ListRecords {

    protected static string $resource = AssessmentDepartmentResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('New Department')
                    ->icon('heroicon-o-plus'),
        ];
    }
}
