const TENANT_KEY = 'admin_active_tenant_id';

export const getActiveTenantId = (): string | null => {
  return window.localStorage.getItem(TENANT_KEY);
};

export const setActiveTenantId = (tenantId: string): void => {
  window.localStorage.setItem(TENANT_KEY, tenantId);
};

export const clearActiveTenantId = (): void => {
  window.localStorage.removeItem(TENANT_KEY);
};
