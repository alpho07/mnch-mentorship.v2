<?php

namespace App\Filament\Resources\ProgramModuleQuizResource\Pages;

use App\Filament\Resources\ProgramModuleQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgramModuleQuiz extends EditRecord
{
    protected static string $resource = ProgramModuleQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
