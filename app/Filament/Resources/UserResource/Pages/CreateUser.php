<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\AccountVerificationMail;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord {

    protected static string $resource = UserResource::class;

    // ─────────────────────────────────────────────────────────────────────────
    // Create the user, sync pivots, then send the verification email.
    // A temporary random password is set so the account is not passwordless —
    // it is replaced when the user verifies via the emailed link.
    // ─────────────────────────────────────────────────────────────────────────

    protected function handleRecordCreation(array $data): Model {
        $roles = $data['roles'] ?? [];
        $permissions = $data['permissions'] ?? [];
        $counties = $data['counties'] ?? [];
        $subcounties = $data['subcounties'] ?? [];
        $facilities = $data['facilities'] ?? [];

        unset($data['roles'], $data['permissions'], $data['counties'], $data['subcounties'], $data['facilities']);

        // Temporary password — user will replace via verification link
        $data['password'] = bcrypt(Str::random(32));

        // Build display name
        $parts = array_filter([
            trim($data['first_name'] ?? ''),
            trim($data['middle_name'] ?? ''),
            trim($data['last_name'] ?? ''),
        ]);
        $data['name'] = implode(' ', $parts);

        /** @var \App\Models\User $user */
        $user = static::getModel()::create($data);

        // Sync Spatie roles & permissions
        if (!empty($roles)) {
            $user->syncRoles($roles);
        }
        if (!empty($permissions)) {
            $user->syncPermissions($permissions);
        }

        // Sync geographic access pivots
        $user->counties()->sync($counties);
        $user->subcounties()->sync($subcounties);
        $user->facilities()->sync($facilities);

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // After the record is persisted and roles are attached, send the email.
    // Reload roles relationship so the mailable can read the role name.
    // ─────────────────────────────────────────────────────────────────────────

    protected function afterCreate(): void {
        $user = $this->record->load('roles');

        try {
            Mail::to($user->email)->send(new AccountVerificationMail($user));

            Notification::make()
                    ->title('User Created')
                    ->body("Account created for **{$user->full_name}**. A verification email has been sent to {$user->email}.")
                    ->success()
                    ->persistent()
                    ->send();
        } catch (\Exception $e) {
            // Don't fail the creation if email fails — show a warning instead
            Notification::make()
                    ->title('User Created — Email Failed')
                    ->body("Account created but the verification email could not be sent: {$e->getMessage()}")
                    ->warning()
                    ->persistent()
                    ->send();
        }
    }

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }
}
