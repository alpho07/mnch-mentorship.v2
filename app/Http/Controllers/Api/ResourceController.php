<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    /**
     * GET /api/v1/resources
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $query = \App\Models\Resource::accessibleTo($user)
            ->where('status', 'published')
            ->with('resourceType');

        if ($request->type) {
            $query->byType($request->type);
        }

        $resources = $query->latest('published_at')->get()->map(fn($r) => [
            'id'          => $r->id,
            'title'       => $r->title,
            'description' => $r->excerpt,
            'type'        => $r->resourceType?->slug ?? 'document',
            'url'         => $r->external_url,
            'file_url'    => $r->file_path ? asset('storage/' . $r->file_path) : null,
        ]);

        return response()->json(['data' => $resources]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
