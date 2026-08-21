<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->diffInSeconds(now()) >= 60)) {
            User::where('id', $user->id)->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}

