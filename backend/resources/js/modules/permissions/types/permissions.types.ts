export interface PermissionListItem {
  id: number;
  name: string;
  label: string;
  translations?: Record<string, { label: string; description: string | null }>;
  module: string;
  module_label?: string;
  description: string | null;
  used_by_roles: string[];
  used_by_roles_labels?: Record<string, string>;
  type: 'read' | 'write' | 'manage';
  type_label?: string;
  usage: 'used' | 'unused';
  created_at?: string | null;
}

export interface PermissionsQuery {
  search: string;
  module: string;
  type: 'all' | 'read' | 'write' | 'manage';
  usage: 'all' | 'used' | 'unused';
  page: number;
  perPage: number;
}

export interface PermissionsMetaPayload {
  current_user_permissions: string[];
}
