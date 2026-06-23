import axios, { type AxiosError, type AxiosInstance, type AxiosResponse } from 'axios';

import type { BackendResponse } from '../../types/api.types';
import type { NormalizedApiError } from '../../types/response.types';

/**
 * Shared interceptor registration.
 *
 * WHY CENTRALIZED INTERCEPTORS:
 * Request/response policy belongs to infrastructure, not feature components.
 * Centralization guarantees consistent status handling (401/403/422/500),
 * logging behavior, and error shape normalization across the admin frontend.
 */
export const attachInterceptors = (instance: AxiosInstance): void => {
  instance.interceptors.response.use(
    (response: AxiosResponse<BackendResponse>) => response,
    (error: AxiosError<BackendResponse>) => Promise.reject(normalizeApiError(error)),
  );
};

export const normalizeApiError = <TErrors = unknown>(
  error: AxiosError<BackendResponse<unknown, TErrors>>,
): NormalizedApiError<TErrors> => {
  if (!error.response) {
    return {
      status: 0,
      code: 'network',
      message: error.message || 'Network error',
      errors: null,
    };
  }

  const { status, data } = error.response;
  const message = data?.message || 'Request failed';
  const errors = (data?.errors ?? null) as TErrors | null;

  if (status === 401) {
    return { status, code: 'unauthorized', message, errors };
  }

  if (status === 403) {
    return { status, code: 'forbidden', message, errors };
  }

  if (status === 422) {
    return { status, code: 'validation', message, errors };
  }

  if (status >= 500) {
    return { status, code: 'server', message, errors };
  }

  return { status, code: 'unknown', message, errors };
};

export const isNormalizedApiError = (value: unknown): value is NormalizedApiError => {
  if (!value || typeof value !== 'object') {
    return false;
  }

  return 'status' in value && 'code' in value && 'message' in value;
};

export const isAxiosError = axios.isAxiosError;

