import type { CacheEntry } from '../types/cache.types';

export const DEFAULT_CACHE_TTL = 60_000;

export const isCacheFresh = (
  entry: CacheEntry | null,
  ttl = DEFAULT_CACHE_TTL,
): boolean => {
  if (!entry) return false;
  return Date.now() - entry.updatedAt <= ttl;
};
