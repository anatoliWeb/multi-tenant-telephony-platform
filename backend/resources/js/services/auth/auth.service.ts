import { getToken, removeToken, setToken } from './token.storage';
import { api } from '../api/client';
import type { ApiResponse } from '../../types/response.types';
import type { AuthUser } from '../../types/auth.types';

interface SessionAuthPayload {
  user: AuthUser | null;
  permissions: string[];
}

/**
 * Lightweight auth utility service.
 *
 * Scope in this phase:
 * - token persistence helpers only
 * - no login/logout API implementation yet
 */
export const authService = {
  getToken,
  setToken,
  removeToken,
  login: async (payload: { email: string; password: string; remember?: boolean }): Promise<SessionAuthPayload> => {
    // Vue Admin canonical auth flow is session-first.
    const sessionResponse = await api.post<SessionAuthPayload, typeof payload>('/v1/auth/session/login', payload);
    const tokenPayload = (sessionResponse as ApiResponse<SessionAuthPayload>).data ?? { user: null, permissions: [] };

    return {
      user: tokenPayload.user ?? null,
      permissions: tokenPayload.permissions ?? [],
    };
  },
  fetchSession: async (): Promise<SessionAuthPayload> => {
    const bearer = getToken();

    if (bearer) {
      try {
        const tokenResponse = await api.get<SessionAuthPayload>('/v1/auth/me');
        return (tokenResponse as ApiResponse<SessionAuthPayload>).data ?? { user: null, permissions: [] };
      } catch {
        removeToken();
      }
    }

    const sessionResponse = await api.get<SessionAuthPayload>('/v1/auth/session/me');
    return (sessionResponse as ApiResponse<SessionAuthPayload>).data ?? { user: null, permissions: [] };
  },
  /**
   * Session logout endpoint for Laravel web guard.
   *
   * WHY:
   * Admin SPA is embedded into Laravel and primarily authenticated via
   * session/cookie auth. We therefore call the canonical web logout route
   * instead of inventing a separate frontend-only logout flow.
   */
  logout: async (): Promise<void> => {
    if (getToken()) {
      try {
        await api.post('/v1/auth/logout', {});
      } finally {
        removeToken();
      }
    }

    await api.post('/v1/auth/session/logout', {});
  },
};
