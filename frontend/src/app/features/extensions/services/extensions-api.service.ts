import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  ExtensionAssignmentOptions,
  ExtensionFilters,
  ExtensionItem,
  ExtensionUpsertPayload,
} from '../models/extension.model';

@Injectable({ providedIn: 'root' })
export class ExtensionsApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listExtensions(filters: Partial<ExtensionFilters>) {
    return this.apiClient.get<ExtensionItem[]>('/api/v1/extensions', {
      params: this.toListParams(filters),
    });
  }

  getExtension(extensionId: number) {
    return this.apiClient.get<ExtensionItem>(`/api/v1/extensions/${extensionId}`);
  }

  createExtension(payload: ExtensionUpsertPayload) {
    return this.apiClient.post<ExtensionItem, ExtensionUpsertPayload>('/api/v1/extensions', payload);
  }

  updateExtension(extensionId: number, payload: ExtensionUpsertPayload) {
    return this.apiClient.put<ExtensionItem, ExtensionUpsertPayload>(`/api/v1/extensions/${extensionId}`, payload);
  }

  rotateCredentials(extensionId: number) {
    return this.apiClient.post<ExtensionItem, Record<string, never>>(`/api/v1/extensions/${extensionId}/rotate-credentials`, {});
  }

  deleteExtension(extensionId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/api/v1/extensions/${extensionId}`);
  }

  assignmentOptions() {
    return this.apiClient.get<ExtensionAssignmentOptions>('/api/v1/extensions/assignment-options');
  }

  private toListParams(filters: Partial<ExtensionFilters>): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.search?.trim()) {
      params['search'] = filters.search.trim();
    }

    if (filters.status?.trim()) {
      params['status'] = filters.status.trim();
    }

    if (filters.assigned?.trim()) {
      params['assigned'] = filters.assigned.trim();
    }

    params['page'] = filters.page ?? 1;
    params['per_page'] = filters.per_page ?? 15;

    return params;
  }
}
