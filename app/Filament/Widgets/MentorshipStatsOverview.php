<?php

namespace App\Filament\Widgets;

use App\Services\MentorshipStatsService;
use Filament\Widgets\Widget;

class MentorshipStatsOverview extends Widget
{
    protected static string $view = 'filament.widgets.mentorship-stats-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        $service = app(MentorshipStatsService::class);
        $user = auth()->user();
        $programs = $service->programStats($user);

        return [
            'overall' => $service->overallStats($user),
            'programs' => $programs,
        ];
    }
}
