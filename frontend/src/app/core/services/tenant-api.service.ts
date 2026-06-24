import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiClientService } from '../../api/services/api-client.service';
import type { ApiResponse } from '../../api/models/api-response.model';
import type {
  TenantContextPayload,
  TenantMembershipListPayload,
} from '../models/tenant-context.model';

const skipTenantHeader = { 'X-Skip-Tenant-ID': '1' };

@Injectable({ providedIn: 'root' })
export class TenantApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listTenants() {
    return this.apiClient
      .get<TenantMembershipListPayload>('/v1/user/tenants', {
        headers: skipTenantHeader,
      })
      .pipe(map((response: ApiResponse<TenantMembershipListPayload>) => response.data ?? {
        tenants: [],
        current_tenant_id: null,
        platform_permissions: [],
        tenant_permissions: [],
      }));
  }

  currentTenant() {
    return this.apiClient
      .get<TenantContextPayload>('/v1/user/tenant')
      .pipe(map((response: ApiResponse<TenantContextPayload>) => response.data ?? {
        tenant: null,
        membership: null,
        current_tenant_id: null,
        permissions: [],
        platform_permissions: [],
        tenant_permissions: [],
      }));
  }

  switchTenant(tenantUuid: string) {
    return this.apiClient
      .post<TenantContextPayload, { tenant_uuid: string }>(
        '/v1/user/tenant/switch',
        { tenant_uuid: tenantUuid },
        { headers: skipTenantHeader },
      )
      .pipe(map((response: ApiResponse<TenantContextPayload>) => response.data ?? {
        tenant: null,
        membership: null,
        current_tenant_id: null,
        permissions: [],
        platform_permissions: [],
        tenant_permissions: [],
      }));
  }
}
