<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use App\Models\ScopeRoleAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateScope extends CreateRecord
{
    protected static string $resource = ScopeResource::class;

    protected function afterCreate(): void
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
}
