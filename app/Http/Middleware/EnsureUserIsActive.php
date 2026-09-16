<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive {

    public function handle(Request $request, Closure $next) {
        if ($request->user() && $request->user()->status === 'inactive') {
            $request->user()->currentAccessToken()?->delete();
            return response()->json([
                        'message' => 'Your account is inactive. Contact your administrator.',
                            ], 403);
        }
        return $next($request);
    }
}
