<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountVerificationController extends Controller {

    // ─────────────────────────────────────────────────────────────────────────
    // GET /account/verify/{user}?signature=...
    // Show the set-password page. Validates the signed URL first.
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request, User $user) {


        if ($user->email_verified_at) {
            return redirect(config('filament.path', '/admin'))
                            ->with('info', 'Your account is already verified. Please log in.');
        }

        $user->loadMissing('roles');
        $roleName = $user->roles->first()?->name ?? 'User';

        return view('auth.account-verify', compact('user', 'roleName'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /account/verify/{user}
    // Validate password, update user, mark verified, auto-login, redirect.
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, User $user) {
        //if (!$request->hasValidSignature()) {
          //  abort(403, 'This verification link is invalid or has expired.');
       // }

        $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/', // at least one uppercase
                'regex:/[0-9]/', // at least one digit
            ],
                ], [
            'password.regex' => 'Password must contain at least one uppercase letter and one number.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status' => 'active',
        ]);

        // Auto-login the user
        Auth::login($user);

        return redirect(config('filament.path', '/admin'))
                        ->with('success', 'Welcome to the MNCH Mentorship Platform! Your account has been verified.');
    }
}
