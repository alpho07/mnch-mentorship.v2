<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use App\Models\ScopeRoleAccess;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScope extends EditRecord
{
    protected static string $resource = ScopeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_names'] = $this->getRecord()
            ->roleAccess()
            ->pluck('role_name')
            ->toArray();
        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncRoles($this->getRecord(), $this->form->getState()['role_names'] ?? []);
    }

    private function syncRoles($record, array $roleNames): void
    {
        $record->roleAccess()->delete();
        foreach ($roleNames as $role) {
            ScopeRoleAccess::create(['scope_id' => $record->id, 'role_name' => $role]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
