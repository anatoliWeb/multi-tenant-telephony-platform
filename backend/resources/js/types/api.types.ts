import type { ApiResponse } from './response.types';

/**
 * API request/utility-level types.
 *
 * WHY:
 * Keep request options and field-level validation typing separate from
 * transport implementation details. This lets services/composables evolve
 * independently while still sharing a contract.
 */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface ApiRequestOptions {
  params?: Record<string, unknown>;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  withCredentials?: boolean;
}

export type ValidationErrors = Record<string, string[]>;

export type BackendResponse<TData = unknown, TErrors = unknown> = ApiResponse<TData, TErrors>;
