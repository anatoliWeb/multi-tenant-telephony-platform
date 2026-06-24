import { api } from '../api/client';
import type { ApiResponse } from '../../types/response.types';
import type {
  TenantContextPayload,
  TenantMembershipListPayload,
} from '../../types/tenant.types';

const skipTenantHeader = { headers: { 'X-Skip-Tenant-ID': '1' } };

export const tenantService = {
  listTenants: async (): Promise<TenantMembershipListPayload> => {
    const response = await api.get<TenantMembershipListPayload>('/v1/user/tenants', skipTenantHeader);
    return (response as ApiResponse<TenantMembershipListPayload>).data ?? { tenants: [], current_tenant_id: null, platform_permissions: [], tenant_permissions: [] };
  },
  currentTenant: async (): Promise<TenantContextPayload> => {
    const response = await api.get<TenantContextPayload>('/v1/user/tenant');
    return (response as ApiResponse<TenantContextPayload>).data ?? { tenant: null, membership: null, current_tenant_id: null, permissions: [], platform_permissions: [], tenant_permissions: [] };
  },
  switchTenant: async (tenantUuid: string): Promise<TenantContextPayload> => {
    const response = await api.post<TenantContextPayload, { tenant_uuid: string }>(
      '/v1/user/tenant/switch',
      { tenant_uuid: tenantUuid },
      skipTenantHeader,
    );
    return (response as ApiResponse<TenantContextPayload>).data ?? { tenant: null, membership: null, current_tenant_id: null, permissions: [], platform_permissions: [], tenant_permissions: [] };
  },
};
