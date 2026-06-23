export interface RoleListItem {
  id: number;
  name: string;
  label: string;
  description: string | null;
  translations?: Record<string, { label: string; description: string | null }>;
  permissions: string[];
  permissions_labels?: Record<string, string>;
  permissions_count: number;
  users_count: number;
  status: 'active' | 'inactive';
  type: 'system' | 'custom';
  created_at?: string | null;
}

export interface RolesQuery {
  search: string;
  type: 'all' | 'system' | 'custom';
  status: 'all' | 'active' | 'inactive';
  page: number;
  perPage: number;
}

export interface RolesMetaPayload {
  current_user_permissions: string[];
}
