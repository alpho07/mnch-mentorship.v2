<?php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenteeProfile extends EditRecord
{
    protected static string $resource = MenteeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
