<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityController extends Controller {

    public function index(Request $request): JsonResponse {
        $query = Facility::with('subcounty.county')
                ->where('is_active', true)
                ->orderBy('name');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn($q) =>
                            $q->where('name', 'like', "%{$term}%")
                            ->orWhere('mfl_code', 'like', "%{$term}%")
            );
        }

        if ($request->filled('county_id')) {
            $query->whereHas('subcounty', fn($q) =>
                    $q->where('county_id', $request->county_id)
            );
        }

        $facilities = $query->paginate($request->input('per_page', 50));

        return response()->json([
                    'data' => $facilities->items() ? collect($facilities->items())->map(fn($f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'mfl_code' => $f->mfl_code,
                        'label' => ($f->mfl_code ? "{$f->mfl_code} - " : "") . $f->name,
                        'level' => $f->level,
                        'county' => $f->subcounty->county->name ?? null,
                        'subcounty' => $f->subcounty->name ?? null,
                            ])->values() : [],
                    'meta' => [
                        'total' => $facilities->total(),
                        'current_page' => $facilities->currentPage(),
                        'last_page' => $facilities->lastPage(),
                    ],
        ]);
    }

    public function show(Facility $facility): JsonResponse {
        $facility->load('subcounty.county');
        return response()->json([
                    'data' => [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'mfl_code' => $facility->mfl_code,
                        'label' => ($facility->mfl_code ? "{$facility->mfl_code} - " : "") . $facility->name,
                        'level' => $facility->level,
                        'ownership' => $facility->ownership,
                        'county' => $facility->subcounty->county->name ?? null,
                        'subcounty' => $facility->subcounty->name ?? null,
                        'phone' => $facility->phone,
                        'email' => $facility->email,
                    ],
        ]);
    }

    public function byCounty(int $countyId): JsonResponse {
        $facilities = Facility::with('subcounty')
                ->where('is_active', true)
                ->whereHas('subcounty', fn($q) => $q->where('county_id', $countyId))
                ->orderBy('name')
                ->get(['id', 'name', 'mfl_code', 'level']);

        return response()->json([
                    'data' => $facilities->map(fn($f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'mfl_code' => $f->mfl_code,
                        'label' => ($f->mfl_code ? "{$f->mfl_code} - " : "") . $f->name,
                        'level' => $f->level,
                            ])->values(),
        ]);
    }
}
