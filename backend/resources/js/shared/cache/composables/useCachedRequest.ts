import { cacheStore } from '../stores/cache.store';
import type { CachedRequestOptions, CachedRequestResult } from '../types/cache.types';
import { DEFAULT_CACHE_TTL, isCacheFresh } from '../utils/cache.utils';

const revalidate = async <TData>(
  key: string,
  request: () => Promise<TData>,
): Promise<TData> => {
  const pending = cacheStore.getPending<TData>(key);
  if (pending) {
    return pending.promise;
  }

  const promise = request()
    .then((data) => {
      cacheStore.set(key, data);
      return data;
    })
    .finally(() => {
      cacheStore.clearPending(key);
    });

  cacheStore.setPending(key, promise);
  return promise;
};

/**
 * Reusable stale-while-revalidate request runner.
 *
 * STRATEGY:
 * - fresh cache: return immediately, skip loader UX
 * - stale cache: return cached data immediately and refresh in background
 * - empty cache: perform normal network request
 *
 * REQUEST DEDUPLICATION:
 * All concurrent requests for the same key share one in-flight promise to
 * avoid duplicate API traffic and reduce UI flicker across route transitions.
 */
export const useCachedRequest = async <TData>(
  options: CachedRequestOptions<TData>,
): Promise<CachedRequestResult<TData>> => {
  const ttl = options.ttl ?? DEFAULT_CACHE_TTL;
  const staleWhileRevalidate = options.staleWhileRevalidate ?? true;
  const cachedEntry = cacheStore.get<TData>(options.key);

  if (!options.force && cachedEntry) {
    const fresh = isCacheFresh(cachedEntry, ttl);

    if (fresh) {
      return {
        data: cachedEntry.data,
        source: 'cache-fresh',
        isStale: false,
        revalidating: false,
      };
    }

    if (staleWhileRevalidate) {
      void revalidate(options.key, options.request).then((freshData) => {
        options.onBackgroundUpdate?.(freshData);
      });

      return {
        data: cachedEntry.data,
        source: 'cache-stale',
        isStale: true,
        revalidating: true,
      };
    }
  }

  const data = await revalidate(options.key, options.request);
  return {
    data,
    source: 'network',
    isStale: false,
    revalidating: false,
  };
};
