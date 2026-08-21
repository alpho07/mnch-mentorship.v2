<?php

namespace App\Filament\Pages;

use App\Models\LoginLog;
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

    public Collection $recentLogins;

    public Collection $topPages;

    public Collection $onlineUsers;

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
    }

    public function refreshOnline(): void
    {
        $onlineUsers = User::where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->with('roles')
            ->limit(50)
            ->get();

        $latestVisitIds = PageVisit::whereIn('user_id', $onlineUsers->pluck('id'))
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');

        $latestVisitsByUser = PageVisit::whereIn('id', $latestVisitIds)->get()->keyBy('user_id');

        $onlineUsers->each(
            fn (User $user) => $user->setRelation('currentPageVisit', $latestVisitsByUser->get($user->id))
        );

        $this->onlineUsers = $onlineUsers;
    }

    private function loadData(): void
    {
        $start = $this->rangeStart();

        $this->recentLogins = LoginLog::with('user')
            ->where('logged_in_at', '>=', $start)
            ->orderByDesc('logged_in_at')
            ->limit(20)
            ->get();

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
            'recentLogins' => $this->recentLogins,
            'topPages' => $this->topPages,
            'onlineUsers' => $this->onlineUsers,
        ];
    }
}

