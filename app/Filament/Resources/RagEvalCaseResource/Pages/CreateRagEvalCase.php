<?php

namespace App\Filament\Resources\RagEvalCaseResource\Pages;

use App\Filament\Resources\RagEvalCaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRagEvalCase extends CreateRecord
{
    protected static string $resource = RagEvalCaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['question_hash'] = hash('sha256', Str::lower(trim((string) $data['question'])));

        return $data;
    }
}
