<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/** Endpoints for the inactivity timer (public/js/idle-timeout.js). */
class SessionActivityController extends Controller
{
    /** "Stay signed in": touching the session is enough, the request itself refreshes it. */
    public function keepAlive(): Response
    {
        return response()->noContent();
    }

    /** Signs the user out after the idle limit and sends them to the front page. */
    public function expire(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/?signed_out=inactive');
    }
}
