<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AccountVerificationMail;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MainCadre;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Public (unauthenticated) self-registration for the mobile app.
 * Mirrors the web form in App\Livewire\Auth\CustomRegister: the user is
 * created as "pending" with a random password and must set their own via
 * the emailed verification link.
 */
class RegisterController extends Controller
{
    /** Cadres offered at sign-up: active ones of assessment type 2 only. */
    private const CADRE_ASSESSMENT_TYPE_ID = 2;

    public function cadres(): JsonResponse
    {
        $cadres = MainCadre::where('is_active', true)
            ->where('assessment_type_id', self::CADRE_ASSESSMENT_TYPE_ID)
            ->orderBy('order')
            ->get(['id', 'name']);

        return response()->json(['data' => $cadres]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(['data' => Department::orderBy('name')->get(['id', 'name'])]);
    }

    public function counties(): JsonResponse
    {
        return response()->json(['data' => County::orderBy('name')->get(['id', 'name'])]);
    }

    public function facilitiesByCounty(County $county): JsonResponse
    {
        $facilities = Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $county->id))
            ->orderBy('name')
            ->get(['id', 'name', 'mfl_code'])
            ->map(fn (Facility $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'mfl_code' => $f->mfl_code,
                'label' => trim(($f->mfl_code ? "{$f->mfl_code} - " : '').$f->name),
            ])
            ->values();

        return response()->json(['data' => $facilities]);
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'max:255']]);

        return response()->json(['exists' => User::where('email', $data['email'])->exists()]);
    }

    public function checkPhone(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:50']]);

        return response()->json(['exists' => User::where('phone', $data['phone'])->exists()]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:users,phone'],
            'cadre_id' => ['required', 'integer', Rule::exists((new MainCadre)->getTable(), 'id')
                ->where('is_active', true)
                ->where('assessment_type_id', self::CADRE_ASSESSMENT_TYPE_ID)],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'role' => ['required', 'in:mentee,facility_mentor'],
            'county_id' => ['required', 'integer', 'exists:counties,id'],
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityInCounty = Facility::whereKey($data['facility_id'])
            ->whereHas('subcounty', fn ($q) => $q->where('county_id', $data['county_id']))
            ->exists();

        if (! $facilityInCounty) {
            return response()->json([
                'message' => 'The selected facility does not belong to the selected county.',
                'errors' => ['facility_id' => ['The selected facility does not belong to the selected county.']],
            ], 422);
        }

        $first = ucfirst(strtolower(trim($data['first_name'])));
        $middle = filled($data['middle_name'] ?? null) ? ucfirst(strtolower(trim($data['middle_name']))) : null;
        $last = ucfirst(strtolower(trim($data['last_name'])));

        $user = DB::transaction(function () use ($data, $first, $middle, $last) {
            $user = User::create([
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'name' => trim(collect([$first, $middle, $last])->filter()->implode(' ')),
                'email' => $data['email'],
                'phone' => $data['phone'],
                'cadre_id' => $data['cadre_id'],
                'department_id' => $data['department_id'],
                'facility_id' => $data['facility_id'],
                'password' => bcrypt(Str::random(32)),
                'status' => 'pending',
            ]);

            $user->assignRole($data['role']);
            $user->counties()->sync([$data['county_id']]);
            $user->facilities()->sync([$data['facility_id']]);

            return $user;
        });

        Mail::to($user->email)->send(new AccountVerificationMail($user->load('roles')));

        return response()->json([
            'message' => 'Registration successful. Check your email for a link to set your password.',
        ], 201);
    }
}
