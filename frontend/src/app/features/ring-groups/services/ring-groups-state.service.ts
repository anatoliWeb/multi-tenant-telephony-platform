import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { RingGroupsApiService } from './ring-groups-api.service';
import type {
  RingGroupAssignmentOptions,
  RingGroupFilters,
  RingGroupItem,
  RingGroupMemberItem,
  RingGroupMemberUpsertPayload,
  RingGroupPaginationMeta,
  RingGroupRoutePlan,
  RingGroupUpsertPayload,
} from '../models/ring-group.model';

const DEFAULT_FILTERS: RingGroupFilters = {
  search: '',
  status: '',
  strategy: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class RingGroupsStateService {
  private readonly ringGroupsSubject = new BehaviorSubject<RingGroupItem[]>([]);
  private readonly activeRingGroupSubject = new BehaviorSubject<RingGroupItem | null>(null);
  private readonly activeRingGroupMembersSubject = new BehaviorSubject<RingGroupMemberItem[]>([]);
  private readonly optionsSubject = new BehaviorSubject<RingGroupAssignmentOptions | null>(null);
  private readonly routePlanSubject = new BehaviorSubject<RingGroupRoutePlan | null>(null);
  private readonly filtersSubject = new BehaviorSubject<RingGroupFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<RingGroupPaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_FILTERS.per_page,
    total: 0,
  });
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly savingSubject = new BehaviorSubject<boolean>(false);
  private readonly detailLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly optionsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private requestVersion = 0;

  readonly ringGroups$ = this.ringGroupsSubject.asObservable();
  readonly activeRingGroup$ = this.activeRingGroupSubject.asObservable();
  readonly activeRingGroupMembers$ = this.activeRingGroupMembersSubject.asObservable();
  readonly options$ = this.optionsSubject.asObservable();
  readonly routePlan$ = this.routePlanSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly ringGroupsApi: RingGroupsApiService) {}

  get activeRingGroup(): RingGroupItem | null {
    return this.activeRingGroupSubject.value;
  }

  async init(): Promise<void> {
    await Promise.all([
      this.loadOptions(),
      this.loadRingGroups(),
    ]);
  }

  async loadRingGroups(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.listRingGroups(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.ringGroupsSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeRingGroupId = this.activeRingGroupSubject.value?.id ?? null;
      if (activeRingGroupId && !this.ringGroupsSubject.value.some((item) => item.id === activeRingGroupId)) {
        this.activeRingGroupSubject.next(null);
        this.activeRingGroupMembersSubject.next([]);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.ringGroupsSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load ring groups.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.options());
      this.optionsSubject.next(response.data ?? null);
    } catch {
      this.optionsSubject.next(null);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async openRingGroup(ringGroupId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.getRingGroup(ringGroupId));
      const ringGroup = response.data ?? null;
      this.activeRingGroupSubject.next(ringGroup);
      this.activeRingGroupMembersSubject.next(ringGroup?.members ?? []);
    } catch (error) {
      this.activeRingGroupSubject.next(null);
      this.activeRingGroupMembersSubject.next([]);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load ring group details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createRingGroup(payload: RingGroupUpsertPayload): Promise<RingGroupItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.createRingGroup(payload));
      await this.loadRingGroups();
      if (response.data?.id) {
        await this.openRingGroup(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create ring group.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateRingGroup(ringGroupId: number, payload: RingGroupUpsertPayload): Promise<RingGroupItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.updateRingGroup(ringGroupId, payload));
      await this.loadRingGroups();
      if (response.data?.id) {
        await this.openRingGroup(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update ring group.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteRingGroup(ringGroupId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.ringGroupsApi.deleteRingGroup(ringGroupId));
      if (this.activeRingGroupSubject.value?.id === ringGroupId) {
        this.activeRingGroupSubject.next(null);
        this.activeRingGroupMembersSubject.next([]);
      }
      await this.loadRingGroups();
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete ring group.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async createMember(ringGroupId: number, payload: RingGroupMemberUpsertPayload): Promise<RingGroupMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.createMember(ringGroupId, payload));
      await this.refreshActiveRingGroup(ringGroupId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to add ring group member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateMember(ringGroupId: number, memberId: number, payload: RingGroupMemberUpsertPayload): Promise<RingGroupMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.updateMember(ringGroupId, memberId, payload));
      await this.refreshActiveRingGroup(ringGroupId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update ring group member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteMember(ringGroupId: number, memberId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.ringGroupsApi.deleteMember(ringGroupId, memberId));
      await this.refreshActiveRingGroup(ringGroupId);
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete ring group member.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async testRoute(ringGroupId: number): Promise<RingGroupRoutePlan | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.ringGroupsApi.testRoute(ringGroupId));
      this.routePlanSubject.next(response.data ?? null);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to test ring group route.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  selectRingGroup(ringGroup: RingGroupItem | null): void {
    this.activeRingGroupSubject.next(ringGroup);
    this.activeRingGroupMembersSubject.next(ringGroup?.members ?? []);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadRingGroups();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadRingGroups();
  }

  async setStrategy(value: string): Promise<void> {
    this.patchFilters({ strategy: value, page: 1 });
    await this.loadRingGroups();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadRingGroups();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.ringGroupsSubject.next([]);
    this.activeRingGroupSubject.next(null);
    this.activeRingGroupMembersSubject.next([]);
    this.optionsSubject.next(null);
    this.routePlanSubject.next(null);
    this.filtersSubject.next({ ...DEFAULT_FILTERS });
    this.paginationSubject.next({
      current_page: 1,
      last_page: 1,
      per_page: DEFAULT_FILTERS.per_page,
      total: 0,
    });
    this.loadingSubject.next(false);
    this.savingSubject.next(false);
    this.detailLoadingSubject.next(false);
    this.optionsLoadingSubject.next(false);
    this.errorSubject.next(null);
  }

  private async refreshActiveRingGroup(ringGroupId: number): Promise<void> {
    await this.loadRingGroups();
    await this.openRingGroup(ringGroupId);
  }

  private patchFilters(next: Partial<RingGroupFilters>): void {
    this.filtersSubject.next({
      ...this.filtersSubject.value,
      ...next,
    });
  }

  private toSafeError(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'message' in error) {
      const message = String((error as { message?: unknown }).message ?? '').trim();
      if (message.length > 0) {
        return message;
      }
    }

    return fallback;
  }
}
