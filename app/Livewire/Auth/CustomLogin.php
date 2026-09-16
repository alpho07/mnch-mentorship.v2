<?php

namespace App\Livewire\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class CustomLogin extends SimplePage
{
    protected static string $view = 'livewire.auth.custom-login';

    protected static string $layout = 'components.layouts.auth';

    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $email = session()->pull('login_email');

        $this->form->fill($email ? ['email' => $email] : []);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $data = $this->form->getState();

            if (! Filament::auth()->attempt([
                'email' => $data['email'],
                'password' => $data['password'],
            ], $data['remember'] ?? false)) {
                throw ValidationException::withMessages([
                    'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
                ]);
            }

            session()->regenerate();

            return app(LoginResponse::class);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/login.messages.failed'))
                ->danger()
                ->send();

            throw $exception;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->prefixIcon('heroicon-m-envelope')
                    ->required()
                    ->autofocus()
                    ->autocomplete('username')
                    ->extraInputAttributes(['class' => 'custom-input']),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->prefixIcon('heroicon-m-lock-closed')
                    ->required()
                    ->autocomplete('current-password')
                    ->extraInputAttributes(['class' => 'custom-input']),
                Checkbox::make('remember')
                    ->label('Remember me'),
            ])
            ->statePath('data');
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
