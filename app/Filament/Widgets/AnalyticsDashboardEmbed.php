<?php

namespace App\Filament\Widgets;

use App\Models\Subcounty;
use App\Models\Facility;
use Filament\Widgets\Widget;

abstract class AnalyticsDashboardEmbed extends Widget
{
    protected static string $view = 'filament.widgets.analytics-dashboard-embed';
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Only render on resource list pages (not on the Filament dashboard)
        $route = request()->route()?->getName() ?? '';
        return str_contains($route, 'resources.') && str_contains($route, '.index');
    }

    /** Override in each subclass */
    protected static string $analyticsMode = 'training';

    public function getMode(): string
    {
        return static::$analyticsMode;
    }

    public function isActive(): bool
    {
        return request()->get('tab') === 'home';
    }

    public function getIframeUrl(): string
    {
        $user   = auth()->user();
        $params = ['mode' => $this->getMode()];

        if ($user && ! $user->isAboveSite()) {
            if ($user->hasRole('county_mentor_lead')) {
                $countyId = $user->counties()->first()?->id;
                if ($countyId) {
                    $params['county_id'] = $countyId;
                }
            } elseif ($user->hasRole('subcounty_mentor_lead')) {
                $sub = $user->subcounties()->with('county')->first();
                if ($sub) {
                    $params['county_id']    = $sub->county_id;
                    $params['subcounty_id'] = $sub->id;
                }
            } else {
                $facilityId = $user->facility_id ?? $user->facilities()->first()?->id;
                if ($facilityId) {
                    $facility = Facility::with('subcounty')->find($facilityId);
                    if ($facility) {
                        $params['county_id']    = $facility->subcounty?->county_id;
                        $params['subcounty_id'] = $facility->subcounty_id;
                        $params['facility_id']  = $facilityId;
                    }
                }
            }
        }

        return url('/analytics/dashboard') . '?' . http_build_query(array_filter($params));
    }
}
