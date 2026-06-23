export interface UserListItem {
  id: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
  denied_permissions: string[];
  created_at?: string | null;
  status: 'active' | 'inactive';
}

export interface UsersMeta {
  current_user_permissions: string[];
}

export interface UsersQuery {
  search: string;
  role: string;
  status: 'all' | 'active' | 'inactive';
  page: number;
  perPage: number;
}
