<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ApprovedTrainingArea;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovedTrainingAreaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_approved::training::area');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('view_approved::training::area');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_approved::training::area');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('update_approved::training::area');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('delete_approved::training::area');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_approved::training::area');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('force_delete_approved::training::area');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_approved::training::area');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('restore_approved::training::area');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_approved::training::area');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ApprovedTrainingArea $approvedTrainingArea): bool
    {
        return $user->can('replicate_approved::training::area');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_approved::training::area');
    }
}
