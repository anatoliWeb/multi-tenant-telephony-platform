import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { tenantService } from '../services/tenant/tenant.service';
import { clearActiveTenantId, getActiveTenantId, setActiveTenantId } from '../services/tenant/tenant.storage';
import type { TenantMembershipSummary, TenantSelectionItem, TenantSummary } from '../types/tenant.types';
import { useAuthStore } from './auth.store';

export const useTenantStore = defineStore('tenant', () => {
  const memberships = ref<TenantSelectionItem[]>([]);
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

  const clearSelection = (): void => {
    setSelection(null);
    useAuthStore().clearTenantPermissions();
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

      const tenantSummaries = payload.tenants.filter((item): item is TenantSummary => !isTenantMembership(item));
      const tenantMemberships = payload.tenants.filter((item): item is TenantMembershipSummary => isTenantMembership(item));
      const currentMembership = payload.current_tenant_id
        ? tenantMemberships.find((membership) => membership.tenant?.id === payload.current_tenant_id) ?? null
        : null;
      const storedTenantId = getActiveTenantId();
      const storedMembership = storedTenantId
        ? tenantMemberships.find((membership) => membership.tenant?.id === storedTenantId) ?? null
        : null;
      const currentSummary = payload.current_tenant_id
        ? tenantSummaries.find((tenant) => tenant.id === payload.current_tenant_id) ?? null
        : null;
      const storedSummary = storedTenantId
        ? tenantSummaries.find((tenant) => tenant.id === storedTenantId) ?? null
        : null;
      const fallbackMembership = tenantMemberships[0] ?? null;
      const selected = currentMembership ?? storedMembership ?? fallbackMembership;
      const selectedTenant = selected?.tenant ?? currentSummary ?? storedSummary ?? null;

      setSelection(selectedTenant);
      if (selectedTenant?.id) {
        await refreshCurrentTenant();
      } else {
        useAuthStore().setPermissionScopes({
          platform_permissions: payload.platform_permissions ?? [],
          tenant_permissions: [],
          current_tenant_id: null,
        });
      }

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
    await refreshTenantList();
  };

  const refreshCurrentTenant = async (): Promise<void> => {
    try {
      const payload = await tenantService.currentTenant();
      setSelection(payload.tenant);
      useAuthStore().setPermissionScopes({
        platform_permissions: payload.platform_permissions ?? [],
        tenant_permissions: payload.tenant_permissions ?? [],
        current_tenant_id: payload.current_tenant_id ?? activeTenantId.value,
      });
    } catch {
      clearSelection();
      await refreshTenantList();
    }
  };

  const refreshTenantList = async (): Promise<void> => {
    const payload = await tenantService.listTenants();
    memberships.value = payload.tenants;

    const tenantSummaries = payload.tenants.filter((item): item is TenantSummary => !isTenantMembership(item));
    const tenantMemberships = payload.tenants.filter((item): item is TenantMembershipSummary => isTenantMembership(item));
    const currentMembership = payload.current_tenant_id
      ? tenantMemberships.find((membership) => membership.tenant?.id === payload.current_tenant_id) ?? null
      : null;
    const storedTenantId = getActiveTenantId();
    const storedMembership = storedTenantId
      ? tenantMemberships.find((membership) => membership.tenant?.id === storedTenantId) ?? null
      : null;
    const currentSummary = payload.current_tenant_id
      ? tenantSummaries.find((tenant) => tenant.id === payload.current_tenant_id) ?? null
      : null;
    const storedSummary = storedTenantId
      ? tenantSummaries.find((tenant) => tenant.id === storedTenantId) ?? null
      : null;
    const fallbackMembership = tenantMemberships[0] ?? null;
    const selected = currentMembership ?? storedMembership ?? fallbackMembership;
    const selectedTenant = selected?.tenant ?? currentSummary ?? storedSummary ?? null;

    setSelection(selectedTenant);
  };

  return {
    memberships,
    activeTenant,
    activeTenantId,
    isHydrated,
    hasTenant,
    setSelection,
    clearSelection,
    clearTenantContext,
    hydrateTenantContext,
    switchTenant,
    refreshCurrentTenant,
    refreshTenantList,
  };
});

function isTenantMembership(item: TenantSelectionItem): item is TenantMembershipSummary {
  return Object.prototype.hasOwnProperty.call(item, 'tenant');
}
