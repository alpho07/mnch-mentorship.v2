<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without this, a mentee who submits a quiz (redirected back to the module
 * page showing it completed) and then uses the browser's back button can
 * land on a bfcache'd snapshot of the module page from BEFORE they started
 * the quiz — showing "Start Pre-Test" again even though the server's actual
 * state is correct. `no-store` forces the browser to always re-fetch these
 * pages instead of restoring a stale snapshot.
 */
class PreventStalePageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
