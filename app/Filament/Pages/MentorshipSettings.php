<?php

namespace App\Filament\Pages;

use App\Models\Program;
use App\Models\Setting;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A settings hub for mentorship-wide configuration, starting with program
 * activation. Deactivating a program here hides it from the mentorship
 * creation flow for most roles (Program::isSelectableBy()) — the
 * Program & Schedule picker still lists it, but disabled and labelled "Not
 * Active" rather than hidden outright. Full program editing (name,
 * description, per-role visibility overrides) stays under Curriculum →
 * Programs; this page is deliberately just the on/off switch.
 *
 * Also controls whether the "New Mentorship" / "New Mentorship Guided
 * Setup" buttons on the mentorships list are usable — see Setting and
 * ListMentorshipTrainings::getHeaderActions().
 */
class MentorshipSettings extends Page implements HasActions, HasForms, Tables\Contracts\HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Mentorship Settings';

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.mentorship-settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('update_program');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('update_program');
    }

    public function mount(): void
    {
        $this->form->fill([
            'program_scoping_enabled' => Setting::getBool(Setting::PROGRAM_SCOPING_ENABLED, false),
            'new_mentorship_button_enabled' => Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED),
            'guided_setup_button_enabled' => Setting::getBool(Setting::GUIDED_SETUP_BUTTON_ENABLED),
            'chat_setup_button_enabled' => Setting::getBool(Setting::CHAT_SETUP_BUTTON_ENABLED),
            'mnchgpt_button_enabled' => Setting::getBool(Setting::MNCHGPT_BUTTON_ENABLED),
            'quick_setup_button_enabled' => Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Program Scoping')
                    ->description('Master switch for the per-user "Program Scope" field (User Management → All Users). When on, mentor-tier users whose scope is set to EmONC / Newborn Care / Infant & Child Care only see and manage trainings for that program. When off, everyone sees all programs regardless of their individual setting.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Forms\Components\Toggle::make('program_scoping_enabled')
                            ->label('Enforce per-user Program Scope')
                            ->helperText('Off by default — turning this on immediately restricts every scoped mentor to their assigned program(s).')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::PROGRAM_SCOPING_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'Program Scoping enabled' : 'Program Scoping disabled')
                                    ->success()
                                    ->send();
                            }),
                    ]),

                Forms\Components\Section::make('Mentorship Creation Methods')
                    ->description('Turn a method off to disable its button on the Mentorships page (shown greyed out with a tooltip) and block the page directly. Anyone already partway through a guided setup can still finish it.')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->schema([
                        Forms\Components\Toggle::make('new_mentorship_button_enabled')
                            ->label('"New Mentorship" button')
                            ->helperText('The single-step create form.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? '"New Mentorship" enabled' : '"New Mentorship" disabled')
                                    ->success()
                                    ->send();
                            }),
                        Forms\Components\Toggle::make('guided_setup_button_enabled')
                            ->label('"New Mentorship Guided Setup" button')
                            ->helperText('The step-by-step wizard.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::GUIDED_SETUP_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'Guided Setup enabled' : 'Guided Setup disabled')
                                    ->success()
                                    ->send();
                            }),
                        Forms\Components\Toggle::make('chat_setup_button_enabled')
                            ->label('"Chat Setup" button')
                            ->helperText('The conversational assistant.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::CHAT_SETUP_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'Chat Setup enabled' : 'Chat Setup disabled')
                                    ->success()
                                    ->send();
                            }),
                        Forms\Components\Toggle::make('mnchgpt_button_enabled')
                            ->label('"MNCHGPT" button')
                            ->helperText('The free-text, LLM-powered assistant.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::MNCHGPT_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'MNCHGPT enabled' : 'MNCHGPT disabled')
                                    ->success()
                                    ->send();
                            }),
                        Forms\Components\Toggle::make('quick_setup_button_enabled')
                            ->label('"Quick Setup" button')
                            ->helperText('The single-page, all-in-one form.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'Quick Setup enabled' : 'Quick Setup disabled')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->columns(5),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Program::query())
            ->heading('Program Activation')
            ->description('Turn a program off to hide it from the mentorship creation flow for most roles. It still appears in the picker, greyed out and marked "Not Active", instead of disappearing outright.')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Program')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Program $record): ?string => $record->description
                        ? str($record->description)->limit(60)->toString()
                        : null),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip(fn (Program $record): string => $record->is_active ? 'Active — visible to all' : 'Deactivated')
                    ->afterStateUpdated(function (Program $record, bool $state): void {
                        Notification::make()
                            ->title($state ? 'Program activated' : 'Program deactivated')
                            ->success()
                            ->send();
                    }),

                Tables\Columns\TextColumn::make('visible_to_roles')
                    ->label('Still visible when off')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => \App\Filament\Resources\ProgramResource::roleOptions()[$state] ?? $state)
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
