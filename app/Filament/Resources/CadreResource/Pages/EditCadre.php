<?php

namespace App\Filament\Resources\CadreResource\Pages;

use App\Filament\Resources\CadreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCadre extends EditRecord
{
    protected static string $resource = CadreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}