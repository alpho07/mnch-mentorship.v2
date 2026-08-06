<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that are legitimately not meant to be hit as a bare GET —
     * excluded with a reason, not silently.
     */
    private const EXCLUDED_URIS = [];

    public static function apiParamlessGetRouteProvider(): array
    {
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $cases = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1')) {
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

    #[DataProvider('apiParamlessGetRouteProvider')]
    public function test_api_route_does_not_500_when_authenticated(string $uri): void
    {
        $user = User::factory()->create(['name' => 'Smoke Test API User', 'status' => 'active']);
        $token = $user->createToken('smoke-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for an authenticated API user — investigate."
        );
    }

    #[DataProvider('apiParamlessGetRouteProvider')]
    public function test_api_route_does_not_500_when_unauthenticated(string $uri): void
    {
        $response = $this->getJson('/' . $uri);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET /{$uri} returned a {$response->getStatusCode()} for a guest — expect 200 (public) or 401 "
            . "(protected), never a 500."
        );
    }
}
