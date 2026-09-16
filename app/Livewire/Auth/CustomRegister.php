<?php

namespace App\Livewire\Auth;

use App\Mail\AccountVerificationMail;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms; 
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CustomRegister extends SimplePage implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'livewire.auth.custom-register';

    public ?array $data           = [];
    public string $captchaQuestion = '';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
        $this->generateCaptcha();
        $this->form->fill();
    }

    public function generateCaptcha(): void
    {
        $a  = rand(2, 15);
        $b  = rand(2, 15);
        $op = ['+', '-', '×'][rand(0, 2)];

        if ($op === '-' && $a < $b) {
            [$a, $b] = [$b, $a];
        }

        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '×' => $a * $b,
        };

        $this->captchaQuestion = "What is {$a} {$op} {$b}?";
        session(['captcha_answer' => $answer]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('middle_name')
                            ->label('Middle Name (Optional)')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Section::make('Contact Details')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique('users', 'email')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->unique('users', 'phone')
                            ->maxLength(20),
                    ])
                    ->columns(1),

                Section::make('Professional Information')
                    ->schema([
                        Select::make('cadre_id')
                            ->label('Cadre')
                            ->options(fn () => MainCadre::where('is_active', true)->orderBy('order')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('department_id')
                            ->label('Department')
                            ->options(fn () => Department::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'mentee'          => 'Mentee',
                                'facility_mentor' => 'Facility Mentor',
                            ])
                            ->default('mentee')
                            ->required(),
                    ])
                    ->columns(1),

                Section::make('Geographic Scope')
                    ->description('Select your county, then choose your facility.')
                    ->schema([
                        Select::make('county_id')
                            ->label('County')
                            ->options(fn () => County::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('facility_id', null)),

                        Select::make('facility_id')
                            ->label('Facility')
                            ->options(fn (Get $get) => $get('county_id')
                                ? Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $get('county_id')))
                                    ->orderBy('name')
                                    ->get(['id', 'name', 'mfl_code'])
                                    ->mapWithKeys(fn ($f) => [
                                        $f->id => $f->name . ($f->mfl_code ? ' [' . $f->mfl_code . ']' : ''),
                                    ])
                                    ->toArray()
                                : []
                            )
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get) => ! filled($get('county_id')))
                            ->helperText(fn (Get $get) => ! filled($get('county_id')) ? 'Select a county first' : null),
                    ])
                    ->columns(1),

                Section::make('Security Check')
                    ->schema([
                        TextInput::make('captcha_input')
                            ->label(fn () => $this->captchaQuestion)
                            ->required()
                            ->integer()
                            ->inputMode('numeric')
                            ->autocomplete('off')
                            ->suffixActions([
                                FormAction::make('refresh_captcha')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip('Get a new question')
                                    ->action(fn () => $this->generateCaptcha()),
                            ])
                            ->rules([
                                fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                    if ((int) $value !== (int) session('captcha_answer')) {
                                        $this->generateCaptcha();
                                        $fail('Incorrect answer. Please try the new question.');
                                    }
                                },
                            ]),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function register(): ?RegistrationResponse
    {
        $data = $this->form->getState();

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create([
                    'first_name'    => ucfirst(strtolower(trim($data['first_name']))),
                    'middle_name'   => filled($data['middle_name'] ?? null)
                        ? ucfirst(strtolower(trim($data['middle_name'])))
                        : null,
                    'last_name'     => ucfirst(strtolower(trim($data['last_name']))),
                    'name'          => trim(collect([
                        $data['first_name'],
                        $data['middle_name'] ?? null,
                        $data['last_name'],
                    ])->filter()->map(fn ($n) => ucfirst(strtolower(trim($n))))->implode(' ')),
                    'email'         => $data['email'],
                    'phone'         => $data['phone'],
                    'cadre_id'      => $data['cadre_id'],
                    'department_id' => $data['department_id'],
                    'facility_id'   => $data['facility_id'],
                    'password'      => bcrypt(Str::random(32)),
                    'status'        => 'pending',
                ]);

                $user->assignRole($data['role'] ?? 'mentee');
                $user->counties()->sync([$data['county_id']]);
                $user->facilities()->sync([$data['facility_id']]);

                return $user;
            });

            $user->load('roles');
            session()->forget('captcha_answer');
            Mail::to($user->email)->send(new AccountVerificationMail($user));

            Notification::make()
                ->success()
                ->title('Registration Successful!')
                ->body("Please check your email ({$user->email}) for further instructions. Click the link in the email to set your password and activate your account.")
                ->persistent()
                ->send();

            $this->redirect(route('filament.admin.auth.login'));

            return null;
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Registration Failed')
                ->body('An unexpected error occurred. Please try again or contact support.')
                ->send();

            report($e);

            return null;
        }
    }

    public function getTitle(): string { return ''; }
    public function hasLogo(): bool    { return false; }
}

