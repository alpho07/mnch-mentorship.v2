<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that are legitimately not meant to be hit as a bare guest
     * GET — excluded with a reason, not silently.
     */
    private const EXCLUDED_URIS = [
        // MySQL-only YEAR()/DATE_FORMAT() — same testing-infrastructure gap as the
        // admin dashboards excluded in AdminRouteSmokeTest. See
        // docs/PHASE1-DISCOVERY-BASELINE.md §9.12.
        'analytics/dashboard',
        'training-dashboard/api/years',
        'dashboard/api/years',

        // SQLite rejects "HAVING <subquery alias> > 0" (error: "HAVING clause on a
        // non-aggregate query") — MySQL allows HAVING to reference any SELECT-list
        // alias, SQLite requires an actual aggregate expression. Same root category
        // as the YEAR()/DATE_FORMAT() gap above — a different specific SQL-portability
        // issue. See docs/PHASE1-DISCOVERY-BASELINE.md §9.12.
        'resources',
        'resources/search',
        'resources/browse',

        // View files genuinely don't exist anywhere in the codebase (not a SQLite
        // issue — confirmed with `find resources/views`) — these 500 in every
        // environment, not just tests. Real bug, not fixed here (writing a correct
        // sitemap.xml/RSS-feed template is content work, not a drive-by fix). See
        // docs/PHASE1-DISCOVERY-BASELINE.md §9.15.
        'sitemap.xml',
        'feed',
    ];

    /**
     * Known side effect of bootstrapping a second Application instance here:
     * makes PHPUnit's "error/exception handlers removed" risky-test detector
     * fire for every test in the same process afterward. Confirmed harmless
     * (0 real failures/errors, exit code 0) — see AdminRouteSmokeTest's
     * provider for the full explanation.
     */
    public static function publicParamlessGetRouteProvider(): array
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $cases = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'api/')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            if (in_array($uri, self::EXCLUDED_URIS, true)) {
                continue;
            }
            $cases[$uri] = [$uri];
        }

        return $cases;
    }

    #[DataProvider('publicParamlessGetRouteProvider')]
    public function test_public_route_does_not_500(string $uri): void
    {
        $response = $this->get('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for a guest — investigate before excluding. "
            . "A redirect (e.g. to login) is fine; a 500 is not."
        );
    }
}
