import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  PhoneNumberAssignmentOptions,
  PhoneNumberFilters,
  PhoneNumberItem,
  PhoneNumberUpsertPayload,
} from '../models/phone-number.model';

@Injectable({ providedIn: 'root' })
export class PhoneNumbersApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listPhoneNumbers(filters: Partial<PhoneNumberFilters>) {
    return this.apiClient.get<PhoneNumberItem[]>('/v1/phone-numbers', {
      params: this.toListParams(filters),
    });
  }

  getPhoneNumber(phoneNumberId: number) {
    return this.apiClient.get<PhoneNumberItem>(`/v1/phone-numbers/${phoneNumberId}`);
  }

  createPhoneNumber(payload: PhoneNumberUpsertPayload) {
    return this.apiClient.post<PhoneNumberItem, PhoneNumberUpsertPayload>('/v1/phone-numbers', payload);
  }

  updatePhoneNumber(phoneNumberId: number, payload: PhoneNumberUpsertPayload) {
    return this.apiClient.put<PhoneNumberItem, PhoneNumberUpsertPayload>(`/v1/phone-numbers/${phoneNumberId}`, payload);
  }

  deletePhoneNumber(phoneNumberId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/phone-numbers/${phoneNumberId}`);
  }

  assignmentOptions() {
    return this.apiClient.get<PhoneNumberAssignmentOptions>('/v1/phone-numbers/assignment-options');
  }

  assignPhoneNumber(phoneNumberId: number, assignedUserId: number, isPrimary: boolean) {
    return this.apiClient.post<PhoneNumberItem, { assigned_user_id: number; is_primary: boolean }>(
      `/v1/phone-numbers/${phoneNumberId}/assign`,
      { assigned_user_id: assignedUserId, is_primary: isPrimary },
    );
  }

  unassignPhoneNumber(phoneNumberId: number) {
    return this.apiClient.post<PhoneNumberItem, Record<string, never>>(`/v1/phone-numbers/${phoneNumberId}/unassign`, {});
  }

  setPrimary(phoneNumberId: number) {
    return this.apiClient.post<PhoneNumberItem, Record<string, never>>(`/v1/phone-numbers/${phoneNumberId}/set-primary`, {});
  }

  activate(phoneNumberId: number) {
    return this.apiClient.post<PhoneNumberItem, Record<string, never>>(`/v1/phone-numbers/${phoneNumberId}/activate`, {});
  }

  suspend(phoneNumberId: number) {
    return this.apiClient.post<PhoneNumberItem, Record<string, never>>(`/v1/phone-numbers/${phoneNumberId}/suspend`, {});
  }

  release(phoneNumberId: number) {
    return this.apiClient.post<PhoneNumberItem, Record<string, never>>(`/v1/phone-numbers/${phoneNumberId}/release`, {});
  }

  private toListParams(filters: Partial<PhoneNumberFilters>): Record<string, string | number> {
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

    if (filters.primary?.trim()) {
      params['primary'] = filters.primary.trim();
    }

    params['page'] = filters.page ?? 1;
    params['per_page'] = filters.per_page ?? 15;

    return params;
  }
}
