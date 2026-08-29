<?php

namespace App\Filament\Pages;

use App\Models\PageVisit;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ActivityDashboard extends Page
{
    protected static string $view = 'filament.pages.activity-dashboard';

    protected static ?string $slug = 'activity';

    protected static ?string $navigationGroup = 'Reports & Analytics';

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Activity';

    public string $range = '7d';

    public Collection $topPages;

    public Collection $onlineUsers;

    public Collection $activeUsers;

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
        $this->loadData();
        $this->refreshOnline();
    }

    public function setRange(string $range): void
    {
        $this->range = $range;
        $this->loadData();
        $this->refreshOnline();
    }

    public function refreshOnline(): void
    {
        $start = now()->subMinutes(5);

        $this->onlineUsers = User::where('last_seen_at', '>=', $start)
            ->orderByDesc('last_seen_at')
            ->with('roles')
            ->limit(50)
            ->get()
            ->each(function (User $user) {
                $recentVisits = PageVisit::where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();

                $user->setRelation('recentPageVisits', $recentVisits);
                $user->setRelation('currentPageVisit', $recentVisits->first());
            });

        $this->loadTopPages();
    }

    private function loadData(): void
    {
        $this->loadTopPages();
        $this->loadActiveUsers();
    }

    private function loadActiveUsers(): void
    {
        $this->activeUsers = User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subDays(7))
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get();
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
            'onlineUsers' => $this->onlineUsers,
            'activeUsers' => $this->activeUsers,
        ];
    }
}
