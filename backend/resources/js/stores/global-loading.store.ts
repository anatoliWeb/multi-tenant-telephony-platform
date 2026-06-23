import { defineStore } from 'pinia';

export type GlobalLoadingType = 'bootstrap' | 'locale' | 'route' | 'generic';

interface LoadingEntry {
  id: number;
  startedAt: number;
  message: string;
  type: GlobalLoadingType;
  minDurationMs: number;
}

interface GlobalLoadingState {
  entries: LoadingEntry[];
  nextId: number;
}

/**
 * Unified global loading state.
 *
 * WHY:
 * The app performs multiple async flows (bootstrap, localization, route/data).
 * A token-based centralized loader guarantees consistent UX and prevents
 * duplicated per-component loading overlays.
 */
export const useGlobalLoadingStore = defineStore('globalLoading', {
  state: (): GlobalLoadingState => ({
    entries: [],
    nextId: 1,
  }),

  getters: {
    isGlobalLoading: (state): boolean => state.entries.length > 0,
    loadingMessage: (state): string => state.entries[state.entries.length - 1]?.message ?? '',
    loadingType: (state): GlobalLoadingType => state.entries[state.entries.length - 1]?.type ?? 'generic',
  },

  actions: {
    begin(
      message: string,
      type: GlobalLoadingType = 'generic',
      minDurationMs = 500,
    ): number {
      const id = this.nextId++;

      this.entries.push({
        id,
        startedAt: Date.now(),
        message,
        type,
        minDurationMs,
      });

      return id;
    },

    async end(id: number): Promise<void> {
      const entry = this.entries.find((item) => item.id === id);
      if (!entry) {
        return;
      }

      const elapsed = Date.now() - entry.startedAt;
      const remaining = Math.max(0, entry.minDurationMs - elapsed);

      if (remaining > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, remaining));
      }

      this.entries = this.entries.filter((item) => item.id !== id);
    },

    clear(): void {
      this.entries = [];
    },
  },
});

