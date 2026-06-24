import { describe, expect, it, vi } from 'vitest';

import { evaluateAdminRouteAccess } from './permission-guard';

const makeRoute = (
  path: string,
  meta: Record<string, unknown> = {},
): { path: string; fullPath: string; matched: Array<{ meta: Record<string, unknown> }> } => ({
  path,
  fullPath: path,
  matched: [{ meta }],
});

describe('RouterPermissionGuard', () => {
  it('allows /admin/chat when user has chat.admin.view', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/chat', { requiresAuth: true, permissions: ['chat.admin.view', 'chat.admin.view_metadata'] }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: (permission) => permission === 'chat.admin.view',
        hasAnyPlatformPermission: (permissions) => permissions.includes('chat.admin.view'),
      },
    );

    expect(result).toBe(true);
  });

  it('allows /admin/chat when user has chat.admin.view_metadata', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/chat', { requiresAuth: true, permissions: ['chat.admin.view', 'chat.admin.view_metadata'] }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: (permission) => permission === 'chat.admin.view_metadata',
        hasAnyPlatformPermission: (permissions) => permissions.includes('chat.admin.view_metadata'),
      },
    );

    expect(result).toBe(true);
  });

  it('denies /admin/chat when user has neither permission', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/chat', { requiresAuth: true, permissions: ['chat.admin.view', 'chat.admin.view_metadata'] }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: () => false,
        hasAnyPlatformPermission: () => false,
      },
    );

    expect(result).toEqual({ path: '/dashboard' });
  });

  it('redirects unauthenticated protected route to /admin/login path', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/chat', { requiresAuth: true }),
      {
        hydrateSession: async () => false,
        hasPlatformPermission: () => false,
        hasAnyPlatformPermission: () => false,
      },
    );

    expect(result).toEqual({ path: '/login', query: { redirect: '/chat' } });
  });

  it('allows public /admin/login route', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/login', { guestOnly: true }),
      {
        hydrateSession: async () => false,
        hasPlatformPermission: () => false,
        hasAnyPlatformPermission: () => false,
      },
    );

    expect(result).toBe(true);
  });

  it('enforces meta.permission exact check', async () => {
    const denied = await evaluateAdminRouteAccess(
      makeRoute('/users', { requiresAuth: true, permission: 'users.view' }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: () => false,
        hasAnyPlatformPermission: () => false,
      },
    );
    const allowed = await evaluateAdminRouteAccess(
      makeRoute('/users', { requiresAuth: true, permission: 'users.view' }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: (permission) => permission === 'users.view',
        hasAnyPlatformPermission: () => false,
      },
    );

    expect(denied).toEqual({ path: '/dashboard' });
    expect(allowed).toBe(true);
  });

  it('waits for hydrateSession before permission checks', async () => {
    const order: string[] = [];
    const result = await evaluateAdminRouteAccess(
      makeRoute('/chat', { requiresAuth: true, permissions: ['chat.admin.view'] }),
      {
        hydrateSession: async () => {
          order.push('hydrate:start');
          await new Promise((resolve) => setTimeout(resolve, 5));
          order.push('hydrate:end');
          return true;
        },
        hasPlatformPermission: () => {
          order.push('hasPlatformPermission');
          return false;
        },
        hasAnyPlatformPermission: () => {
          order.push('hasAnyPlatformPermission');
          return true;
        },
      },
    );

    expect(order.indexOf('hydrate:end')).toBeLessThan(order.indexOf('hasAnyPlatformPermission'));
    expect(result).toBe(true);
  });

  it('prevents infinite redirect loop on /admin/dashboard deny path', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/dashboard', { requiresAuth: true, permission: 'dashboard.view' }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: () => false,
        hasAnyPlatformPermission: () => false,
      },
    );

    expect(result).toBe(false);
  });

  it('guestOnly route redirects authenticated user to /dashboard', async () => {
    const result = await evaluateAdminRouteAccess(
      makeRoute('/login', { guestOnly: true }),
      {
        hydrateSession: async () => true,
        hasPlatformPermission: vi.fn(() => true),
        hasAnyPlatformPermission: vi.fn(() => true),
      },
    );

    expect(result).toEqual({ path: '/dashboard' });
  });
});

