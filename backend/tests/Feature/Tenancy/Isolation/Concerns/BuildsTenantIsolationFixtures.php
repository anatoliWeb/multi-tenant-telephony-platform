<?php

namespace Tests\Feature\Tenancy\Isolation\Concerns;

use App\Enums\Rbac\PermissionScope;
use App\Enums\Rbac\RoleScope;
use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\ChatWebhookEndpoint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

trait BuildsTenantIsolationFixtures
{
    protected function createTenant(string $slug, TenantStatus $status = TenantStatus::Active): Tenant
    {
        $now = now();

        return Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'timezone' => 'UTC',
            'locale' => 'en',
            'currency' => 'USD',
            'settings' => [],
            'activated_at' => $status === TenantStatus::Active ? $now : null,
            'suspended_at' => $status === TenantStatus::Suspended ? $now : null,
        ]);
    }

    protected function createUser(string $prefix = 'tenant-user'): User
    {
        return User::factory()->create([
            'email' => sprintf('%s-%s@example.test', $prefix, Str::lower(Str::random(8))),
        ]);
    }

    protected function createMembership(
        Tenant $tenant,
        User $user,
        TenantMembershipStatus $status = TenantMembershipStatus::Active
    ): TenantMembership {
        $now = now();

        return TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => $status,
            'invited_by' => null,
            'invited_at' => $status === TenantMembershipStatus::Invited ? $now : null,
            'accepted_at' => $status === TenantMembershipStatus::Invited ? null : $now,
            'activated_at' => $status === TenantMembershipStatus::Active ? $now : null,
            'suspended_at' => $status === TenantMembershipStatus::Suspended ? $now : null,
        ]);
    }

    /**
     * @param array<int, string> $permissions
     */
    protected function assignTenantPermissions(User $user, Tenant $tenant, array $permissions, ?string $roleName = null): Role
    {
        $role = Role::create([
            'name' => $roleName ?? 'tenant-role-'.Str::lower(Str::random(8)),
            'scope' => RoleScope::Tenant->value,
            'scope_reference' => (string) $tenant->getKey(),
            'tenant_id' => (string) $tenant->getKey(),
            'description' => 'Tenant isolation test role',
            'is_system' => false,
            'is_protected' => false,
        ]);

        $permissionIds = collect($permissions)
            ->map(function (string $name): int {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'scope' => PermissionScope::Tenant->value],
                    [
                        'scope_reference' => PermissionScope::Tenant->value,
                        'description' => ucfirst(str_replace('.', ' ', $name)),
                    ]
                );

                return (int) $permission->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);
        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'tenant_id' => (string) $tenant->getKey(),
                'scope_reference' => (string) $tenant->getKey(),
            ],
        ]);

        return $role;
    }

    /**
     * @param array<int, string> $permissions
     */
    protected function assignPlatformPermissions(User $user, array $permissions): void
    {
        $permissionIds = collect($permissions)
            ->map(function (string $name): int {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'scope' => PermissionScope::Platform->value],
                    [
                        'scope_reference' => PermissionScope::Platform->value,
                        'description' => ucfirst(str_replace('.', ' ', $name)),
                    ]
                );

                return (int) $permission->id;
            })
            ->all();

        $user->permissions()->syncWithoutDetaching($permissionIds);
    }

    protected function actingAsTenantUser(User $user): User
    {
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createConversation(Tenant $tenant, User $owner, array $overrides = []): Conversation
    {
        return Conversation::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'type' => 'group',
            'visibility' => 'private',
            'title' => 'Isolation conversation',
            'description' => null,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'created_from_conversation_id' => null,
            'source' => 'internal',
            'status' => 'active',
            'join_policy' => 'invite_only',
            'history_import_mode' => 'none',
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function addParticipant(Conversation $conversation, User $user, array $overrides = []): ConversationParticipant
    {
        return ConversationParticipant::query()->create(array_merge([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'access_state' => 'full',
            'block_display_mode' => null,
            'can_invite' => false,
            'can_remove' => false,
            'can_send' => true,
            'can_attach' => true,
            'can_manage' => false,
            'can_moderate' => false,
            'history_visibility_mode' => 'full',
            'history_visible_from_message_id' => null,
            'history_visible_from_at' => null,
            'history_visible_until_message_id' => null,
            'history_visible_until_at' => null,
            'joined_at' => now(),
            'left_at' => null,
            'removed_at' => null,
            'last_read_message_id' => null,
            'last_read_at' => null,
            'muted_until' => null,
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createMessage(Conversation $conversation, ?User $sender, array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_id' => $sender?->id,
            'sender_type' => $sender ? 'user' : 'system',
            'external_id' => null,
            'reply_to_message_id' => null,
            'type' => 'text',
            'body' => 'Isolation message',
            'status' => 'sent',
            'is_imported' => false,
            'imported_from_conversation_id' => null,
            'imported_from_message_id' => null,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => null,
            'edited_at' => null,
            'deleted_at' => null,
            'metadata' => [],
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createWebhookEndpoint(Tenant $tenant, User $creator, array $overrides = []): ChatWebhookEndpoint
    {
        return ChatWebhookEndpoint::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Isolation webhook endpoint',
            'url' => 'https://example.test/hooks/'.Str::lower(Str::random(6)),
            'secret' => Str::random(64),
            'events' => ['message.created'],
            'is_active' => true,
            'status' => 'active',
            'created_by' => $creator->id,
            'metadata' => ['token_hash' => 'hash-'.Str::lower(Str::random(8))],
        ], $overrides));
    }
}
