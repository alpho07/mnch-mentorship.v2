<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\DashboardFilters;
use App\Services\Analytics\DashboardPresenter;
use App\Services\Analytics\MetricsSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * One controller for all five analytics dashboards. Same URL as before:
 *   /analytics/dashboard?mode=mentorship|emonc|mentor|assessment|training
 *
 * Every filter is a query-string parameter (see DashboardFilters), so any view can be bookmarked or shared.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly MetricsSource $metrics) {}

    public function show(Request $request)
    {
        $filters = DashboardFilters::fromRequest($request);

        // Area admins only ever see their own programme area, so send them to the matching screen.
        $area = \App\Support\ProgramArea::forUser($request->user());
        if ($area === 'emonc' && $filters->mode === 'mentorship') {
            return redirect()->route('analytics.dashboard.index', $filters->toQuery(['mode' => 'emonc']));
        }
        if ($area === 'newborn_child' && $filters->mode === 'emonc') {
            return redirect()->route('analytics.dashboard.index', $filters->toQuery(['mode' => 'mentorship']));
        }
        $base = $request->url();

        // Cache each filter combination for 10 minutes. "Data as of" shows when the numbers were computed.
        $vm = Cache::remember($filters->cacheKey().':'.md5($base).':'.($area ?? 'all'), now()->addMinutes(10), function () use ($filters, $base) {
            $presenter = new DashboardPresenter($this->metrics, resource_path('data/kenya-counties.json'));

            return $presenter->present($filters, $base, now()->format('d M Y, H:i'));
        });

        return view('analytics.'.$filters->mode, $vm + [
            'homeUrl' => url('/'),
            'resourcesUrl' => url('/resources'),
            'categoriesUrl' => url('/categories'),
            'adminUrl' => url('/admin'),
            'heroImage' => asset('images/analytics/kenya-hero.svg'),
            // Existing PDF export of the readiness table (same route the old page used).
            'exportUrl' => url('/analytics/dashboard/assessment/export-readiness').'?'.http_build_query(array_filter(['year' => $filters->year, 'county' => $filters->county, 'type' => $filters->assessmentType])),
            // Executive summary of one assessment, e.g. /assessments/111/executive
            'assessmentUrl' => url('/assessments'),
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| routes/web.php — replace the old analytics dashboard route with:
|--------------------------------------------------------------------------
|
| use App\Http\Controllers\Analytics\DashboardController;
|
| Route::get('/analytics/dashboard', [DashboardController::class, 'show'])->name('analytics.dashboard');
|
| The old JSON endpoints (/analytics/dashboard/geojson, /county/{id}/mentorships, /training-data)
| are no longer called by these views. Remove them once nothing else uses them.
|
| app/Providers/AppServiceProvider.php → register():
|
| $this->app->bind(\App\Services\Analytics\MetricsSource::class, \App\Services\Analytics\DatabaseMetrics::class);
*/
