import { api } from '../../../services/api/client';
import type { ApiResponse, PaginationMeta } from '../../../types/response.types';
import type { UserListItem, UsersMeta } from '../types/users.types';

interface MetaResponsePayload {
  roles?: Array<{ id: number; name: string; label?: string; description?: string | null }>;
  permissions?: Array<{ id: number; name: string; label?: string; description?: string | null }>;
  role_permissions?: Record<string, string[]>;
  current_user_permissions?: string[];
}

const toStatus = (): 'active' | 'inactive' => 'active';

/**
 * Users module API service.
 *
 * WHY:
 * Module-level services isolate endpoint mapping and data normalization from
 * UI components, making users CRUD patterns reusable for future modules.
 */
export const usersService = {
  async fetchUsers(): Promise<{ items: UserListItem[]; meta?: PaginationMeta | Record<string, unknown> }> {
    const response = await api.get<UserListItem[]>('/v1/users');
    const payload = response as ApiResponse<UserListItem[]>;

    const items = (payload.data ?? []).map((user) => ({
      ...user,
      status: toStatus(),
    }));

    return {
      items,
      meta: payload.meta,
    };
  },

  async fetchPermissionsMeta(): Promise<UsersMeta> {
    const response = await api.get<MetaResponsePayload>('/v1/meta');

    return {
      current_user_permissions: response.data?.current_user_permissions ?? [],
    };
  },

  async fetchRbacMeta(): Promise<Required<MetaResponsePayload>> {
    const response = await api.get<MetaResponsePayload>('/v1/meta');

    return {
      roles: response.data?.roles ?? [],
      permissions: response.data?.permissions ?? [],
      role_permissions: response.data?.role_permissions ?? {},
      current_user_permissions: response.data?.current_user_permissions ?? [],
    };
  },

  async createUser(payload: {
    name: string;
    email: string;
    password: string;
    roles: number[];
    permissions: string[];
    denied_permissions: string[];
  }): Promise<UserListItem> {
    const response = await api.post<UserListItem, typeof payload>('/v1/users', payload);
    return response.data as UserListItem;
  },

  async updateUser(
    userId: number,
    payload: {
      name: string;
      email: string;
      password?: string;
      roles: number[];
      permissions: string[];
      denied_permissions: string[];
    },
  ): Promise<UserListItem> {
    const response = await api.put<UserListItem, typeof payload>(`/v1/users/${userId}`, payload);
    return response.data as UserListItem;
  },
};
