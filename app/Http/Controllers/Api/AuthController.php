<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    /**
     * POST /api/v1/auth/login
     *
     * Returns a Sanctum token + user object.
     * The mobile app stores the token in secure storage and sends it
     * as "Authorization: Bearer {token}" on all subsequent requests.
     */
    public function login(LoginRequest $request): JsonResponse {
        $user = User::with(['facility.subcounty.county', 'roles'])
                ->where('email', $request->email)
                ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                        'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                        'message' => 'Your account has been deactivated. Contact your administrator.',
                            ], 403);
        }

        // Revoke previous mobile tokens for this device if device_name is sent
        if ($request->filled('device_name')) {
            $user->tokens()
                    ->where('name', $request->device_name)
                    ->delete();
        }

        $tokenName = $request->input('device_name', 'mobile-app');
        $abilities = $this->resolveAbilities($user);
        $token = $user->createToken($tokenName, $abilities);

        return response()->json([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/v1/auth/logout-all
     * Revokes ALL tokens for the user (sign out from all devices).
     */
    public function logoutAll(Request $request): JsonResponse {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    /**
     * GET /api/v1/auth/me
     * Returns the current authenticated user.
     */
    public function me(Request $request): JsonResponse {
        $user = $request->user()->load(['facility.subcounty.county', 'roles']);

        return response()->json([
                    'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     * Rotates the current token (revoke + issue new one).
     */
    public function refresh(Request $request): JsonResponse {
        $user = $request->user();
        $tokenName = $request->user()->currentAccessToken()->name;
        $abilities = $this->resolveAbilities($user);

        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken($tokenName, $abilities);

        return response()->json([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
                    'message' => $status === Password::RESET_LINK_SENT ? 'Password reset link sent to your email.' : 'Unable to send reset link. Please check the email address.',
                        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    $user->tokens()->delete(); // revoke all tokens on reset
                }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Password reset successfully. Please login.']);
    }

    /**
     * Map user roles → Sanctum token abilities.
     */
    private function resolveAbilities(User $user): array {
        $roleAbilities = [
            'super_admin' => ['*'],
            'admin' => ['assessments:*', 'sections:read', 'reports:read', 'profile:*'],
            'division_staff' => ['assessments:read', 'sections:read', 'reports:read', 'profile:*'],
            'mentor' => ['assessments:*', 'sections:read', 'reports:read', 'profile:*'],
            'mentee' => ['sections:read', 'profile:read'],
        ];

        $roles = $user->getRoleNames()->toArray();

        foreach ($roleAbilities as $role => $abilities) {
            if (in_array($role, $roles)) {
                return $abilities;
            }
        }

        return ['sections:read', 'profile:read'];
    }
}
