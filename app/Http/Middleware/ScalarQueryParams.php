<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Public resource pages echo these query parameters back into the page
 * (search box, filter chips). A request like `?q[]=x` — typically a
 * scanner — turns them into arrays and crashes the view with
 * "htmlspecialchars(): Argument #1 must be of type string, array given".
 * Anything that should be a plain string but isn't is dropped.
 */
class ScalarQueryParams
{
    private const KEYS = ['q', 'search', 'sort', 'category', 'subcategory', 'type', 'difficulty', 'tag'];

    public function handle(Request $request, Closure $next)
    {
        foreach (self::KEYS as $key) {
            if (is_array($request->query($key))) {
                $request->query->remove($key);
                $request->request->remove($key);
            }
        }

        return $next($request);
    }
}
