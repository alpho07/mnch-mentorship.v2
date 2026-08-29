<?php

namespace App\Filament\Pages;

use App\Support\NotificationEvents;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Per-user notification preferences ("My Notifications"). Every user manages
 * only their own opt-outs; senders consult these via
 * User::wantsNotification() before touching each channel. Everything is on
 * by default — the form simply makes the defaults explicit.
 */
class NotificationPreferences extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'My Notifications';

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.notification-preferences';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->notificationChannelMap());
    }

    public function form(Form $form): Form
    {
        $sections = [];

        foreach (NotificationEvents::GROUPS as $group => $events) {
            $fields = [];

            foreach ($events as $event => $meta) {
                $fields[] = Section::make($meta['label'])
                    ->description($meta['description'])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Grid::make(1)->schema([
                            Toggle::make("{$event}.mail")
                                ->label('Email me')
                                ->onColor('success'),
                        ]),
                        Grid::make(1)->schema([
                            Toggle::make("{$event}.database")
                                ->label('Show in-app bell notification')
                                ->onColor('success'),
                        ]),
                    ]);
            }

            $sections[] = Section::make($group)
                ->icon('heroicon-o-bell-alert')
                ->columns(2)
                ->schema($fields);
        }

        return $form
            ->statePath('data')
            ->schema($sections);
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Only persist known events; unknown keys are dropped so stale
        // entries never linger after an event is retired from the catalog.
        $channels = [];
        foreach (NotificationEvents::all() as $event => $meta) {
            foreach ([NotificationEvents::CHANNEL_MAIL, NotificationEvents::CHANNEL_DATABASE] as $channel) {
                $channels[$event][$channel] = (bool) data_get($state, "{$event}.{$channel}", true);
            }
        }

        auth()->user()->saveNotificationChannels($channels);

        Notification::make()
            ->title('Notification preferences saved')
            ->success()
            ->send();
    }
}
