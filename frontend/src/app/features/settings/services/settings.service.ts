import { Injectable } from '@angular/core';
import { map } from 'rxjs';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { ApiResponse } from '../../../api/models/api-response.model';
import type { SettingItem, SettingsFilters, SettingsListPayload, SettingUpsertPayload } from '../models/settings.model';

@Injectable({ providedIn: 'root' })
export class SettingsService {
  constructor(private readonly apiClient: ApiClientService) {}

  list(filters: SettingsFilters) {
    return this.apiClient.get<SettingsListPayload>('/v1/settings', {
      params: {
        search: filters.search,
        group: filters.group,
        type: filters.type,
        is_active: filters.is_active,
        channel: filters.channel,
        is_public: filters.is_public,
        is_encrypted: filters.is_encrypted,
        page: filters.page,
        per_page: filters.per_page,
      },
    }).pipe(
      map((response: ApiResponse<SettingsListPayload>) => this.normalizeListPayload(response.data)),
    );
  }

  create(payload: SettingUpsertPayload) {
    return this.apiClient.post<SettingItem, SettingUpsertPayload>('/v1/settings', payload).pipe(
      map((response: ApiResponse<SettingItem>) => response.data as SettingItem),
    );
  }

  update(id: number, payload: Partial<SettingUpsertPayload>) {
    return this.apiClient.post<SettingItem, Partial<SettingUpsertPayload>>(`/v1/settings/${id}?_method=PATCH`, payload).pipe(
      map((response: ApiResponse<SettingItem>) => response.data as SettingItem),
    );
  }

  delete(id: number) {
    return this.apiClient.post<{ deleted: boolean }, Record<string, never>>(`/v1/settings/${id}?_method=DELETE`, {}).pipe(
      map((response: ApiResponse<{ deleted: boolean }>) => response.data?.deleted ?? false),
    );
  }

  /**
   * Settings payload normalization.
   *
   * WHY:
   * Backend responses can evolve between flat arrays and paginator-like shapes.
   * Normalizing once in the service keeps feature rendering stable and prevents
   * partial/empty table UI when optional fields are absent.
   */
  private normalizeListPayload(payload: unknown): SettingsListPayload {
    const source = (payload ?? {}) as Record<string, unknown>;
    const settingsRaw = source['settings'];
    const settings = Array.isArray(settingsRaw)
      ? (settingsRaw as SettingItem[])
      : Array.isArray((settingsRaw as { data?: unknown[] } | undefined)?.data)
        ? (((settingsRaw as { data: unknown[] }).data ?? []) as SettingItem[])
        : [];

    const metaRaw = (source['meta'] ?? {}) as Partial<SettingsListPayload['meta']>;
    const meta: SettingsListPayload['meta'] = {
      current_page: Number(metaRaw.current_page ?? 1),
      last_page: Number(metaRaw.last_page ?? 1),
      per_page: Number(metaRaw.per_page ?? 15),
      total: Number(metaRaw.total ?? settings.length),
    };

    return {
      settings,
      effective: (source['effective'] as SettingsListPayload['effective']) ?? {},
      groups: (source['groups'] as string[]) ?? [],
      types: (source['types'] as string[]) ?? [],
      meta,
    };
  }
}
