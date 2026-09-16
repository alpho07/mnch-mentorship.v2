<?php

namespace App\Filament\Pages;

use App\Models\LoginLog;
use App\Models\PageVisit;
use App\Models\User;
use App\Services\IpGeolocationService;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.activity-log';

    protected static ?string $slug = 'activity-log';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Activity Log';

    public string $range = '7d';

    public ?int $selectedUserId = null;

    public Collection $topPages;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAboveSite() ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAboveSite() ?? false;
    }

    public function mount(): void
    {
        if ($userId = request()->integer('user')) {
            $this->tableFilters = ['user' => ['value' => $userId]];
        }

        $this->loadTopPages();
    }

    public function setRange(string $range): void
    {
        $this->range = $range;
        $this->loadTopPages();
    }

    public function clearUserSelection(): void
    {
        $this->selectedUserId = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LoginLog::with(['user.roles'])
                    ->where('logged_in_at', '>=', $this->rangeStart())
                    ->orderByDesc('logged_in_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.roles.name')
                    ->label('Role(s)')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_location')
                    ->label('Location')
                    ->state(function (LoginLog $record) {
                        return app(IpGeolocationService::class)->resolve($record->ip_address);
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Device / Browser')
                    ->limit(40)
                    ->tooltip(fn (LoginLog $record) => $record->user_agent),
                Tables\Columns\TextColumn::make('logged_in_at')
                    ->label('Login Time')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_user_activity')
                    ->label('View Activities')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (LoginLog $record) => 'Page Visits — '.($record->user?->name ?? 'Unknown User'))
                    ->modalContent(fn (LoginLog $record) => view('filament.modals.user-page-visits', [
                        'visits' => PageVisit::where('user_id', $record->user_id)
                            ->orderByDesc('id')
                            ->limit(50)
                            ->get(),
                        'userName' => $record->user?->name ?? 'Unknown User',
                    ]))
                    ->modalWidth('4xl')
                    ->visible(fn (LoginLog $record) => $record->user_id !== null),
            ])
            ->headerActions([
                Tables\Actions\Action::make('clear_selection')
                    ->label('← Back to All Logins')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->visible(fn () => $this->selectedUserId !== null)
                    ->action(fn () => $this->clearUserSelection()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->label('User')
                    ->searchable()
                    ->options(fn (): array => $this->userFilterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string|int $userId) => $q->where('login_logs.user_id', $userId),
                        );
                    }),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(fn (): array => $this->roleFilterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $role) => $q->whereHas(
                                'user.roles',
                                fn (Builder $r) => $r->where('name', $role),
                            ),
                        );
                    }),
                Tables\Filters\Filter::make('ip_address')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label('IP Address')
                            ->placeholder('e.g. 197.232.'),
                    ])
                    ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? 'IP: '.$data['value'] : null)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $ip) => $q->where('ip_address', 'like', "{$ip}%"),
                        );
                    }),
            ])
            ->filtersFormMaxHeight(null)
            ->paginated(15);
    }

    public function userFilterOptions(): array
    {
        return $this->logsWithinRange()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy(fn (User $u) => $u->name ?? $u->email ?? '')
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name ?? $u->email])
            ->all();
    }

    public function roleFilterOptions(): array
    {
        return $this->logsWithinRange()
            ->with('user.roles')
            ->get()
            ->pluck('user.roles.*.name')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $role) => [$role => $role])
            ->all();
    }

    private function logsWithinRange(): Builder
    {
        return LoginLog::query()->whereBetween('logged_in_at', [$this->rangeStart(), now()]);
    }

    private function loadTopPages(): void
    {
        $start = $this->rangeStart();

        $this->topPages = PageVisit::selectRaw('route_name, path, count(*) as visits')
            ->where('created_at', '>=', $start)
            ->groupBy('route_name', 'path')
            ->orderByDesc('visits')
            ->limit(20)
            ->get();
    }

    private function rangeStart(): Carbon
    {
        return match ($this->range) {
            'today' => now()->startOfDay(),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };
    }

    protected function getViewData(): array
    {
        return [
            'topPages' => $this->topPages,
            'range' => $this->range,
            'selectedUserId' => $this->selectedUserId,
        ];
    }
}
