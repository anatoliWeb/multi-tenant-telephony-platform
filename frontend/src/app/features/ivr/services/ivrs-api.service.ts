import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  IvrAssignmentOptions,
  IvrFilters,
  IvrMenuItem,
  IvrMenuUpsertPayload,
  IvrOptionItem,
  IvrOptionUpsertPayload,
  IvrRoutePlan,
} from '../models/ivr.model';

@Injectable({ providedIn: 'root' })
export class IvrsApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listIvrMenus(filters: Partial<IvrFilters>) {
    return this.apiClient.get<IvrMenuItem[]>('/v1/ivr-menus', {
      params: this.toListParams(filters),
    });
  }

  getIvrMenu(ivrMenuId: number) {
    return this.apiClient.get<IvrMenuItem>(`/v1/ivr-menus/${ivrMenuId}`);
  }

  createIvrMenu(payload: IvrMenuUpsertPayload) {
    return this.apiClient.post<IvrMenuItem, IvrMenuUpsertPayload>('/v1/ivr-menus', payload);
  }

  updateIvrMenu(ivrMenuId: number, payload: IvrMenuUpsertPayload) {
    return this.apiClient.put<IvrMenuItem, IvrMenuUpsertPayload>(`/v1/ivr-menus/${ivrMenuId}`, payload);
  }

  deleteIvrMenu(ivrMenuId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/ivr-menus/${ivrMenuId}`);
  }

  options() {
    return this.apiClient.get<IvrAssignmentOptions>('/v1/ivr-menus/options');
  }

  listOptions(ivrMenuId: number) {
    return this.apiClient.get<IvrOptionItem[]>(`/v1/ivr-menus/${ivrMenuId}/options`);
  }

  createOption(ivrMenuId: number, payload: IvrOptionUpsertPayload) {
    return this.apiClient.post<IvrOptionItem, IvrOptionUpsertPayload>(`/v1/ivr-menus/${ivrMenuId}/options`, payload);
  }

  updateOption(ivrMenuId: number, optionId: number, payload: IvrOptionUpsertPayload) {
    return this.apiClient.put<IvrOptionItem, IvrOptionUpsertPayload>(`/v1/ivr-menus/${ivrMenuId}/options/${optionId}`, payload);
  }

  deleteOption(ivrMenuId: number, optionId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/ivr-menus/${ivrMenuId}/options/${optionId}`);
  }

  testRoute(ivrMenuId: number, payload: { input_type: string; digit?: string | null }) {
    return this.apiClient.post<IvrRoutePlan, { input_type: string; digit?: string | null }>(
      `/v1/ivr-menus/${ivrMenuId}/test-route`,
      payload,
    );
  }

  private toListParams(filters: Partial<IvrFilters>): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.search?.trim()) {
      params['search'] = filters.search.trim();
    }

    if (filters.status?.trim()) {
      params['status'] = filters.status.trim();
    }

    params['page'] = filters.page ?? 1;
    params['per_page'] = filters.per_page ?? 15;

    return params;
  }
}
