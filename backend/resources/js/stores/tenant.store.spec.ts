import { setActivePinia, createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const listTenantsMock = vi.fn();
const currentTenantMock = vi.fn();
const switchTenantMock = vi.fn();
const setPermissionScopesMock = vi.fn();
const clearTenantPermissionsMock = vi.fn();
const storageState = { value: null as string | null };

vi.mock('../services/tenant/tenant.service', () => ({
  tenantService: {
    listTenants: listTenantsMock,
    currentTenant: currentTenantMock,
    switchTenant: switchTenantMock,
  },
}));

vi.mock('../services/tenant/tenant.storage', () => ({
  getActiveTenantId: () => storageState.value,
  setActiveTenantId: (tenantId: string) => {
    storageState.value = tenantId;
  },
  clearActiveTenantId: () => {
    storageState.value = null;
  },
}));

vi.mock('./auth.store', () => ({
  useAuthStore: () => ({
    setPermissionScopes: setPermissionScopesMock,
    clearTenantPermissions: clearTenantPermissionsMock,
  }),
}));

describe('tenant.store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    storageState.value = null;
  });

  it('preserves a platform-admin tenant selection after switching and refreshing the available tenant list', async () => {
    switchTenantMock.mockResolvedValue({
      tenant: {
        id: 'tenant-a',
        uuid: 'tenant-a',
        name: 'Tenant A',
        slug: 'tenant-a',
        status: 'active',
        timezone: 'UTC',
        locale: 'en',
        currency: 'USD',
        settings: {},
        activated_at: null,
        suspended_at: null,
        created_at: null,
        updated_at: null,
      },
      membership: null,
      current_tenant_id: 'tenant-a',
      permissions: ['contacts.view'],
      platform_permissions: ['tenants.view'],
      tenant_permissions: ['contacts.view'],
    });
    listTenantsMock.mockResolvedValue({
      tenants: [
        {
          id: 'tenant-a',
          uuid: 'tenant-a',
          name: 'Tenant A',
          slug: 'tenant-a',
          status: 'active',
          timezone: 'UTC',
          locale: 'en',
          currency: 'USD',
          settings: {},
          activated_at: null,
          suspended_at: null,
          created_at: null,
          updated_at: null,
        },
      ],
      current_tenant_id: null,
      platform_permissions: ['tenants.view'],
      tenant_permissions: [],
    });

    const { useTenantStore } = await import('./tenant.store');
    const store = useTenantStore();

    await store.switchTenant('tenant-a');

    expect(store.activeTenantId).toBe('tenant-a');
    expect(store.activeTenant?.name).toBe('Tenant A');
    expect(setPermissionScopesMock).toHaveBeenCalledWith({
      platform_permissions: ['tenants.view'],
      tenant_permissions: ['contacts.view'],
      current_tenant_id: 'tenant-a',
    });
  });

  it('hydrates tenant permissions from the current tenant endpoint when a stored selection exists', async () => {
    storageState.value = 'tenant-b';
    listTenantsMock.mockResolvedValue({
      tenants: [
        {
          id: 'tenant-b',
          uuid: 'tenant-b',
          name: 'Tenant B',
          slug: 'tenant-b',
          status: 'active',
          timezone: 'UTC',
          locale: 'en',
          currency: 'USD',
          settings: {},
          activated_at: null,
          suspended_at: null,
          created_at: null,
          updated_at: null,
        },
      ],
      current_tenant_id: null,
      platform_permissions: ['tenants.view'],
      tenant_permissions: [],
    });
    currentTenantMock.mockResolvedValue({
      tenant: {
        id: 'tenant-b',
        uuid: 'tenant-b',
        name: 'Tenant B',
        slug: 'tenant-b',
        status: 'active',
        timezone: 'UTC',
        locale: 'en',
        currency: 'USD',
        settings: {},
        activated_at: null,
        suspended_at: null,
        created_at: null,
        updated_at: null,
      },
      membership: null,
      current_tenant_id: 'tenant-b',
      permissions: ['call_logs.view'],
      platform_permissions: ['tenants.view'],
      tenant_permissions: ['call_logs.view'],
    });

    const { useTenantStore } = await import('./tenant.store');
    const store = useTenantStore();

    await store.hydrateTenantContext();

    expect(store.activeTenantId).toBe('tenant-b');
    expect(currentTenantMock).toHaveBeenCalled();
    expect(setPermissionScopesMock).toHaveBeenCalledWith({
      platform_permissions: ['tenants.view'],
      tenant_permissions: ['call_logs.view'],
      current_tenant_id: 'tenant-b',
    });
  });
});
