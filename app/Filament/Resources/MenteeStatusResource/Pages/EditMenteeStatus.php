<?php

namespace App\Filament\Resources\MenteeStatusResource\Pages;

use App\Filament\Resources\MenteeStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMenteeStatus extends EditRecord
{
    protected static string $resource = MenteeStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
