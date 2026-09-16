<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ResourceCategory;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display all categories
     */
    public function index(): View
    {
        $user = auth()->user();
        
        $categories = ResourceCategory::active()
            ->parent()
            ->with(['children' => function($q) use ($user) {
                $q->active()->withCount(['resources' => function($rq) use ($user) {
                    $rq->published()->accessibleTo($user);
                }]);
            }])
            ->withCount(['resources' => function($rq) use ($user) {
                $rq->published()->accessibleTo($user);
            }])
            ->orderBy('sort_order')
            ->get();

        return view('frontend.categories.index', compact('categories'));
    }

    /**
     * Display resources in a specific category - Enhanced access control
     */
    public function show(ResourceCategory $category, Request $request): View
    {
        // Check if category is active
        if (!$category->is_active) {
            abort(404);
        }

        $query = Resource::published()
            ->byCategory($category->id)
            ->with(['resourceType', 'category', 'author', 'tags']);

        // Apply visibility filter
        $user = auth()->user();
        $query->accessibleTo($user);

        // Apply additional filters
        if ($request->filled('type')) {
            $query->whereHas('resourceType', fn($q) => $q->where('slug', $request->type));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortBy = $request->get('sort', 'latest');
        match ($sortBy) {
            'popular' => $query->popular(),
            'oldest' => $query->oldest('published_at'),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('view_count'),
            'downloads' => $query->orderByDesc('download_count'),
            default => $query->latest('published_at'),
        };

        $resources = $query->paginate(12)->withQueryString();

        // Load category relationships
        $category->load(['children' => fn($q) => $q->active(), 'parent']);

        return view('frontend.categories.show', compact('category', 'resources'));
    }
}