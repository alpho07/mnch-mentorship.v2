<?php

namespace Tests\Feature;

use App\Filament\Pages\RagChat;
use App\Filament\Resources\RagDocumentResource;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RagAccessTest extends TestCase
{
    public function test_disabled_rag_hides_navigation(): void
    {
        config()->set('rag.enabled', false);

        $this->be($this->user());

        $this->assertFalse(RagDocumentResource::shouldRegisterNavigation());
        $this->assertFalse(RagChat::shouldRegisterNavigation());
    }

    public function test_enabled_rag_requires_permissions_or_admin_role(): void
    {
        config()->set('rag.enabled', true);

        $user = $this->user();
        $this->be($user);

        $this->assertFalse(RagDocumentResource::shouldRegisterNavigation());
        $this->assertFalse(RagChat::shouldRegisterNavigation());

        $user->setRelation('roles', collect([
            new Role(['name' => 'admin', 'guard_name' => 'web']),
        ]));

        $this->assertTrue(RagDocumentResource::shouldRegisterNavigation());
        $this->assertTrue(RagChat::shouldRegisterNavigation());
    }

    public function test_enabled_rag_allows_super_admin_role(): void
    {
        config()->set('rag.enabled', true);

        $user = $this->user();
        $user->setRelation('roles', collect([
            new Role(['name' => 'super_admin', 'guard_name' => 'web']),
        ]));

        $this->be($user);

        $this->assertTrue(RagDocumentResource::shouldRegisterNavigation());
        $this->assertTrue(RagChat::shouldRegisterNavigation());
    }

    public function test_explicit_rag_permissions_allow_access(): void
    {
        config()->set('rag.enabled', true);

        $this->be($this->user(['view_any_rag::document', 'use_rag_chat']));

        $this->assertTrue(RagDocumentResource::shouldRegisterNavigation());
        $this->assertTrue(RagChat::shouldRegisterNavigation());
    }

    private function user(array $allowed = []): User
    {
        $user = new class($allowed) extends User {
            private array $allowedPermissions;

            public function __construct(array $allowed = [], array $attributes = [])
            {
                $this->allowedPermissions = $allowed;
                parent::__construct($attributes);
            }

            public function can($abilities, $arguments = [])
            {
                if (is_string($abilities) && in_array($abilities, $this->allowedPermissions, true)) {
                    return true;
                }

                return parent::can($abilities, $arguments);
            }
        };

        $user->fill([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'id_number' => '12345678',
            'phone' => '0700000000',
            'status' => 'active',
        ]);
        $user->id = 1;
        $user->exists = true;
        $user->setRelation('roles', collect());

        return $user;
    }
}
