<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Custom rate limiters for resource system
        $this->configureResourceRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure rate limiting for resource-related routes
     */
    protected function configureResourceRateLimiting(): void
    {
        // Downloads - 10 per minute per user/IP
        RateLimiter::for('downloads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many download attempts. Please try again later.'
                    ], 429);
                });
        });

        // Previews - 20 per minute per user/IP
        RateLimiter::for('previews', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many preview requests. Please try again later.'
                    ], 429);
                });
        });

        // User interactions (like, bookmark, etc.) - 30 per minute
        RateLimiter::for('interactions', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many interaction requests. Please slow down.'
                    ], 429);
                });
        });

        // Comments from authenticated users - 10 per minute
        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many comments. Please wait before commenting again.'
                    ], 429);
                });
        });

        // Guest comments - 3 per minute (stricter)
        RateLimiter::for('guest-comments', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many comments from this IP. Please wait before commenting again.'
                    ], 429);
                });
        });

        // Comment updates - 5 per minute
        RateLimiter::for('comment-updates', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many comment updates. Please wait before editing again.'
                    ], 429);
                });
        });

        // API endpoints - 100 per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Too many API requests. Please reduce your request rate.'
                    ], 429);
                });
        });

        // Search requests - 60 per minute
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // File uploads (if you add upload functionality) - 20 per hour
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id ?: $request->ip());
        });

        // Admin actions - 200 per minute (higher limit for admin users)
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip());
        });
    }
}