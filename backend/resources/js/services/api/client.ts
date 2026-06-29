import type { AxiosRequestConfig, AxiosResponse } from 'axios';

import { http } from './http';
import type { ApiRequestOptions, BackendResponse } from '../../types/api.types';

/**
 * API client wrapper.
 *
 * WHY THIS LAYER EXISTS:
 * Feature modules should depend on a stable API abstraction, not on axios APIs.
 * This reduces coupling, makes transport swap/testing easier, and keeps view
 * code focused on UI use-cases.
 */
const toConfig = (options?: ApiRequestOptions): AxiosRequestConfig => ({
  params: options?.params,
  headers: options?.headers,
  signal: options?.signal,
  withCredentials: options?.withCredentials,
});

export const api = {
  get: async <TData = unknown>(
    url: string,
    options?: ApiRequestOptions,
  ): Promise<BackendResponse<TData>> => {
    const response = await http.get<BackendResponse<TData>>(url, toConfig(options));
    return response.data;
  },

  post: async <TData = unknown, TPayload = unknown>(
    url: string,
    payload?: TPayload,
    options?: ApiRequestOptions,
  ): Promise<BackendResponse<TData>> => {
    const response = await http.post<BackendResponse<TData>>(url, payload, toConfig(options));
    return response.data;
  },

  put: async <TData = unknown, TPayload = unknown>(
    url: string,
    payload?: TPayload,
    options?: ApiRequestOptions,
  ): Promise<BackendResponse<TData>> => {
    const response = await http.put<BackendResponse<TData>>(url, payload, toConfig(options));
    return response.data;
  },

  patch: async <TData = unknown, TPayload = unknown>(
    url: string,
    payload?: TPayload,
    options?: ApiRequestOptions,
  ): Promise<BackendResponse<TData>> => {
    const response = await http.patch<BackendResponse<TData>>(url, payload, toConfig(options));
    return response.data;
  },

  delete: async <TData = unknown>(
    url: string,
    options?: ApiRequestOptions,
  ): Promise<BackendResponse<TData>> => {
    const response = await http.delete<BackendResponse<TData>>(url, toConfig(options));
    return response.data;
  },

  download: async (
    url: string,
    options?: ApiRequestOptions,
  ): Promise<AxiosResponse<Blob>> => {
    return http.get<Blob>(url, {
      ...toConfig(options),
      responseType: 'blob',
    });
  },
};
