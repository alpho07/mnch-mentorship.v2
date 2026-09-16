<?php

namespace App\Livewire\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomResetPassword extends SimplePage {

    protected static string $view = 'livewire.auth.custom-reset-password';
    protected static string $layout = 'components.layouts.auth';
    public string $token = '';
    public string $email = '';
    public ?array $data = [];

    public function mount(string $token): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
            return;
        }

        $this->token = $token;
        $this->email = request()->query('email', '');

        if (blank($this->token) || blank($this->email)) {
            redirect()->route('filament.admin.auth.password-reset.request');
            return;
        }

        $this->form->fill([
            'email' => $this->email,
        ]);
    }

    public function resetPassword(): void {
        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->reset(
                [
                    'email' => $this->email,
                    'password' => $data['password'],
                    'password_confirmation' => $data['password_confirmation'],
                    'token' => $this->token,
                ],
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                        'status' => 'active',
                    ])->save();

                    event(new PasswordReset($user));
                }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                        'data.password' => __($status),
            ]);
        }

        Notification::make()
                ->title('Password updated successfully. Please sign in.')
                ->success()
                ->send();

        redirect()->route('filament.admin.auth.login');
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->disabled()
                            ->dehydrated(false),
                            TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->rules(['regex:/[A-Z]/', 'regex:/[0-9]/'])
                            ->validationMessages([
                                'regex' => 'Password must contain at least one uppercase letter and one number.',
                            ])
                            ->same('password_confirmation')
                            ->autocomplete('new-password')
                            ->extraInputAttributes(['id' => 'pw-new', 'oninput' => 'pwStrength(this.value)']),
                            TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->required()
                            ->autocomplete('new-password')
                            ->extraInputAttributes(['id' => 'pw-confirm']),
                        ])
                        ->statePath('data');
    }

    public function getTitle(): string {
        return '';
    }

    public function hasLogo(): bool {
        return false;
    }
}
