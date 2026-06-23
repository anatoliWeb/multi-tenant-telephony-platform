import { defineStore } from 'pinia';

/**
 * Application store placeholder.
 *
 * Intended for cross-module UI state (layout preferences, global loading,
 * non-domain app concerns). Domain business state should stay in module stores.
 */
export const useAppStore = defineStore('app', () => {
  return {};
});

