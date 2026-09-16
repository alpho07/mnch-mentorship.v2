<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ResourceService
{
    public function createResource(array $data, ?User $user = null): Resource
    {
        $user = $user ?: auth()->user();
        $data['author_id'] = $user->id;

        $resource = Resource::create($data);

        // Handle tags
        if (isset($data['tag_names'])) {
            $this->syncTags($resource, $data['tag_names']);
        }

        // Log activity
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy($user)
                ->log('Resource created');
        }

        return $resource;
    }

    public function updateResource(Resource $resource, array $data): Resource
    {
        $resource->update($data);

        // Handle tags
        if (isset($data['tag_names'])) {
            $this->syncTags($resource, $data['tag_names']);
        }

        // Log activity
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy(auth()->user())
                ->log('Resource updated');
        }

        return $resource;
    }

    protected function syncTags(Resource $resource, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function ($tagName) {
            return \App\Models\Tag::firstOrCreate(
                ['name' => $tagName],
                ['slug' => \Str::slug($tagName)]
            )->id;
        })->toArray();

        $resource->tags()->sync($tagIds);
    }

    public function deleteResource(Resource $resource): bool
    {
        // Log before deletion
        if (function_exists('activity')) {
            activity()
                ->performedOn($resource)
                ->causedBy(auth()->user())
                ->log('Resource deleted');
        }

        // Delete associated files
        $resource->deleteFiles();

        return $resource->delete();
    }

    public function getResourcesAccessibleTo(?User $user): Collection
    {
        return Resource::accessibleTo($user)
            ->published()
            ->with(['category', 'resourceType', 'author'])
            ->orderByDesc('published_at')
            ->get();
    }
}