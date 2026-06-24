import { of } from 'rxjs';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { TenantContextService } from './tenant-context.service';

describe('TenantContextService', () => {
  const tenantApi = {
    listTenants: vi.fn(),
    currentTenant: vi.fn(),
    switchTenant: vi.fn(),
  };

  const tokenStorage = {
    getToken: vi.fn(),
  };

  const authState = {
    setPermissionScopes: vi.fn(),
    clearTenantPermissions: vi.fn(),
  };

  beforeEach(() => {
    window.localStorage.clear();
    vi.clearAllMocks();
  });

  it('clears tenant state when no token exists', async () => {
    tokenStorage.getToken.mockReturnValue(null);
    const service = new TenantContextService(tenantApi as any, tokenStorage as any, authState as any);

    service.setActiveTenantId('tenant-a');
    await service.hydrateTenantContext();

    expect(service.activeTenantId).toBeNull();
    expect(window.localStorage.getItem('admin_active_tenant_id')).toBeNull();
    expect(tenantApi.listTenants).not.toHaveBeenCalled();
  });

  it('hydrates the first available tenant when no selection is stored', async () => {
    tokenStorage.getToken.mockReturnValue('token');
    tenantApi.listTenants.mockReturnValue(
      of({
        tenants: [
          {
            id: 'membership-1',
            tenant_id: 'tenant-a',
            user_id: 1,
            status: 'active',
            invited_by: null,
            invited_at: null,
            accepted_at: null,
            activated_at: null,
            suspended_at: null,
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
            created_at: null,
            updated_at: null,
          },
        ],
        current_tenant_id: null,
        platform_permissions: ['users.view'],
        tenant_permissions: ['chat.view'],
      }),
    );

    const service = new TenantContextService(tenantApi as any, tokenStorage as any, authState as any);
    await service.hydrateTenantContext();

    expect(service.activeTenantId).toBe('tenant-a');
    expect(service.activeTenant?.slug).toBe('tenant-a');
    expect(authState.setPermissionScopes).toHaveBeenCalled();
  });

  it('switches and persists the tenant selection', async () => {
    tokenStorage.getToken.mockReturnValue('token');
    tenantApi.switchTenant.mockReturnValue(
      of({
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
        permissions: ['chat.view'],
        platform_permissions: ['users.view'],
        tenant_permissions: ['chat.view'],
      }),
    );
    tenantApi.listTenants.mockReturnValue(
      of({
        tenants: [
          {
            id: 'membership-2',
            tenant_id: 'tenant-b',
            user_id: 1,
            status: 'active',
            invited_by: null,
            invited_at: null,
            accepted_at: null,
            activated_at: null,
            suspended_at: null,
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
            created_at: null,
            updated_at: null,
          },
        ],
        current_tenant_id: 'tenant-b',
        platform_permissions: ['users.view'],
        tenant_permissions: ['chat.view'],
      }),
    );

    const service = new TenantContextService(tenantApi as any, tokenStorage as any, authState as any);
    await service.switchTenant('tenant-b');

    expect(service.activeTenantId).toBe('tenant-b');
    expect(window.localStorage.getItem('admin_active_tenant_id')).toBe('tenant-b');
    expect(authState.setPermissionScopes).toHaveBeenCalled();
  });
});
