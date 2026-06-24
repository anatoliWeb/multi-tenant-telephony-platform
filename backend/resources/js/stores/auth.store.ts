import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { authService } from '../services/auth/auth.service';
import { cacheStore } from '../shared/cache';
import { useTranslationStore } from './translation.store';
import { useGlobalLoadingStore } from './global-loading.store';
import { useTenantStore } from './tenant.store';
import { clearActiveTenantId } from '../services/tenant/tenant.storage';
import type { AuthUser } from '../types/auth.types';

/**
 * Centralized auth/session state for admin SPA.
 *
 * WHY:
 * Session auth, route guards, and user-permission hydration should be handled
 * in one place so views/components stay thin and consistent.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null);
  const permissions = ref<string[]>([]);
  const isHydrated = ref(false);

  const isAuthenticated = computed(() => Boolean(user.value));
  const hasPermission = (permission: string): boolean => permissions.value.includes(permission);
  const hasAnyPermission = (requiredPermissions: string[]): boolean => {
    return requiredPermissions.some((permission) => hasPermission(permission));
  };

  const setSession = (payload: { user: AuthUser | null; permissions: string[] }): void => {
    user.value = payload.user;
    permissions.value = payload.permissions ?? [];
    isHydrated.value = true;
  };

  const clearAuthState = (options: { preserveTranslations?: boolean } = {}): void => {
    authService.removeToken();
    cacheStore.clear();
    clearActiveTenantId();
    useTenantStore().clearTenantContext();

    user.value = null;
    permissions.value = [];
    isHydrated.value = true;

    if (!options.preserveTranslations) {
      useTranslationStore().resetState();
    }
  };

  const hydrateSession = async (): Promise<boolean> => {
    if (isHydrated.value) {
      return isAuthenticated.value;
    }

    try {
      const payload = await authService.fetchSession();
      setSession(payload);
      await useTenantStore().hydrateTenantContext();
      return Boolean(payload.user);
    } catch {
      clearAuthState({ preserveTranslations: true });
      return false;
    }
  };

  const login = async (credentials: { email: string; password: string; remember?: boolean }): Promise<void> => {
    const payload = await authService.login(credentials);
    setSession(payload);
    await useTenantStore().hydrateTenantContext();
  };

  const logout = async (): Promise<void> => {
    const loadingStore = useGlobalLoadingStore();
    const loadingToken = loadingStore.begin('Signing out...', 'generic', 450);

    try {
      await authService.logout();
    } finally {
      clearAuthState();
      await loadingStore.end(loadingToken);
      window.location.assign('/admin/login');
    }
  };

  return {
    user,
    permissions,
    isHydrated,
    isAuthenticated,
    hasPermission,
    hasAnyPermission,
    setSession,
    clearAuthState,
    hydrateSession,
    login,
    logout,
  };
});
