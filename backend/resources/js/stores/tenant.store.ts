import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { tenantService } from '../services/tenant/tenant.service';
import { clearActiveTenantId, getActiveTenantId, setActiveTenantId } from '../services/tenant/tenant.storage';
import type { TenantMembershipSummary, TenantSummary } from '../types/tenant.types';
import { useAuthStore } from './auth.store';

export const useTenantStore = defineStore('tenant', () => {
  const memberships = ref<TenantMembershipSummary[]>([]);
  const activeTenant = ref<TenantSummary | null>(null);
  const activeTenantId = ref<string | null>(getActiveTenantId());
  const isHydrated = ref(false);

  const hasTenant = computed(() => Boolean(activeTenantId.value));

  const setSelection = (tenant: TenantSummary | null): void => {
    activeTenant.value = tenant;
    activeTenantId.value = tenant?.id ?? null;

    if (tenant?.id) {
      setActiveTenantId(tenant.id);
      return;
    }

    clearActiveTenantId();
  };

  const clearTenantContext = (): void => {
    memberships.value = [];
    activeTenant.value = null;
    activeTenantId.value = null;
    isHydrated.value = true;
    clearActiveTenantId();
    useAuthStore().clearTenantPermissions();
  };

  const hydrateTenantContext = async (): Promise<void> => {
    try {
      const payload = await tenantService.listTenants();
      memberships.value = payload.tenants;

      const currentMembership = payload.current_tenant_id
        ? payload.tenants.find((membership) => membership.tenant?.id === payload.current_tenant_id) ?? null
        : null;
      const storedTenantId = getActiveTenantId();
      const storedMembership = storedTenantId
        ? payload.tenants.find((membership) => membership.tenant?.id === storedTenantId) ?? null
        : null;
      const fallbackMembership = payload.tenants[0] ?? null;
      const selected = currentMembership ?? storedMembership ?? fallbackMembership;

      setSelection(selected?.tenant ?? null);
      useAuthStore().setPermissionScopes({
        platform_permissions: payload.platform_permissions ?? [],
        tenant_permissions: payload.tenant_permissions ?? [],
        current_tenant_id: selected?.tenant?.id ?? payload.current_tenant_id,
      });
      isHydrated.value = true;
    } catch {
      clearTenantContext();
    }
  };

  const switchTenant = async (tenantUuid: string): Promise<void> => {
    const payload = await tenantService.switchTenant(tenantUuid);
    setSelection(payload.tenant);
    useAuthStore().setPermissionScopes({
      platform_permissions: payload.platform_permissions ?? [],
      tenant_permissions: payload.tenant_permissions ?? [],
      current_tenant_id: payload.current_tenant_id,
    });
    await hydrateTenantContext();
  };

  const refreshCurrentTenant = async (): Promise<void> => {
    try {
      const payload = await tenantService.currentTenant();
      setSelection(payload.tenant);
      useAuthStore().setPermissionScopes({
        platform_permissions: payload.platform_permissions ?? [],
        tenant_permissions: payload.tenant_permissions ?? [],
        current_tenant_id: activeTenantId.value,
      });
    } catch {
      clearTenantContext();
    }
  };

  return {
    memberships,
    activeTenant,
    activeTenantId,
    isHydrated,
    hasTenant,
    setSelection,
    clearTenantContext,
    hydrateTenantContext,
    switchTenant,
    refreshCurrentTenant,
  };
});
