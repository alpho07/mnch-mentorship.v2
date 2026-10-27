<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Services\Site\ResourceFilters;
use App\Services\Site\SiteDataSource;
use App\Services\Site\SiteChrome;
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

    public static function makePresenter(): SitePresenter
    {
        return (new self(app(SiteDataSource::class)))->presenter();
    }

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
        return SiteChrome::data($page);
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
