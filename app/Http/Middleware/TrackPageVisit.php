<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageVisit
{
    private const EXCLUDED_PREFIXES = ['livewire', 'storage', 'css', 'js', 'build', 'app-icons', 'up', '_debugbar'];

    private const EXCLUDED_PATHS = ['sw.js', 'manifest.webmanifest', 'favicon.ico'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request)) {
            PageVisit::create([
                'user_id' => $request->user()?->id,
                'route_name' => optional($request->route())->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
                'created_at' => now(),
            ]);
        }

        return $response;
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        $path = $request->path();

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return ! in_array($path, self::EXCLUDED_PATHS, true);
    }
}

