import axios, { type AxiosInstance } from 'axios';

import { attachInterceptors } from './interceptors';
import { getToken } from '../auth/token.storage';
import { i18n, getStoredLocale } from '../../shared/i18n';

const normalizeApiBaseUrl = (value?: string): string => {
  if (!value) {
    return '/api';
  }

  const trimmed = value.trim();
  const markdownLinkMatch = trimmed.match(/\((https?:\/\/[^)]+)\)/);
  const extracted = markdownLinkMatch ? markdownLinkMatch[1] : trimmed;

  return extracted.replace(/\/+$/, '');
};

const baseURL = normalizeApiBaseUrl(import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL);

/**
 * Centralized Axios instance.
 *
 * WHY:
 * A single HTTP boundary keeps transport behavior predictable across modules:
 * - shared base URL and timeout
 * - shared headers
 * - shared auth header injection
 * - shared error normalization via interceptors
 * - sanitized base URL parsing to avoid malformed env values
 *
 * Components and views should never call axios directly.
 */
export const http: AxiosInstance = axios.create({
  baseURL,
  timeout: 15_000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

http.interceptors.request.use((config) => {
  const token = getToken();
  const activeLocale = i18n.global.locale.value || getStoredLocale();
  const headers = config.headers;

  if (headers && typeof (headers as { set?: unknown }).set === 'function') {
    (headers as { set: (key: string, value: string) => void }).set('Accept-Language', activeLocale);

    if (token) {
      (headers as { set: (key: string, value: string) => void }).set('Authorization', `Bearer ${token}`);
    }

    return config;
  }

  if (!config.headers) {
    config.headers = {};
  }

  config.headers['Accept-Language'] = activeLocale;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

attachInterceptors(http);
