<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InteractionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('throttle:30,1'); // Rate limiting for interactions
    }

    /**
     * Like a resource
     */
    public function like(Resource $resource): JsonResponse
    {
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $result = null;

        DB::transaction(function () use ($resource, &$result) {
            $result = auth()->user()->toggleResourceInteraction($resource, 'like');

            // Remove dislike if exists
            if ($result) {
                auth()->user()->resourceInteractions()
                    ->where('resource_id', $resource->id)
                    ->where('type', 'dislike')
                    ->delete();
            }

            // Update counts
            $resource->updateInteractionCounts();
        });

        $resource->refresh();

        return response()->json([
            'liked' => $result,
            'like_count' => $resource->like_count,
            'dislike_count' => $resource->dislike_count,
        ]);
    }

    /**
     * Dislike a resource
     */
    public function dislike(Resource $resource): JsonResponse
    {
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $result = null;

        DB::transaction(function () use ($resource, &$result) {
            $result = auth()->user()->toggleResourceInteraction($resource, 'dislike');

            // Remove like if exists
            if ($result) {
                auth()->user()->resourceInteractions()
                    ->where('resource_id', $resource->id)
                    ->where('type', 'like')
                    ->delete();
            }

            // Update counts
            $resource->updateInteractionCounts();
        });

        $resource->refresh();

        return response()->json([
            'disliked' => $result,
            'like_count' => $resource->like_count,
            'dislike_count' => $resource->dislike_count,
        ]);
    }

    /**
     * Bookmark a resource
     */
    public function bookmark(Resource $resource): JsonResponse
    {
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $bookmarked = auth()->user()->toggleResourceInteraction($resource, 'bookmark');

        return response()->json([
            'bookmarked' => $bookmarked,
            'message' => $bookmarked ? 'Resource bookmarked!' : 'Bookmark removed!',
        ]);
    }

    /**
     * Optimized toggle method with better error handling
     */
    public function toggle(Resource $resource, string $type): JsonResponse
    {
        // Validate interaction type
        $validTypes = ['like', 'dislike', 'bookmark', 'share'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid interaction type'], 400);
        }

        // Check access
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $user = auth()->user();
        $result = null;

        // Use database transaction for consistency
        DB::transaction(function () use ($user, $resource, $type, &$result) {
            $result = $user->toggleResourceInteraction($resource, $type);

            // Handle mutual exclusivity for like/dislike efficiently
            if ($result && in_array($type, ['like', 'dislike'])) {
                $oppositeType = $type === 'like' ? 'dislike' : 'like';
                $user->resourceInteractions()
                    ->where('resource_id', $resource->id)
                    ->where('type', $oppositeType)
                    ->delete();
            }

            // Update counts only when necessary
            if (in_array($type, ['like', 'dislike'])) {
                $resource->updateInteractionCounts();
            }
        });

        // Refresh counts
        $resource->refresh();

        return response()->json([
            $type . 'd' => $result ?? false,
            'like_count' => $resource->like_count,
            'dislike_count' => $resource->dislike_count,
            'message' => ($result ?? false) ? ucfirst($type) . ' added!' : ucfirst($type) . ' removed!',
        ]);
    }
}
