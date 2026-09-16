<?php

namespace App\Filament\Resources\RagDocumentResource\Pages;

use App\Filament\Resources\RagDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRagDocuments extends ListRecords
{
    protected static string $resource = RagDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->can('create', static::getModel()) ?? false),
        ];
    }
}
