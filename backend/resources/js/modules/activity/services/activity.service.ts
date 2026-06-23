import { api } from '../../../services/api/client';
import type { ApiResponse } from '../../../types/response.types';
import type {
  ActivityListFilters,
  ActivityListMeta,
  ActivityListResponse,
  ActivityLogItem,
  ActivityMetaPayload,
} from '../types/activity.types';

interface MetaPayload {
  current_user_permissions?: string[];
}

interface ActivityApiMetaPayload {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

interface ActivityApiPayload {
  data?: Array<Record<string, unknown>>;
  meta?: ActivityApiMetaPayload;
}

const deriveModule = (action: string): string => {
  if (!action) return 'system';
  const normalized = action.replaceAll('.', '_');
  return normalized.split('_')[0] || 'system';
};

const deriveEntity = (action: string): string => {
  const normalized = action.replaceAll('.', '_');
  const parts = normalized.split('_');
  return parts.length > 1 ? parts.slice(1).join('_') : 'event';
};

const deriveStatus = (action: string, description: string): 'success' | 'warning' | 'error' => {
  const source = `${action} ${description}`.toLowerCase();
  if (source.includes('failed') || source.includes('error') || source.includes('denied')) return 'error';
  if (source.includes('revoked') || source.includes('deleted') || source.includes('expired')) return 'warning';
  return 'success';
};

const normalizeActivityItem = (entry: Record<string, unknown>, index: number): ActivityLogItem => {
  const action = String(entry.action ?? 'unknown');
  const description = String(entry.description ?? action.replaceAll('_', ' '));
  const meta = (entry.meta as Record<string, unknown> | undefined) ?? {};

  return {
    id: String(entry.id ?? `${action}-${index}`),
    user: (entry.user as { id?: number; name?: string; email?: string } | null) ?? null,
    action,
    module: deriveModule(action),
    entity: deriveEntity(action),
    description,
    status: deriveStatus(action, description),
    ip_address: (meta.ip_address as string | undefined) ?? null,
    created_at: (entry.created_at as string | undefined) ?? null,
    meta,
  };
};

/**
 * Activity module service.
 *
 * ARCHITECTURE NOTE:
 * The backend may expose activity through different endpoints over time.
 * This service isolates source-selection and normalization, so monitoring UI
 * remains stable while audit APIs evolve toward realtime timeline feeds.
 */
export const activityService = {
  async fetchActivity(filters: ActivityListFilters = {}): Promise<ActivityListResponse> {
    const response = await api.get<ActivityApiPayload>('/v1/activity', { params: filters });
    const payload = response as ApiResponse<ActivityApiPayload>;
    const rows = Array.isArray(payload.data?.data) ? payload.data.data : [];

    const meta: ActivityListMeta = {
      current_page: Number(payload.data?.meta?.current_page ?? 1),
      last_page: Number(payload.data?.meta?.last_page ?? 1),
      per_page: Number(payload.data?.meta?.per_page ?? filters.per_page ?? 10),
      total: Number(payload.data?.meta?.total ?? rows.length),
    };

    return {
      items: rows.map((entry, index) => normalizeActivityItem(entry, index)),
      meta,
    };
  },

  async fetchActivityMeta(): Promise<ActivityMetaPayload> {
    const response = await api.get<MetaPayload>('/v1/meta');

    return {
      current_user_permissions: response.data?.current_user_permissions ?? [],
    };
  },
};
