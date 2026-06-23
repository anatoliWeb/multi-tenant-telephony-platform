import { reactive } from 'vue';

import type { CacheEntry, PendingCacheRequest } from '../types/cache.types';

interface CacheState {
  entries: Map<string, CacheEntry>;
  pending: Map<string, PendingCacheRequest>;
}

/**
 * Centralized in-memory cache store.
 *
 * WHY CENTRALIZE:
 * A shared cache layer keeps page navigation fast and consistent by avoiding
 * per-module ad-hoc memoization. This store is intentionally memory-based now
 * and can be extended with persistence adapters later.
 */
const state = reactive<CacheState>({
  entries: new Map<string, CacheEntry>(),
  pending: new Map<string, PendingCacheRequest>(),
});

export const cacheStore = {
  get<TData>(key: string): CacheEntry<TData> | null {
    return (state.entries.get(key) as CacheEntry<TData> | undefined) ?? null;
  },

  has(key: string): boolean {
    return state.entries.has(key);
  },

  set<TData>(key: string, data: TData): void {
    state.entries.set(key, {
      data,
      updatedAt: Date.now(),
    });
  },

  remove(key: string): void {
    state.entries.delete(key);
    state.pending.delete(key);
  },

  invalidate(key: string): void {
    this.remove(key);
  },

  invalidatePrefix(prefix: string): void {
    Array.from(state.entries.keys()).forEach((key) => {
      if (key.startsWith(prefix)) {
        state.entries.delete(key);
      }
    });

    Array.from(state.pending.keys()).forEach((key) => {
      if (key.startsWith(prefix)) {
        state.pending.delete(key);
      }
    });
  },

  clear(): void {
    state.entries.clear();
    state.pending.clear();
  },

  invalidateAll(): void {
    this.clear();
  },

  getPending<TData>(key: string): PendingCacheRequest<TData> | null {
    return (state.pending.get(key) as PendingCacheRequest<TData> | undefined) ?? null;
  },

  setPending<TData>(key: string, promise: Promise<TData>): void {
    state.pending.set(key, {
      promise,
      startedAt: Date.now(),
    });
  },

  clearPending(key: string): void {
    state.pending.delete(key);
  },
};
