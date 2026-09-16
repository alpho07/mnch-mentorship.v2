<?php

namespace App\Filament\Pages;

use App\Services\EmoncDashboardService;
use Filament\Pages\Page;

class EmoncDashboard extends Page
{
    protected static string $view = 'filament.pages.emonc-dashboard';

    protected static ?string $slug = 'emonc-dashboard';

    protected static ?string $navigationGroup = 'Mentorships';

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Maternal Health (EmONC)';

    protected static ?int $navigationSort = 4;

    private const SENIOR_ROLES = ['super_admin', 'admin', 'division', 'national'];

    private const MENTOR_ROLES = [
        'facility_mentor', 'facility_mentor_lead',
        'county_mentor_lead', 'subcounty_mentor_lead',
        'spoke_mentor', 'spoke_mentor_lead',
        'national_mentor_lead', 'national_mentor',
        'co_mentor', 'co-mentor', 'head_drmh',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_EmoncDashboard');
    }

    public array $kpis = [];

    public array $completionMatrix = [];

    public array $chartData = [];

    public array $pendingActions = [];

    public function mount(): void
    {
        $data = app(EmoncDashboardService::class)->build(auth()->user());
        $this->kpis = $data['kpis'];
        $this->completionMatrix = $data['completionMatrix'];
        $this->chartData = $data['chartData'];
        $this->pendingActions = $data['pendingActions'];
    }
}
