<?php

namespace App\Models\Concerns;

use App\Models\User;

trait HasResourceAnalytics {

    // Analytics methods
    public function incrementViews(?User $user = null, ?string $ipAddress = null): void {
        $this->increment('view_count');

        $this->views()->create([
            'user_id' => $user?->id,
            'ip_address' => $ipAddress ?: request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function incrementDownloads(?User $user = null): void {
        $this->increment('download_count');

        if ($user) {
            $this->downloads()->create([
                'user_id' => $user->id,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    public function updateInteractionCounts(): void {
        $this->update([
            'like_count' => $this->interactions()->where('type', 'like')->count(),
            'dislike_count' => $this->interactions()->where('type', 'dislike')->count(),
        ]);
    }

    // Computed attributes for analytics
    public function getReadTimeAttribute(): int {
        $wordCount = str_word_count(strip_tags($this->content));
        return ceil($wordCount / 200); // Average reading speed
    }

    public function getInteractionLikeCountAttribute(): int {
        return $this->interactions()->where('type', 'like')->count();
    }

    public function getInteractionDislikeCountAttribute(): int {
        return $this->interactions()->where('type', 'dislike')->count();
    }

    public function getBookmarkCountAttribute(): int {
        return $this->interactions()->where('type', 'bookmark')->count();
    }

    public function getCommentCountAttribute(): int {
        return $this->comments()->approved()->count();
    }

    // Search enhancement
    public function toSearchableArray(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => strip_tags($this->content),
            'category_name' => $this->category?->name,
            'resource_type_name' => $this->resourceType?->name,
            'author_name' => $this->author?->full_name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'difficulty_level' => $this->difficulty_level,
            'file_type' => $this->file_type,
            'is_downloadable' => $this->is_downloadable,
            'published_at' => $this->published_at?->timestamp,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
        ];
    }
}
