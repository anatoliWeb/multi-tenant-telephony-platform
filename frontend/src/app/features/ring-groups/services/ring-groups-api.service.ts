import { Injectable } from '@angular/core';
import { ApiClientService } from '../../../api/services/api-client.service';
import type {
  RingGroupAssignmentOptions,
  RingGroupFilters,
  RingGroupItem,
  RingGroupMemberItem,
  RingGroupMemberUpsertPayload,
  RingGroupRoutePlan,
  RingGroupUpsertPayload,
} from '../models/ring-group.model';

@Injectable({ providedIn: 'root' })
export class RingGroupsApiService {
  constructor(private readonly apiClient: ApiClientService) {}

  listRingGroups(filters: Partial<RingGroupFilters>) {
    return this.apiClient.get<RingGroupItem[]>('/v1/ring-groups', {
      params: this.toListParams(filters),
    });
  }

  getRingGroup(ringGroupId: number) {
    return this.apiClient.get<RingGroupItem>(`/v1/ring-groups/${ringGroupId}`);
  }

  createRingGroup(payload: RingGroupUpsertPayload) {
    return this.apiClient.post<RingGroupItem, RingGroupUpsertPayload>('/v1/ring-groups', payload);
  }

  updateRingGroup(ringGroupId: number, payload: RingGroupUpsertPayload) {
    return this.apiClient.put<RingGroupItem, RingGroupUpsertPayload>(`/v1/ring-groups/${ringGroupId}`, payload);
  }

  deleteRingGroup(ringGroupId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/ring-groups/${ringGroupId}`);
  }

  listMembers(ringGroupId: number) {
    return this.apiClient.get<RingGroupMemberItem[]>(`/v1/ring-groups/${ringGroupId}/members`);
  }

  createMember(ringGroupId: number, payload: RingGroupMemberUpsertPayload) {
    return this.apiClient.post<RingGroupMemberItem, RingGroupMemberUpsertPayload>(`/v1/ring-groups/${ringGroupId}/members`, payload);
  }

  updateMember(ringGroupId: number, memberId: number, payload: RingGroupMemberUpsertPayload) {
    return this.apiClient.put<RingGroupMemberItem, RingGroupMemberUpsertPayload>(`/v1/ring-groups/${ringGroupId}/members/${memberId}`, payload);
  }

  deleteMember(ringGroupId: number, memberId: number) {
    return this.apiClient.delete<{ deleted: boolean }>(`/v1/ring-groups/${ringGroupId}/members/${memberId}`);
  }

  testRoute(ringGroupId: number) {
    return this.apiClient.post<RingGroupRoutePlan, Record<string, never>>(`/v1/ring-groups/${ringGroupId}/test-route`, {});
  }

  options() {
    return this.apiClient.get<RingGroupAssignmentOptions>('/v1/ring-groups/options');
  }

  private toListParams(filters: Partial<RingGroupFilters>): Record<string, string | number> {
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
