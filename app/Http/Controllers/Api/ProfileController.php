<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller {

    /**
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse {
        $user = $request->user()->load(['facility.subcounty.county', 'roles', 'cadre', 'department']);

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * PUT /api/v1/profile
     */
    public function update(Request $request): JsonResponse {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'county' => 'sometimes|nullable|string|max:100',
            'facility_id' => 'sometimes|nullable|exists:facilities,id',
        ]);

        $user->update($validated);

        return response()->json([
                    'message' => 'Profile updated successfully.',
                    'user' => new UserResource($user->fresh(['facility.subcounty.county', 'roles'])),
        ]);
    }

    /**
     * PUT /api/v1/profile/password
     */
    public function changePassword(Request $request): JsonResponse {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json([
                        'message' => 'Current password is incorrect.',
                        'errors' => ['current_password' => ['The current password is incorrect.']],
                            ], 422);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all other tokens so other devices must re-login
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $request->user()->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * POST /api/v1/profile/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['avatar' => $path]);

        return response()->json([
                    'message' => 'Avatar updated.',
                    'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * GET /api/v1/profile/stats
     *
     * Assessment statistics for the profile screen.
     */
    public function stats(Request $request): JsonResponse {
        $userId = $request->user()->id;

        $assessments = Assessment::where('assessor_id', $userId)->get();
        $completed = $assessments->where('status', 'completed');

        $avgScore = $completed->count() ? round($completed->avg('overall_percentage'), 1) : 0;

        $gradeDistribution = [
            'green' => $completed->where('overall_grade', 'green')->count(),
            'yellow' => $completed->where('overall_grade', 'yellow')->count(),
            'red' => $completed->where('overall_grade', 'red')->count(),
        ];

        return response()->json([
                    'total_assessments' => $assessments->count(),
                    'completed' => $completed->count(),
                    'in_progress' => $assessments->where('status', 'in_progress')->count(),
                    'average_score' => $avgScore,
                    'grade_distribution' => $gradeDistribution,
        ]);
    }
}
