<?php

namespace App\Policies;

use App\Models\RagDocument;
use App\Models\User;
use App\Support\RagAccess;
use Illuminate\Auth\Access\HandlesAuthorization;

class RagDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return RagAccess::canManageDocuments($user);
    }

    public function view(User $user, RagDocument $document): bool
    {
        return RagAccess::canManageDocuments($user);
    }

    public function create(User $user): bool
    {
        return RagAccess::canCreateDocuments($user);
    }

    public function update(User $user, RagDocument $document): bool
    {
        return RagAccess::canUpdateDocuments($user);
    }

    public function delete(User $user, RagDocument $document): bool
    {
        return RagAccess::canDeleteDocuments($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function download(User $user, RagDocument $document): bool
    {
        return RagAccess::canManageDocuments($user);
    }

    public function retry(User $user, RagDocument $document): bool
    {
        return RagAccess::canUpdateDocuments($user) && $document->canRetry();
    }
}
