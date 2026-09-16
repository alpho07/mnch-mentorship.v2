<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MyProfile extends Page
{
    protected static string $view = 'filament.pages.my-profile';

    protected static ?string $slug = 'my-profile';

    protected static ?string $title = 'My Profile';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public string $activeTab = 'view';

    // Profile fields
    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $id_number = '';

    // Password fields
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->first_name  = $user->first_name  ?? '';
        $this->middle_name = $user->middle_name ?? '';
        $this->last_name   = $user->last_name   ?? '';
        $this->email       = $user->email       ?? '';
        $this->phone       = $user->phone       ?? '';
        $this->id_number   = $user->id_number   ?? '';
    }

    public function updateProfile(): void
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . auth()->id(),
            'phone'      => 'nullable|string|max:20',
            'id_number'  => 'nullable|string|max:50',
        ]);

        auth()->user()->update([
            'first_name'  => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name'   => $this->last_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'id_number'   => $this->id_number,
        ]);

        Notification::make()->success()->title('Profile updated successfully.')->send();

        $this->activeTab = 'view';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password'          => 'required',
            'new_password'              => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'new_password_confirmation' => 'required',
        ]);

        if (! Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'The current password is incorrect.');
            return;
        }

        auth()->user()->update(['password' => Hash::make($this->new_password)]);

        $this->reset('current_password', 'new_password', 'new_password_confirmation');

        Notification::make()->success()->title('Password updated successfully.')->send();
    }
}
