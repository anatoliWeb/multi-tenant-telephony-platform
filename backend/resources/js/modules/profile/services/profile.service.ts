import { api } from '../../../services/api/client';
import type { ProfileSummary } from '../types/profile.types';

/**
 * Account-profile service boundary.
 *
 * Keeps profile data acquisition isolated from page rendering so future account
 * endpoints can evolve independently (user profile, session/device history,
 * security signals) without forcing UI refactors.
 */
export const profileService = {
  async fetchSummary(): Promise<ProfileSummary> {
    const meta = await api.get<{
      current_user?: { name?: string; email?: string };
      current_user_roles?: string[];
      current_user_permissions?: string[];
      generated_at?: string;
    }>('/v1/meta');

    const user = meta.data?.current_user ?? {};
    const roles = meta.data?.current_user_roles ?? [];
    const permissions = meta.data?.current_user_permissions ?? [];

    return {
      name: user.name ?? 'Admin User',
      email: user.email ?? 'admin@saas.local',
      roles,
      permissionsCount: permissions.length,
      memberSince: '2024-01-01',
      lastActiveAt: meta.data?.generated_at ?? new Date().toISOString(),
    };
  },
};

