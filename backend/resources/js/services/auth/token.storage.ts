const TOKEN_KEY = 'admin_access_token';

/**
 * Token storage helpers.
 *
 * WHY:
 * Token persistence is an infrastructure concern and should not be duplicated
 * across views/stores/services. Centralizing token I/O keeps auth coupling low
 * and simplifies future migrations (cookies, secure storage, etc.).
 */
export const getToken = (): string | null => {
  return window.localStorage.getItem(TOKEN_KEY);
};

export const setToken = (token: string): void => {
  window.localStorage.setItem(TOKEN_KEY, token);
};

export const removeToken = (): void => {
  window.localStorage.removeItem(TOKEN_KEY);
};

