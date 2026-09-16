<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles CORS for the mobile assessment app.
 *
 * Laravel's built-in HandleCors middleware only fires when an Origin header
 * is present. Capacitor Android on a real device (WiFi debug or production)
 * often sends requests with no Origin header at all — so HandleCors silently
 * skips them and no Access-Control-Allow-Origin is returned.
 *
 * This middleware runs on all /api/v1/* routes and unconditionally adds the
 * correct CORS headers, covering every case:
 *   - Capacitor Android real device (no Origin header) 
 *   - Capacitor Android emulator  (Origin: https://localhost)
 *   - Capacitor iOS               (Origin: capacitor://localhost)
 *   - Browser / Vite dev server   (Origin: http://localhost:5173)
 */
class MobileApiCors {

    /** Origins we explicitly allow. Null entry handles the no-Origin case. */
    private const ALLOWED_ORIGINS = [
        'https://localhost',
        'http://localhost',
        'http://localhost:5173',
        'http://localhost:5174',
        'capacitor://localhost',
        'ionic://localhost',
        'https://mnchkenyamentorship.org',
        'https://www.mnchkenyamentorship.org',
        'https://mnchkenyamentorship.netlify.app'
    ];

    public function handle(Request $request, Closure $next): Response {
        // ── Respond to preflight immediately — never hits the controller ──────
        if ($request->isMethod('OPTIONS')) {
            return $this->buildPreflightResponse($request);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildPreflightResponse(Request $request): Response {
        $response = response('', 204);
        $this->addCorsHeaders($request, $response);
        return $response;
    }

    private function addCorsHeaders(Request $request, Response $response): Response {
        $origin = $request->headers->get('Origin', '');

        // If the request comes from a known browser origin, echo it back.
        // If there's no Origin header (real Capacitor device), use wildcard —
        // but only when credentials are NOT needed. Since we use Bearer tokens
        // (not cookies), wildcard + no credentials is safe and works everywhere.
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            // No Origin header (native device) or unknown origin.
            // Wildcard is fine here — auth is via Bearer token, not cookies.
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );
        $response->headers->set(
                'Access-Control-Allow-Headers',
                'Authorization, Content-Type, Accept, X-Requested-With, X-XSRF-TOKEN'
        );
        $response->headers->set('Access-Control-Max-Age', '86400');

        // Vary header so CDN/proxy caches store separate copies per origin
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
