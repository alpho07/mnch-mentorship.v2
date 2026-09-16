<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ViewUser extends ViewRecord {

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\EditAction::make()
                    ->icon('heroicon-o-pencil'),
                    Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn() => $this->record->status !== 'active')
                    ->action(function () {
                        $this->record->update(['status' => 'active']);
                        Notification::make()->success()->title("{$this->record->full_name} activated")->send();
                        $this->refreshFormData(['status']);
                    }),
                    Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn() => $this->record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function () {
                        $this->record->update(['status' => 'suspended']);
                        Notification::make()->warning()->title("{$this->record->full_name} suspended")->send();
                        $this->refreshFormData(['status']);
                    }),
                    Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn() => $this->record->status !== 'trainee')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('A new random 10-character password will be generated.')
                    ->action(function () {
                        $newPassword = Str::random(10);
                        $this->record->update(['password' => Hash::make($newPassword)]);

                        Notification::make()
                                ->title('Password Reset')
                                ->body("New password for **{$this->record->full_name}**: `{$newPassword}`\n\nCopy and share securely.")
                                ->success()
                                ->persistent()
                                ->send();
                    }),
                    Actions\DeleteAction::make()
                    ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist {
        return $infolist->schema([
                    // ── Profile header ─────────────────────────────────────────────
                            Infolists\Components\Section::make()
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                ->schema([
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('full_name')
                                        ->label('Full Name')
                                        ->size('xl')
                                        ->weight('bold'),
                                        Infolists\Components\TextEntry::make('roles.name')
                                        ->label('Roles')
                                        ->badge()
                                        ->separator(',')
                                        ->color(fn(string $state): string => match (strtolower($state)) {
                                                    'super_admin', 'super admin' => 'danger',
                                                    'admin' => 'rose',
                                                    'division', 'division lead' => 'warning',
                                                    'mentor' => 'info',
                                                    'co_mentor', 'co-mentor' => 'purple',
                                                    'mentee' => 'success',
                                                    default => 'gray',
                                                }),
                                    ])->columnSpan(2),
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('status')
                                        ->label('Account Status')
                                        ->badge()
                                        ->color(fn(?string $state): string => match ($state) {
                                                    'active' => 'success',
                                                    'inactive' => 'gray',
                                                    'suspended' => 'danger',
                                                    'trainee' => 'warning',
                                                    default => 'gray',
                                                }),
                                        Infolists\Components\TextEntry::make('created_at')
                                        ->label('Registered')
                                        ->date('d M Y'),
                                    ])->columnSpan(1),
                                    Infolists\Components\Group::make([
                                        Infolists\Components\TextEntry::make('id_number')
                                        ->label('National ID')
                                        ->copyable()
                                        ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('email')
                                        ->label('Email')
                                        ->copyable()
                                        ->placeholder('—'),
                                    ])->columnSpan(1),
                                ]),
                            ]),
                    // ── Contact & Identity ─────────────────────────────────────────
                    Infolists\Components\Section::make('Contact & Identity')
                            ->icon('heroicon-o-identification')
                            ->columns(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('phone')
                                ->label('Phone')
                                ->copyable()
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('email')
                                ->label('Email')
                                ->copyable()
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('id_number')
                                ->label('National ID')
                                ->copyable()
                                ->placeholder('—'),
                            ]),
                    // ── Organisation ───────────────────────────────────────────────
                    Infolists\Components\Section::make('Organisation')
                            ->icon('heroicon-o-building-office-2')
                            ->columns(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('facility.name')
                                ->label('Primary Facility')
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('facility.mfl_code')
                                ->label('MFL Code')
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('facility.subcounty.name')
                                ->label('Sub-County')
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('facility.subcounty.county.name')
                                ->label('County')
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('department.name')
                                ->label('Department')
                                ->placeholder('—'),
                                Infolists\Components\TextEntry::make('cadre.name')
                                ->label('Cadre')
                                ->badge()
                                ->color('gray')
                                ->placeholder('—'),
                            ]),
                    // ── Mentorship participation ────────────────────────────────────
                    Infolists\Components\Section::make('Mentorship Participation')
                            ->icon('heroicon-o-academic-cap')
                            ->columns(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('class_participations_count')
                                ->label('Classes Enrolled')
                                ->state(fn(User $record) =>
                                        $record->classParticipations()->count()
                                )
                                ->badge()
                                ->color('success'),
                                Infolists\Components\TextEntry::make('completed_modules_count')
                                ->label('Modules Completed')
                                ->state(fn(User $record) =>
                                        \App\Models\MenteeModuleProgress::whereHas('classParticipant', fn($q) =>
                                                $q->where('user_id', $record->id)
                                        )->where('status', 'completed')->count()
                                )
                                ->badge()
                                ->color('info'),
                                Infolists\Components\TextEntry::make('in_progress_modules_count')
                                ->label('Modules In Progress')
                                ->state(fn(User $record) =>
                                        \App\Models\MenteeModuleProgress::whereHas('classParticipant', fn($q) =>
                                                $q->where('user_id', $record->id)
                                        )->where('status', 'in_progress')->count()
                                )
                                ->badge()
                                ->color('warning'),
                            ]),
        ]);
    }
}
