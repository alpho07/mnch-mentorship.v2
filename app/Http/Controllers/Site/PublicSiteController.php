<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Services\Site\ResourceFilters;
use App\Services\Site\SiteDataSource;
use App\Services\Site\SitePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The three redesigned public pages: home, /resources and /categories.
 * Routes: routes/site-redesign.php. Data: SiteDataSource (bind it in AppServiceProvider).
 */
class PublicSiteController extends Controller
{
    public function __construct(private readonly SiteDataSource $data) {}

    private function presenter(): SitePresenter
    {
        return new SitePresenter($this->data, [
            'home' => route('home'),
            'resources' => route('resources.index'),
            'categories' => route('categories.index'),
            'resource' => url('/resources'),              // detail page: /resources/{slug} (existing route)
            'dashboard' => url('/analytics/dashboard'),
            'asset' => asset(''),
        ], resource_path('data/kenya-counties.json'));
    }

    private function shared(string $page): array
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

    public function home()
    {
        $vm = Cache::remember('site:home', now()->addMinutes(10), fn () => $this->presenter()->home(now()));

        return view('site.home', $vm + $this->shared('home'));
    }

    public function resources(Request $request)
    {
        $filters = ResourceFilters::fromRequest($request);

        return view('site.resources', $this->presenter()->resources($filters, now()) + $this->shared('resources'));
    }

    public function categories()
    {
        $vm = Cache::remember('site:categories', now()->addMinutes(10), fn () => $this->presenter()->categories());

        return view('site.categories', $vm + $this->shared('categories'));
    }
}
