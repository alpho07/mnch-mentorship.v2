<?php

namespace App\Support;

use App\Models\User;

class RagAccess
{
    public static function canManageDocuments(?User $user): bool
    {
        return self::enabled() && self::has($user, 'view_any_rag::document');
    }

    public static function canCreateDocuments(?User $user): bool
    {
        return self::enabled() && self::has($user, 'create_rag::document');
    }

    public static function canUpdateDocuments(?User $user): bool
    {
        return self::enabled() && self::has($user, 'update_rag::document');
    }

    public static function canDeleteDocuments(?User $user): bool
    {
        return self::enabled() && self::has($user, 'delete_rag::document');
    }

    public static function canUseChat(?User $user): bool
    {
        return self::enabled() && self::has($user, 'use_rag_chat');
    }

    public static function enabled(): bool
    {
        return (bool) config('rag.enabled');
    }

    private static function has(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        try {
            return $user->can($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
