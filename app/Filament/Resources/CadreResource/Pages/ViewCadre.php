<?php
namespace App\Filament\Resources\CadreResource\Pages;

use App\Filament\Resources\CadreResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCadre extends ViewRecord
{
    protected static string $resource = CadreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }
}