export interface ActivityActor {
  id?: number;
  name?: string;
  email?: string;
}

export interface ActivityLogItem {
  id: string;
  user: ActivityActor | null;
  action: string;
  module: string;
  entity: string;
  description: string;
  status: 'success' | 'warning' | 'error';
  ip_address: string | null;
  created_at: string | null;
  meta: Record<string, unknown>;
}

export interface ActivityQuery {
  search: string;
  module: string;
  actionType: string;
  status: 'all' | 'success' | 'warning' | 'error';
  user: string;
  dateRange: 'all' | 'today' | '7d' | '30d';
  page: number;
  perPage: number;
}

export interface ActivityMetaPayload {
  current_user_permissions: string[];
}

export interface ActivityListFilters {
  search?: string;
  action?: string;
  user_id?: number;
  subject_type?: string;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
}

export interface ActivityListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ActivityListResponse {
  items: ActivityLogItem[];
  meta: ActivityListMeta;
}
