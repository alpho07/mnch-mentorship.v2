<?php

namespace App\Services\Site;

use App\Models\ResourceCategory;
use Illuminate\Support\Facades\Cache;

/** Data the shared public layout (site.layout) needs for its header and footer, for any page that extends it. */
class SiteChrome
{
    public static function data(string $page = 'resources'): array
    {
        return [
            'page' => $page,
            'img' => rtrim(asset('images/site'), '/'),
            'year' => now()->year,
            'dashboardUrl' => url('/analytics/dashboard'),
            'loginUrl' => url('admin/login'),
            'quickLinks' => [
                ['label' => 'All Resources', 'href' => route('resources.index')],
                ['label' => 'Categories', 'href' => route('categories.index')],
                ['label' => 'Browse', 'href' => route('resources.browse')],
                ['label' => 'Mentorship SOP Guide', 'href' => route('guides.mentorship')],
                ['label' => 'EmONC Mentor & Mentee Guide', 'href' => route('guides.emonc')],
                ['label' => 'User Manual', 'href' => route('manual')],
                ['label' => 'Analytics Map', 'href' => url('analytics/dashboard')],
                ['label' => 'MNCH Mobile App', 'href' => 'https://mnchkenyamentorship.org/MNCH%20Mentorship.apk', 'download' => true, 'badge' => 'Updated'],
            ],
            'topCategories' => Cache::remember('site:top-categories', now()->addMinutes(10), fn () => ResourceCategory::active()->parent()
                ->withCount('resources')->orderByDesc('resources_count')->limit(5)->get()
                ->map(fn ($c) => ['name' => $c->name, 'count' => $c->resources_count, 'href' => route('resources.category', $c->slug)])->all()),
            'social' => [
                ['label' => 'Twitter', 'icon' => 'fab fa-twitter', 'href' => '#'],
                ['label' => 'LinkedIn', 'icon' => 'fab fa-linkedin', 'href' => '#'],
            ],
            'appUrl' => 'https://mnchkenyamentorship.org/MNCH%20Mentorship.apk',
            'contactEmail' => 'mnch@health.go.ke',
            'supportPhone' => '+254 700 000 000',          // same placeholder the old footer showed
        ];
    }
}
