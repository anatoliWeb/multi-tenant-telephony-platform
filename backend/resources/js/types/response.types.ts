/**
 * Shared response contract types for the admin frontend.
 *
 * WHY:
 * Frontend modules should consume one predictable shape regardless of endpoint.
 * Explicit types reduce implicit assumptions and protect feature code from
 * transport-level or backend envelope changes.
 */
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  [key: string]: unknown;
}

export interface ApiResponse<TData = unknown, TErrors = unknown> {
  success: boolean;
  message: string;
  data?: TData;
  errors?: TErrors;
  meta?: PaginationMeta | Record<string, unknown>;
}

export interface NormalizedApiError<TErrors = unknown> {
  status: number;
  code: 'unauthorized' | 'forbidden' | 'validation' | 'server' | 'network' | 'unknown';
  message: string;
  errors: TErrors | Record<string, unknown> | null;
  meta?: Record<string, unknown>;
}

