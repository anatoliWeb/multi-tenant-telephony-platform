import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  CallQueueAssignmentOptions,
  CallQueueFilters,
  CallQueueItem,
  CallQueueMemberUpsertPayload,
  CallQueueMemberItem,
  CallQueueRoutePlan,
  CallQueueUpsertPayload,
} from '../models/call-queue.model';

@Injectable({ providedIn: 'root' })
export class CallQueuesApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listCallQueues(filters: Partial<CallQueueFilters>) {
    return this.apiClient.get<CallQueueItem[]>('/v1/call-queues', {
      params: this.toListParams(filters),
    });
  }

  getCallQueue(callQueueId: number) {
    return this.apiClient.get<CallQueueItem>(`/v1/call-queues/${callQueueId}`);
  }

  createCallQueue(payload: CallQueueUpsertPayload) {
    return this.apiClient.post<CallQueueItem, CallQueueUpsertPayload>('/v1/call-queues', payload);
  }

  updateCallQueue(callQueueId: number, payload: CallQueueUpsertPayload) {
    return this.apiClient.put<CallQueueItem, CallQueueUpsertPayload>(`/v1/call-queues/${callQueueId}`, payload);
  }

  deleteCallQueue(callQueueId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/call-queues/${callQueueId}`);
  }

  options() {
    return this.apiClient.get<CallQueueAssignmentOptions>('/v1/call-queues/options');
  }

  listMembers(callQueueId: number) {
    return this.apiClient.get<CallQueueMemberItem[]>(`/v1/call-queues/${callQueueId}/members`);
  }

  createMember(callQueueId: number, payload: CallQueueMemberUpsertPayload) {
    return this.apiClient.post<CallQueueMemberItem, CallQueueMemberUpsertPayload>(`/v1/call-queues/${callQueueId}/members`, payload);
  }

  updateMember(callQueueId: number, memberId: number, payload: CallQueueMemberUpsertPayload) {
    return this.apiClient.put<CallQueueMemberItem, CallQueueMemberUpsertPayload>(`/v1/call-queues/${callQueueId}/members/${memberId}`, payload);
  }

  deleteMember(callQueueId: number, memberId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/call-queues/${callQueueId}/members/${memberId}`);
  }

  pauseMember(callQueueId: number, memberId: number, reason: string) {
    return this.apiClient.post<CallQueueMemberItem, { reason: string }>(`/v1/call-queues/${callQueueId}/members/${memberId}/pause`, { reason });
  }

  resumeMember(callQueueId: number, memberId: number) {
    return this.apiClient.post<CallQueueMemberItem, Record<string, never>>(`/v1/call-queues/${callQueueId}/members/${memberId}/resume`, {});
  }

  testRoute(callQueueId: number) {
    return this.apiClient.post<CallQueueRoutePlan, Record<string, never>>(`/v1/call-queues/${callQueueId}/test-route`, {});
  }

  private toListParams(filters: Partial<CallQueueFilters>): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.search?.trim()) {
      params['search'] = filters.search.trim();
    }

    if (filters.status?.trim()) {
      params['status'] = filters.status.trim();
    }

    if (filters.strategy?.trim()) {
      params['strategy'] = filters.strategy.trim();
    }

    params['page'] = filters.page ?? 1;
    params['per_page'] = filters.per_page ?? 15;

    return params;
  }
}
