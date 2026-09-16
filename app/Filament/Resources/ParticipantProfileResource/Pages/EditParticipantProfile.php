<?php

namespace App\Filament\Resources\ParticipantProfileResource\Pages;

use App\Filament\Resources\ParticipantProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParticipantProfile extends EditRecord
{
    protected static string $resource = ParticipantProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
