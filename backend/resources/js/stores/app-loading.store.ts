import { defineStore } from 'pinia';

interface AppLoadingState {
  routeLoadingCount: number;
}

/**
 * Centralized async loading state for route transitions.
 *
 * WHY:
 * Bootstrap loader covers app startup, but users also need feedback during
 * route changes/page async initialization. Counter-based tracking is resilient
 * to nested/parallel async flows.
 */
export const useAppLoadingStore = defineStore('appLoading', {
  state: (): AppLoadingState => ({
    routeLoadingCount: 0,
  }),

  getters: {
    isRouteLoading: (state): boolean => state.routeLoadingCount > 0,
  },

  actions: {
    startRouteLoading(): void {
      this.routeLoadingCount += 1;
    },

    finishRouteLoading(): void {
      this.routeLoadingCount = Math.max(0, this.routeLoadingCount - 1);
    },

    resetRouteLoading(): void {
      this.routeLoadingCount = 0;
    },
  },
});

