<?php

namespace App\Filament\Resources\ProgramModuleQuizResource\Pages;

use App\Filament\Resources\ProgramModuleQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProgramModuleQuiz extends ViewRecord
{
    protected static string $resource = ProgramModuleQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
