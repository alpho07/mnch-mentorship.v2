<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord {

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array {
        $user = $this->record;

        // Load current roles and org units for the form
        $data['roles'] = $user->roles->pluck('name')->toArray();
        $data['counties'] = $user->counties->pluck('id')->toArray();
        $data['subcounties'] = $user->subcounties->pluck('id')->toArray();
        $data['facilities'] = $user->facilities->pluck('id')->toArray();
        $data = UserResource::populateNamePartsFromDisplayName($data);

        // Recalculate display name from first, middle, last names
        $parts = array_filter([
            trim($data['first_name'] ?? ''),
            trim($data['middle_name'] ?? ''),
            trim($data['last_name'] ?? ''),
        ]);
        $data['name'] = implode(' ', $parts);

        return $data;
    }

    protected function handleRecordUpdate($user, array $data): \Illuminate\Database\Eloquent\Model {
        $roles = $data['roles'] ?? [];
        $counties = $data['counties'] ?? [];
        $subcounties = $data['subcounties'] ?? [];
        $facilities = $data['facilities'] ?? [];

        unset($data['roles'], $data['counties'], $data['subcounties'], $data['facilities']);

        // Remove password from update data — password cannot be changed during edit
        unset($data['password']);

        // Ensure display name is built from first, middle, last names
        $parts = array_filter([
            trim($data['first_name'] ?? ''),
            trim($data['middle_name'] ?? ''),
            trim($data['last_name'] ?? ''),
        ]);
        $data['name'] = implode(' ', $parts);

        // Update user data (excluding pivot data and password)
        $user->update($data);

        // Sync roles
        if (!empty($roles)) {
            $user->syncRoles($roles);
        }

        // Sync organization units
        $user->counties()->sync($counties);
        $user->subcounties()->sync($subcounties);
        $user->facilities()->sync($facilities);

        return $user;
    }
}
