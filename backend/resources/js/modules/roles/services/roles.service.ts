import { api } from '../../../services/api/client';
import type { ApiResponse } from '../../../types/response.types';
import type { UserListItem } from '../../users/types/users.types';
import type { RoleListItem, RolesMetaPayload } from '../types/roles.types';

interface MetaPayload {
  roles: Array<{ id: number; name: string; label?: string; description?: string | null; translations?: Record<string, { label: string; description: string | null }> }>;
  role_permissions: Record<string, string[]>;
  current_user_permissions?: string[];
}

const SYSTEM_ROLE_NAMES = new Set(['admin', 'manager', 'user']);

/**
 * Roles module service layer.
 *
 * WHY:
 * RBAC views require combining role metadata and user assignments. Isolating
 * this mapping keeps components focused on presentation and scales to future
 * dedicated /roles endpoints without changing UI contracts.
 */
export const rolesService = {
  async fetchRoles(): Promise<RoleListItem[]> {
    const [metaResponse, usersResponse] = await Promise.all([
      api.get<MetaPayload>('/v1/meta'),
      api.get<UserListItem[]>('/v1/users'),
    ]);

    const metaPayload = (metaResponse as ApiResponse<MetaPayload>).data;
    const users = (usersResponse as ApiResponse<UserListItem[]>).data ?? [];

    const rolePermissions = metaPayload?.role_permissions ?? {};
    const permissionLabels = new Map<string, string>(
      (metaPayload?.permissions ?? []).map((permission) => [
        permission.name,
        permission.label ?? permission.name,
      ]),
    );

    return (metaPayload?.roles ?? []).map((role) => {
      const normalizedName = role.name.toLowerCase();
      const usersCount = users.filter((user) => user.roles.includes(role.name)).length;
      const permissions = rolePermissions[role.name] ?? [];
      const permissionsLabels = Object.fromEntries(
        permissions.map((permissionName) => [permissionName, permissionLabels.get(permissionName) ?? permissionName]),
      );

      return {
        id: role.id,
        name: role.name,
        label: role.label ?? role.name,
        description: role.description ?? null,
        translations: role.translations,
        permissions,
        permissions_labels: permissionsLabels,
        permissions_count: permissions.length,
        users_count: usersCount,
        status: 'active',
        type: SYSTEM_ROLE_NAMES.has(normalizedName) ? 'system' : 'custom',
        created_at: null,
      };
    });
  },

  async fetchPermissionsMeta(): Promise<RolesMetaPayload> {
    const response = await api.get<MetaPayload>('/v1/meta');

    return {
      current_user_permissions: response.data?.current_user_permissions ?? [],
    };
  },

  async createRole(payload: {
    name: string;
    description?: string;
    permissions?: string[];
    translations?: Record<string, { label?: string; description?: string }>;
  }): Promise<RoleListItem> {
    const response = await api.post<RoleListItem, typeof payload>('/v1/roles', payload);
    return response.data as RoleListItem;
  },

  async updateRole(
    roleId: number,
    payload: {
      description?: string;
      permissions?: string[];
      translations?: Record<string, { label?: string; description?: string }>;
    },
  ): Promise<RoleListItem> {
    const response = await api.put<RoleListItem, typeof payload>(`/v1/roles/${roleId}`, payload);
    return response.data as RoleListItem;
  },
};
