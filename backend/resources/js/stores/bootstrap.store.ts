import { defineStore } from 'pinia';

interface BootstrapState {
  isBootstrapping: boolean;
  isReady: boolean;
  bootError: string | null;
}

/**
 * Application bootstrap lifecycle store.
 *
 * WHY THIS EXISTS:
 * SPA startup now includes async preload steps (runtime translations today,
 * auth/settings/permissions/realtime tomorrow). Centralizing startup state
 * prevents scattered loading flags and gives one predictable readiness signal
 * for the root app shell.
 */
export const useBootstrapStore = defineStore('bootstrap', {
  state: (): BootstrapState => ({
    isBootstrapping: false,
    isReady: false,
    bootError: null,
  }),

  actions: {
    startBoot(): void {
      this.isBootstrapping = true;
      this.isReady = false;
      this.bootError = null;
    },

    finishBoot(): void {
      this.isBootstrapping = false;
      this.isReady = true;
      this.bootError = null;
    },

    failBoot(error: unknown): void {
      this.isBootstrapping = false;
      this.isReady = false;
      this.bootError = error instanceof Error ? error.message : 'Bootstrap failed';
    },
  },
});

