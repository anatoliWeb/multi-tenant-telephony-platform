import { api } from '../../../services/api/client';
import type { ApiResponse } from '../../../types/response.types';
import type { TokenListItem, TokensMetaPayload } from '../types/tokens.types';

interface TokenApiItem {
  id: number;
  name: string;
  scopes?: string[];
  scope_labels?: Record<string, string>;
  scopes_count?: number;
  status?: 'active' | 'revoked' | 'expired';
  created_at: string | null;
  owner: {
    id: number;
    name: string;
  };
}

interface MetaPayload {
  current_user_permissions?: string[];
}

const inferType = (tokenName: string): 'system' | 'user' => {
  const normalized = tokenName.toLowerCase();
  return normalized.startsWith('sys_') || normalized.startsWith('system-') ? 'system' : 'user';
};

/**
 * Tokens service layer.
 *
 * SECURITY UX NOTE:
 * Token endpoint currently provides core identity fields. Additional security
 * attributes (status/last-used/scopes metadata) are prepared as normalized UI
 * fields so the module can evolve to richer backend contracts without UI churn.
 */
export const tokensService = {
  async fetchTokens(): Promise<TokenListItem[]> {
    const response = await api.get<TokenApiItem[]>('/v1/tokens');
    const payload = (response as ApiResponse<TokenApiItem[]>).data ?? [];

    return payload.map((token) => {
      const scopes = token.scopes ?? [];

      return {
        id: token.id,
        name: token.name,
        owner: token.owner,
        scopes,
        scope_labels: token.scope_labels ?? {},
        scopes_count: token.scopes_count ?? scopes.length,
        last_used_at: null,
        created_at: token.created_at,
        status: token.status ?? 'active',
        type: inferType(token.name),
      };
    });
  },

  async createToken(payload: { name: string; scopes: string[] }): Promise<TokenListItem> {
    const response = await api.post<{ token: string; access_token: TokenApiItem }, typeof payload>('/v1/tokens', payload);
    const token = response.data?.access_token;

    if (!token) {
      throw new Error('Token payload missing.');
    }

    return {
      id: token.id,
      name: token.name,
      owner: token.owner,
      scopes: token.scopes ?? [],
      scope_labels: token.scope_labels ?? {},
      scopes_count: token.scopes_count ?? token.scopes?.length ?? 0,
      last_used_at: null,
      created_at: token.created_at,
      status: token.status ?? 'active',
      type: inferType(token.name),
    };
  },

  async fetchTokensMeta(): Promise<TokensMetaPayload> {
    const response = await api.get<MetaPayload>('/v1/meta');

    return {
      current_user_permissions: response.data?.current_user_permissions ?? [],
    };
  },
};
