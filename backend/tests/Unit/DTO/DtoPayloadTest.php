<?php

use App\DTO\AuthContextDTO;
use App\DTO\NotificationPayloadDTO;
use App\DTO\StatsDTO;
use App\DTO\TokenPayloadDTO;
use App\DTO\UserDTO;

test('user dto toArray returns expected payload shape', function () {
    $dto = new UserDTO(
        id: 1,
        name: 'Jane Admin',
        email: 'jane@example.com',
        roles: ['admin'],
        permissions: ['users.view'],
        deniedPermissions: ['users.delete'],
        createdAt: '2026-05-17T12:00:00Z',
    );

    $payload = $dto->toArray();

    expect(array_keys($payload))->toBe([
        'id',
        'name',
        'email',
        'roles',
        'permissions',
        'denied_permissions',
        'created_at',
    ]);

    expect($payload['roles'])->toBe(['admin']);
    expect($payload['permissions'])->toBe(['users.view']);
    expect($payload['denied_permissions'])->toBe(['users.delete']);
});

test('auth context dto toArray returns expected payload shape', function () {
    $dto = new AuthContextDTO(
        user: ['id' => 5, 'name' => 'John'],
        permissions: ['roles.view'],
        platformPermissions: ['roles.view'],
        tenantPermissions: [],
        roles: ['manager'],
    );

    $payload = $dto->toArray();

    expect(array_keys($payload))->toBe([
        'user',
        'permissions',
        'platform_permissions',
        'tenant_permissions',
        'roles',
    ]);

    expect($payload['user'])->toBe(['id' => 5, 'name' => 'John']);
    expect($payload['permissions'])->toBe(['roles.view']);
    expect($payload['platform_permissions'])->toBe(['roles.view']);
    expect($payload['tenant_permissions'])->toBe([]);
    expect($payload['roles'])->toBe(['manager']);
});

test('auth context dto supports guest-safe null user payload', function () {
    $dto = new AuthContextDTO(
        user: null,
        permissions: [],
        platformPermissions: [],
        tenantPermissions: [],
        roles: [],
    );

    $payload = $dto->toArray();

    expect($payload['user'])->toBeNull();
    expect($payload['permissions'])->toBe([]);
    expect($payload['platform_permissions'])->toBe([]);
    expect($payload['tenant_permissions'])->toBe([]);
    expect($payload['roles'])->toBe([]);
});

test('token payload dto toArray returns expected payload shape', function () {
    $dto = new TokenPayloadDTO(
        id: 7,
        name: 'Admin API Token',
        abilities: ['users.view', 'roles.view'],
        createdAt: '2026-05-17T12:00:00Z',
        owner: ['id' => 11, 'name' => 'Owner User'],
    );

    $payload = $dto->toArray();

    expect(array_keys($payload))->toBe([
        'id',
        'name',
        'abilities',
        'created_at',
        'owner',
    ]);

    expect(array_keys($payload['owner']))->toBe(['id', 'name']);
});

test('notification payload dto toArray returns expected payload shape', function () {
    $dto = new NotificationPayloadDTO(
        id: 'notif-1',
        type: 'system',
        title: 'Settings updated',
        message: 'A system setting was updated.',
        data: ['source' => 'settings'],
        isRead: false,
        readAt: null,
        createdAt: '2026-05-17T12:00:00Z',
    );

    $payload = $dto->toArray();

    expect(array_keys($payload))->toBe([
        'id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'created_at',
    ]);

    expect($payload['data'])->toBe(['source' => 'settings']);
});

test('stats dto toArray returns expected payload shape', function () {
    $dto = new StatsDTO(
        users: 100,
        roles: 4,
        permissions: 25,
        activityLogs: 300,
        admins: 2,
        managers: 8,
        tokens: 12,
        usersWithDirectPermissions: 5,
        recentActivity: [
            ['action' => 'user_updated', 'subject_id' => 10],
        ],
    );

    $payload = $dto->toArray();

    expect(array_keys($payload))->toBe([
        'users',
        'roles',
        'permissions',
        'activity_logs',
        'admins',
        'managers',
        'tokens',
        'users_with_direct_permissions',
        'recent_activity',
    ]);

    expect($payload['recent_activity'])->toBe([
        ['action' => 'user_updated', 'subject_id' => 10],
    ]);
});
