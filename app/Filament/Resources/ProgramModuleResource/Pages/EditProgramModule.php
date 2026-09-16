<?php

namespace App\Filament\Resources\ProgramModuleResource\Pages;

use App\Filament\Resources\ProgramModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgramModule extends EditRecord
{
    protected static string $resource = ProgramModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
