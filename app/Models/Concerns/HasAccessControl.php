<?php

namespace App\Models\Concerns;

use App\Models\User;

trait HasAccessControl {

    // Access control methods
    public function canUserAccess(?User $user): bool {
        // Public resources are always accessible
        if ($this->visibility === 'public') {
            return true;
        }

        // If no user is provided (guest), only public resources are accessible
        if (!$user) {
            return false;
        }

        // Author can always access their own resources
        if ($this->author_id === $user->id) {
            return true;
        }

        // Authenticated users can access authenticated resources
        if ($this->visibility === 'authenticated') {
            return true;
        }

        // Check access groups
        if ($this->accessGroups()->whereHas('users', fn($q) => $q->where('users.id', $user->id))->exists()) {
            return true;
        }

        // Check scoped facilities (assuming User has scopedFacilityIds method)
        if (method_exists($user, 'scopedFacilityIds') &&
                $this->scopedFacilities()->whereIn('facilities.id', $user->scopedFacilityIds())->exists()) {
            return true;
        }

        return false;
    }

    // Scope for accessible resources
    public function scopeAccessibleTo($query, ?User $user) {
        return $query->where(function ($q) use ($user) {
                    // Always include public resources
                    $q->where('visibility', 'public');

                    // If user is authenticated, add additional access levels
                    if ($user) {
                        $q->orWhere(function ($subQ) use ($user) {
                            $subQ->where('visibility', 'authenticated')
                                    ->orWhere('author_id', $user->id)
                                    ->orWhereHas('accessGroups', fn($groupQ) =>
                                            $groupQ->whereHas('users', fn($userQ) => $userQ->where('users.id', $user->id))
                                    );

                            // Add facility scope check if method exists
                            if (method_exists($user, 'scopedFacilityIds')) {
                                $subQ->orWhereHas('scopedFacilities', fn($facQ) =>
                                        $facQ->whereIn('facilities.id', $user->scopedFacilityIds())
                                );
                            }
                        });
                    }
                });
    }
}
