export interface TokenOwner {
  id: number;
  name: string;
}

export interface TokenListItem {
  id: number;
  name: string;
  owner: TokenOwner;
  scopes: string[];
  scope_labels?: Record<string, string>;
  scopes_count: number;
  last_used_at: string | null;
  created_at: string | null;
  status: 'active' | 'revoked' | 'expired';
  type: 'system' | 'user';
}

export interface TokensQuery {
  search: string;
  owner: string;
  status: 'all' | 'active' | 'revoked' | 'expired';
  recent: 'all' | 'recent' | 'stale';
  type: 'all' | 'system' | 'user';
  page: number;
  perPage: number;
}

export interface TokensMetaPayload {
  current_user_permissions: string[];
}
