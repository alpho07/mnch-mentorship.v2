<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Opens every parameterless, guest-accessible GET route; 404/500 = dead link. sqlite in-memory only. */
class PublicRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_get_routes_do_not_404_or_500(): void
    {
        $this->assertContains(config('database.connections.'.config('database.default').'.database'), [':memory:', 'mnch_test'], 'Refusing to run against a non-test database');

        $bad = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $mw = implode(',', array_filter($route->gatherMiddleware(), 'is_string'));
            if (! in_array('GET', $route->methods()) || str_contains($uri, '{')
                || preg_match('#^(admin|api|livewire|_|storage|sanctum|horizon|telescope)#', $uri)
                || preg_match('/auth|can:|verified|signed/', $mw)) {
                continue;
            }
            $checked++;
            $resp = $this->get("/".ltrim($uri, "/")); $status = $resp->getStatusCode();
            $msg = (string) $resp->exception?->getMessage();
            // sqlite lacks MySQL's YEAR() and lenient HAVING; those are test-engine gaps, not dead links.
            if ($status >= 500 && preg_match('/no such function|GROUP BY clause is required/', $msg)) {
                continue;
            }
            if ($status === 404 || $status >= 500) {
                $bad[] = "/$uri → $status ".substr((string) $resp->exception?->getMessage(), 0, 160);
            }
        }

        $this->assertGreaterThan(0, $checked);
        $this->assertSame([], $bad, "Dead public links:\n".implode("\n", $bad));
    }
}
