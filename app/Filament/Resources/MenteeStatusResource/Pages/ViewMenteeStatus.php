<?php

namespace App\Filament\Resources\MenteeStatusResource\Pages;

use App\Filament\Resources\MenteeStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMenteeStatus extends ViewRecord
{
    protected static string $resource = MenteeStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
