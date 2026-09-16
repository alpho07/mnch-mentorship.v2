<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\ResourceComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except('storeGuest');
        $this->middleware('throttle:10,1')->only(['store', 'storeGuest']); // Rate limiting
    }

    /**
     * Store a comment from authenticated user
     */
    public function store(Request $request, Resource $resource): RedirectResponse
    {
        $request->validate([
            'content' => 'required|string|min:3|max:1000',
            'parent_id' => 'nullable|exists:resource_comments,id',
        ]);

        // Enhanced access check
        if (!$resource->canUserAccess(auth()->user()) || $resource->status !== 'published') {
            abort(403, 'Cannot comment on this resource.');
        }

        // Validate parent comment belongs to same resource
        if ($request->parent_id) {
            $parentComment = ResourceComment::find($request->parent_id);
            if (!$parentComment || $parentComment->resource_id !== $resource->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent comment.']);
            }
        }

        $comment = $resource->comments()->create([
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'content' => strip_tags($request->content), // Basic XSS protection
            'is_approved' => true,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Comment added successfully!');
    }

    /**
     * Store a comment from guest user - Enhanced security
     */
    public function storeGuest(Request $request, Resource $resource): RedirectResponse
    {
        $request->validate([
            'author_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
            'author_email' => 'required|email|max:255',
            'content' => 'required|string|min:3|max:1000',
            'parent_id' => 'nullable|exists:resource_comments,id',
            'g-recaptcha-response' => 'required|recaptcha', // Proper reCAPTCHA validation
        ]);

        // Check if resource allows public comments
        if ($resource->visibility !== 'public' || $resource->status !== 'published') {
            abort(403, 'Comments are not allowed for this resource.');
        }

        // Check for spam patterns
        $content = strip_tags($request->content);
        if ($this->isSpam($content, $request->author_email)) {
            return back()->withErrors(['content' => 'Comment appears to be spam.']);
        }

        // Validate parent comment belongs to same resource
        if ($request->parent_id) {
            $parentComment = ResourceComment::find($request->parent_id);
            if (!$parentComment || $parentComment->resource_id !== $resource->id) {
                return back()->withErrors(['parent_id' => 'Invalid parent comment.']);
            }
        }

        $comment = $resource->comments()->create([
            'parent_id' => $request->parent_id,
            'content' => $content,
            'author_name' => strip_tags($request->author_name),
            'author_email' => $request->author_email,
            'is_approved' => false, // Require moderation
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Comment submitted and is pending approval!');
    }

    /**
     * Update a comment - Enhanced validation
     */
    public function update(Request $request, ResourceComment $comment): RedirectResponse
    {
        // Enhanced ownership check
        if ($comment->user_id !== auth()->id() || !$comment->user) {
            abort(403);
        }

        // Check if comment is not too old (e.g., 24 hours)
        if ($comment->created_at->diffInHours(now()) > 24) {
            return back()->withErrors(['content' => 'Comment is too old to edit.']);
        }

        $request->validate([
            'content' => 'required|string|min:3|max:1000',
        ]);

        $comment->update([
            'content' => strip_tags($request->content),
            'updated_at' => now(), // Explicitly set for tracking
        ]);

        return back()->with('success', 'Comment updated successfully!');
    }

    /**
     * Delete a comment
     */
    public function destroy(ResourceComment $comment): RedirectResponse
    {
        // Check if user owns the comment or is admin
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }

        $comment->delete();

        return back()->with('success', 'Comment deleted successfully!');
    }

    /**
     * Basic spam detection
     */
    private function isSpam(string $content, string $email): bool
    {
        // Check for excessive links
        if (substr_count(strtolower($content), 'http') > 2) {
            return true;
        }

        // Check for blacklisted domains
        $blacklistedDomains = ['tempmail.org', '10minutemail.com', 'guerrillamail.com'];
        $emailDomain = substr(strrchr($email, "@"), 1);

        return in_array($emailDomain, $blacklistedDomains);
    }
}
