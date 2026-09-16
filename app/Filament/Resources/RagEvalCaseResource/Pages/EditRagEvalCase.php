<?php

namespace App\Filament\Resources\RagEvalCaseResource\Pages;

use App\Filament\Resources\RagEvalCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditRagEvalCase extends EditRecord
{
    protected static string $resource = RagEvalCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['question_hash'] = hash('sha256', Str::lower(trim((string) $data['question'])));

        return $data;
    }
}
