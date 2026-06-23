export interface CacheEntry<TData = unknown> {
  data: TData;
  updatedAt: number;
}

export interface PendingCacheRequest<TData = unknown> {
  promise: Promise<TData>;
  startedAt: number;
}

export interface CachedRequestOptions<TData> {
  key: string;
  request: () => Promise<TData>;
  ttl?: number;
  staleWhileRevalidate?: boolean;
  force?: boolean;
  onBackgroundUpdate?: (freshData: TData) => void;
}

export interface CachedRequestResult<TData> {
  data: TData;
  source: 'cache-fresh' | 'cache-stale' | 'network';
  isStale: boolean;
  revalidating: boolean;
}
