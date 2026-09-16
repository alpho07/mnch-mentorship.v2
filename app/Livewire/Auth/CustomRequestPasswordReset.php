<?php

namespace App\Livewire\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CustomRequestPasswordReset extends SimplePage {

    protected static string $view = 'livewire.auth.custom-request-password-reset';
    protected static string $layout = 'components.layouts.auth';
    public ?array $data = [];
    public bool $linkSent = false;
    public string $sentToEmail = '';

    public function mount(): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function sendResetLink(): void {
        $data = $this->form->getState();

        if (!\App\Models\User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                        'data.email' => 'We cannot find a user with that email address.',
            ]);
        }

        $status = Password::broker(Filament::getAuthPasswordBroker())
                ->sendResetLink(['email' => $data['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                    ->title(__($status))
                    ->danger()
                    ->send();

            return;
        }

        // Advance to Step 2
        $this->sentToEmail = $data['email'];
        $this->linkSent = true;
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->exists('users', 'email')
                            ->autocomplete('email'),
                        ])
                        ->statePath('data');
    }
}
