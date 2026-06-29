import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const mocks = vi.hoisted(() => ({
  removeToken: vi.fn(),
  clearStore: vi.fn(),
  clearActiveTenantId: vi.fn(),
  clearTenantContext: vi.fn(),
  resetTranslations: vi.fn(),
  begin: vi.fn(() => 'loading-token'),
  end: vi.fn(() => Promise.resolve()),
}));

vi.mock('../services/auth/auth.service', () => ({
  authService: {
    removeToken: mocks.removeToken,
  },
}));

vi.mock('../shared/cache', () => ({
  cacheStore: {
    clear: mocks.clearStore,
  },
}));

vi.mock('../services/tenant/tenant.storage', () => ({
  clearActiveTenantId: mocks.clearActiveTenantId,
}));

vi.mock('./tenant.store', () => ({
  useTenantStore: () => ({
    clearTenantContext: mocks.clearTenantContext,
  }),
}));

vi.mock('./translation.store', () => ({
  useTranslationStore: () => ({
    resetState: mocks.resetTranslations,
  }),
}));

vi.mock('./global-loading.store', () => ({
  useGlobalLoadingStore: () => ({
    begin: mocks.begin,
    end: mocks.end,
  }),
}));

describe('auth.store permission scoping', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('keeps platform permissions available when tenant permissions are applied', async () => {
    const { useAuthStore } = await import('./auth.store');
    const auth = useAuthStore();

    auth.setSession({
      user: { id: 1, name: 'Admin', email: 'admin@test.local' },
      permissions: ['users.view', 'roles.view'],
      platform_permissions: ['users.view', 'roles.view'],
      tenant_permissions: ['contacts.view'],
      roles: ['admin'],
    });

    auth.setPermissionScopes({
      platform_permissions: ['users.view', 'roles.view'],
      tenant_permissions: ['contacts.view', 'extensions.view'],
      current_tenant_id: 'tenant-a',
    });

    expect(auth.hasPermission('users.view')).toBe(true);
    expect(auth.hasPlatformPermission('roles.view')).toBe(true);
    expect(auth.hasTenantPermission('contacts.view')).toBe(true);
    expect(auth.hasAnyTenantPermission(['missing', 'extensions.view'])).toBe(true);
    expect(auth.hasAnyPermission(['users.view'])).toBe(true);
  });

  it('restores the platform permission bucket when tenant permissions are cleared', async () => {
    const { useAuthStore } = await import('./auth.store');
    const auth = useAuthStore();

    auth.setSession({
      user: { id: 1, name: 'Admin', email: 'admin@test.local' },
      permissions: ['users.view'],
      platform_permissions: ['users.view', 'tokens.view'],
      tenant_permissions: ['contacts.view'],
      roles: ['admin'],
    });

    auth.setPermissionScopes({
      platform_permissions: ['users.view', 'tokens.view'],
      tenant_permissions: ['contacts.view'],
      current_tenant_id: 'tenant-a',
    });

    auth.clearTenantPermissions();

    expect(auth.hasPermission('users.view')).toBe(true);
    expect(auth.hasPermission('tokens.view')).toBe(true);
    expect(auth.hasTenantPermission('contacts.view')).toBe(false);
    expect(auth.hasAnyPlatformPermission(['tokens.view'])).toBe(true);
  });
});
