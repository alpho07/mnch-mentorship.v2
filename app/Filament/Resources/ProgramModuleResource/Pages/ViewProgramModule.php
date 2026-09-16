<?php

namespace App\Filament\Resources\ProgramModuleResource\Pages;

use App\Filament\Resources\ProgramModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProgramModule extends ViewRecord
{
    protected static string $resource = ProgramModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
