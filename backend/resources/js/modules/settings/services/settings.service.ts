import { api } from '../../../services/api/client';
import type {
  SettingsIndexPayload,
  SettingsListParams,
  SettingsPreloadPayload,
  SystemSettingRecord,
  UpsertSettingPayload,
} from '../types/settings.types';

/**
 * Settings API service.
 *
 * WHY THIS SERVICE EXISTS:
 * - keeps settings transport concerns away from components
 * - centralizes payload serialization for typed settings values
 * - provides one stable integration point for future cache/realtime hooks
 */
const toParams = (params: SettingsListParams): Record<string, unknown> => {
  const payload: Record<string, unknown> = {};

  if (params.search) payload.search = params.search;
  if (params.group) payload.group = params.group;
  if (params.channel) payload.channel = params.channel;
  if (params.type) payload.type = params.type;
  if (params.is_active) payload.is_active = params.is_active;
  if (params.is_public) payload.is_public = params.is_public;
  if (params.is_encrypted) payload.is_encrypted = params.is_encrypted;
  if (params.for_user_id) payload.for_user_id = params.for_user_id;
  if (params.page) payload.page = params.page;
  if (params.per_page) payload.per_page = params.per_page;

  return payload;
};

const normalizeValue = (value: unknown): unknown => {
  if (value === undefined) {
    return null;
  }

  return value;
};

const serializePayload = (payload: UpsertSettingPayload): UpsertSettingPayload => ({
  ...payload,
  value: normalizeValue(payload.value),
  default_value: normalizeValue(payload.default_value),
});

export const settingsService = {
  async fetchSettings(params: SettingsListParams = {}): Promise<SettingsIndexPayload> {
    const response = await api.get<SettingsIndexPayload>('/v1/settings', {
      params: toParams(params),
    });

    return response.data ?? {
      settings: [],
      effective: {},
      groups: [],
      types: ['string', 'integer', 'float', 'boolean', 'json', 'array', 'enum', 'color', 'select', 'textarea', 'toggle'],
    };
  },

  async fetchPreload(): Promise<SettingsPreloadPayload> {
    const response = await api.get<SettingsPreloadPayload>('/v1/settings/preload');

    return response.data ?? {
      channel: 'frontend',
      settings: {},
    };
  },

  async createSetting(payload: UpsertSettingPayload): Promise<SystemSettingRecord> {
    const response = await api.post<SystemSettingRecord, UpsertSettingPayload>(
      '/v1/settings',
      serializePayload(payload),
    );

    if (!response.data) {
      throw new Error('Settings API returned empty create payload');
    }

    return response.data;
  },

  async updateSetting(id: number, payload: Partial<UpsertSettingPayload>): Promise<SystemSettingRecord> {
    const response = await api.put<SystemSettingRecord, Partial<UpsertSettingPayload>>(
      `/v1/settings/${id}`,
      serializePayload(payload as UpsertSettingPayload),
    );

    if (!response.data) {
      throw new Error('Settings API returned empty update payload');
    }

    return response.data;
  },

  async deleteSetting(id: number): Promise<void> {
    await api.delete(`/v1/settings/${id}`);
  },
};
