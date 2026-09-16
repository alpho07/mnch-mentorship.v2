<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cadre;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function programs(): JsonResponse
    {
        $programs = Program::withCount('topLevelModules as module_count')->orderBy('name')->get(['id', 'name', 'description']);

        return response()->json(['data' => $programs]);
    }

    public function programModules(Program $program): JsonResponse
    {
        $modules = ProgramModule::where('program_id', $program->id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['moduleSessions as session_count' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('order_sequence')
            ->get()
            ->map(fn (ProgramModule $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'order_sequence' => $m->order_sequence,
                'session_count' => (int) $m->session_count,
            ])
            ->values();

        return response()->json(['data' => $modules]);
    }

    public function counties(): JsonResponse
    {
        $counties = County::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $counties]);
    }

    public function cadres(): JsonResponse
    {
        // Match web behavior: use Cadre model source (assessment_cadres table in this project).
        $cadres = Cadre::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();

        return response()->json(['data' => $cadres]);
    }

    public function departments(): JsonResponse
    {
        $departments = Department::orderBy('name')->get(['id', 'name'])->values();

        return response()->json(['data' => $departments]);
    }

    public function facilitiesByCounty(County $county): JsonResponse
    {
        $facilities = Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $county->id))
            ->orderBy('name')
            ->get(['id', 'name', 'mfl_code', 'subcounty_id'])
            ->map(fn (Facility $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'mfl_code' => $f->mfl_code,
                'label' => trim(($f->mfl_code ? "{$f->mfl_code} - " : '').$f->name),
            ])
            ->values();

        return response()->json(['data' => $facilities]);
    }

    public function userSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:50',
            'facility_id' => 'nullable|integer|exists:facilities,id',
        ]);

        $term = $request->q;
        $limit = (int) $request->input('per_page', 20);

        $query = User::query()
            ->where('status', 'active')
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($term) {
                        $facilityQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('mfl_code', 'like', "%{$term}%");
                    });
            })
            // Scoped the same way as the rest of the app (see
            // User::scopedFacilityIds() / CLAUDE.md) — the facility_user
            // pivot, not the single home facility_id column.
            ->when($request->filled('facility_id'), function ($q) use ($request) {
                $q->whereHas('facilities', fn ($facilityQuery) => $facilityQuery->where('facilities.id', $request->facility_id));
            })
            ->with('facility')
            ->orderBy('first_name')
            ->limit($limit);

        $users = $query->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->full_name ?? $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'facility_id' => $u->facility_id,
            'facility_name' => $u->facility?->name ?? '',
            'mfl_code' => $u->facility?->mfl_code,
        ]);

        return response()->json(['data' => $users]);
    }

    public function userByEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::with('facility')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])
            ->first();

        if (! $user) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->full_name ?? $user->name,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'cadre_id' => $user->cadre_id,
                'department_id' => $user->department_id,
                'facility_id' => $user->facility_id,
                'facility_name' => $user->facility?->name ?? '',
                'mfl_code' => $user->facility?->mfl_code,
            ],
        ]);
    }

    /**
     * GET /api/v1/users/lookup-index
     *
     * Returns a slim index of active users for offline email lookups.
     * Supports ?since= for delta updates (includes inactive users in delta so app can remove them).
     *
     * Super admin / above-site roles: all users.
     * Others: scoped to users sharing the same facility.
     */
    public function userLookupIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('since')) {
            // Delta: return all users (active or inactive) updated after the given time
            $since = \Carbon\Carbon::parse($request->since);

            $query = User::query()->where('updated_at', '>', $since);

            if (! $user->hasRole('super_admin') && ! $user->isAboveSite()) {
                $facilityId = $user->facility_id;
                if ($facilityId) {
                    $query->where('facility_id', $facilityId);
                } else {
                    $query->whereRaw('1 = 0'); // no facility assigned → no results
                }
            }

            $users = $query->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'status'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->full_name ?? $u->name,
                    'email' => strtolower(trim($u->email ?? '')),
                    'phone' => $u->phone,
                    'is_active' => $u->status === 'active',
                ]);
        } else {
            // Full index: active users only
            $query = User::query()->where('status', 'active');

            if (! $user->hasRole('super_admin') && ! $user->isAboveSite()) {
                $facilityId = $user->facility_id;
                if ($facilityId) {
                    $query->where('facility_id', $facilityId);
                } else {
                    $query->whereRaw('1 = 0'); // no facility assigned → no results
                }
            }

            $users = $query->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'status'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->full_name ?? $u->name,
                    'email' => strtolower(trim($u->email ?? '')),
                    'phone' => $u->phone,
                    'is_active' => true,
                ]);
        }

        return response()->json([
            'data' => $users,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'total' => $users->count(),
            ],
        ]);
    }
}
