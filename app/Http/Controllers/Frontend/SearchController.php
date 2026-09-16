<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Display search results - Enhanced with access control
     */
    public function index(Request $request): View
    {
        $searchTerm = trim($request->get('q', ''));
        $resources = collect();

        if (strlen($searchTerm) >= 2) {
            $query = Resource::published()
                ->search($searchTerm)
                ->with(['resourceType', 'category', 'author', 'tags']);

            // Apply visibility filter
            $user = auth()->user();
            $query->accessibleTo($user);

            // Apply additional filters if present
            if ($request->filled('category')) {
                $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
            }

            if ($request->filled('type')) {
                $query->whereHas('resourceType', fn($q) => $q->where('slug', $request->type));
            }

            if ($request->filled('difficulty')) {
                $query->where('difficulty_level', $request->difficulty);
            }

            // Sort results by relevance (view count + like count as proxy)
            $sortBy = $request->get('sort', 'relevance');
            match ($sortBy) {
                'latest' => $query->latest('published_at'),
                'popular' => $query->popular(),
                'title' => $query->orderBy('title'),
                default => $query->orderByRaw('(view_count + like_count) DESC'),
            };

            $resources = $query->paginate(12)->withQueryString();
        }

        // Get suggestions with access control
        $user = auth()->user();
        
        $suggestedCategories = ResourceCategory::active()
            ->where('name', 'like', "%{$searchTerm}%")
            ->withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->having('resources_count', '>', 0)
            ->limit(5)
            ->get();

        $suggestedTags = Tag::where('name', 'like', "%{$searchTerm}%")
            ->withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->having('resources_count', '>', 0)
            ->limit(10)
            ->get();

        // Get filter options for search refinement
        $resourceTypes = ResourceType::active()
            ->withCount(['resources' => function($q) use ($user) {
                $q->published()->accessibleTo($user);
            }])
            ->having('resources_count', '>', 0)
            ->orderBy('name')
            ->get();

        return view('frontend.search.index', compact(
            'searchTerm',
            'resources',
            'suggestedCategories',
            'suggestedTags',
            'resourceTypes'
        ));
    }
}