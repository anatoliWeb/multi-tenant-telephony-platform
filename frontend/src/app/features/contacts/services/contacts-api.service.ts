import { Inject, Injectable } from '@angular/core';
import { APP_CONFIG, AppEnvironment } from '../../../core/tokens/app-config.token';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { ContactFilters, ContactItem, ContactTag, ContactUpsertPayload } from '../models/contact.model';

@Injectable({ providedIn: 'root' })
export class ContactsApiService {
  constructor(
    private readonly apiClient: ApiClientService,
    @Inject(APP_CONFIG) private readonly config: AppEnvironment,
  ) {}

  listContacts(filters: Partial<ContactFilters>) {
    return this.apiClient.get<ContactItem[]>('/v1/contacts', {
      params: this.toListParams(filters),
    });
  }

  getContact(contactId: number) {
    return this.apiClient.get<ContactItem>(`/v1/contacts/${contactId}`);
  }

  createContact(payload: ContactUpsertPayload) {
    return this.apiClient.post<ContactItem, ContactUpsertPayload>('/v1/contacts', payload);
  }

  updateContact(contactId: number, payload: ContactUpsertPayload) {
    return this.apiClient.put<ContactItem, ContactUpsertPayload>(`/v1/contacts/${contactId}`, payload);
  }

  deleteContact(contactId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/contacts/${contactId}`);
  }

  listTags() {
    return this.apiClient.get<ContactTag[]>('/v1/contact-tags');
  }

  exportContactsUrl(): string {
    const normalizedBase = this.config.apiBaseUrl.replace(/\/+$/, '');
    return `${normalizedBase}/v1/contacts/export`;
  }

  private toListParams(filters: Partial<ContactFilters>): Record<string, string | number | boolean> {
    const params: Record<string, string | number | boolean> = {};

    if (filters.search?.trim()) {
      params['search'] = filters.search.trim();
    }

    if (filters.status?.trim()) {
      params['status'] = filters.status.trim();
    }

    if (filters.tag?.trim()) {
      params['tag'] = filters.tag.trim();
    }

    params['page'] = filters.page ?? 1;
    params['per_page'] = filters.per_page ?? 15;
    params['sort'] = 'display_name';
    params['direction'] = 'asc';

    return params;
  }
}
