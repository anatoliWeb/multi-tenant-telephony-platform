import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { CallQueuesApiService } from './call-queues-api.service';
import type {
  CallQueueAssignmentOptions,
  CallQueueFilters,
  CallQueueItem,
  CallQueueMemberItem,
  CallQueueMemberUpsertPayload,
  CallQueuePaginationMeta,
  CallQueueRoutePlan,
  CallQueueUpsertPayload,
} from '../models/call-queue.model';

const DEFAULT_FILTERS: CallQueueFilters = {
  search: '',
  status: '',
  strategy: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class CallQueuesStateService {
  private readonly queuesSubject = new BehaviorSubject<CallQueueItem[]>([]);
  private readonly activeQueueSubject = new BehaviorSubject<CallQueueItem | null>(null);
  private readonly activeQueueMembersSubject = new BehaviorSubject<CallQueueMemberItem[]>([]);
  private readonly optionsSubject = new BehaviorSubject<CallQueueAssignmentOptions | null>(null);
  private readonly routePlanSubject = new BehaviorSubject<CallQueueRoutePlan | null>(null);
  private readonly filtersSubject = new BehaviorSubject<CallQueueFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<CallQueuePaginationMeta>({
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

  readonly queues$ = this.queuesSubject.asObservable();
  readonly activeQueue$ = this.activeQueueSubject.asObservable();
  readonly activeQueueMembers$ = this.activeQueueMembersSubject.asObservable();
  readonly options$ = this.optionsSubject.asObservable();
  readonly routePlan$ = this.routePlanSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly callQueuesApi: CallQueuesApiService) {}

  get activeQueue(): CallQueueItem | null {
    return this.activeQueueSubject.value;
  }

  async init(): Promise<void> {
    await Promise.all([
      this.loadOptions(),
      this.loadCallQueues(),
    ]);
  }

  async loadCallQueues(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.listCallQueues(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.queuesSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeQueueId = this.activeQueueSubject.value?.id ?? null;
      if (activeQueueId && !this.queuesSubject.value.some((item) => item.id === activeQueueId)) {
        this.activeQueueSubject.next(null);
        this.activeQueueMembersSubject.next([]);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.queuesSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load call queues.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.callQueuesApi.options());
      this.optionsSubject.next(response.data ?? null);
    } catch {
      this.optionsSubject.next(null);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async openCallQueue(callQueueId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.getCallQueue(callQueueId));
      const queue = response.data ?? null;
      this.activeQueueSubject.next(queue);
      this.activeQueueMembersSubject.next(queue?.members ?? []);
    } catch (error) {
      this.activeQueueSubject.next(null);
      this.activeQueueMembersSubject.next([]);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load call queue details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createCallQueue(payload: CallQueueUpsertPayload): Promise<CallQueueItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.createCallQueue(payload));
      await this.loadCallQueues();
      if (response.data?.id) {
        await this.openCallQueue(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create call queue.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateCallQueue(callQueueId: number, payload: CallQueueUpsertPayload): Promise<CallQueueItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.updateCallQueue(callQueueId, payload));
      await this.loadCallQueues();
      if (response.data?.id) {
        await this.openCallQueue(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update call queue.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteCallQueue(callQueueId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.callQueuesApi.deleteCallQueue(callQueueId));
      if (this.activeQueueSubject.value?.id === callQueueId) {
        this.activeQueueSubject.next(null);
        this.activeQueueMembersSubject.next([]);
      }
      await this.loadCallQueues();
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete call queue.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async createMember(callQueueId: number, payload: CallQueueMemberUpsertPayload): Promise<CallQueueMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.createMember(callQueueId, payload));
      await this.refreshActiveCallQueue(callQueueId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to add call queue member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateMember(callQueueId: number, memberId: number, payload: CallQueueMemberUpsertPayload): Promise<CallQueueMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.updateMember(callQueueId, memberId, payload));
      await this.refreshActiveCallQueue(callQueueId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update call queue member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteMember(callQueueId: number, memberId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.callQueuesApi.deleteMember(callQueueId, memberId));
      await this.refreshActiveCallQueue(callQueueId);
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete call queue member.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async pauseMember(callQueueId: number, memberId: number, reason: string): Promise<CallQueueMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.pauseMember(callQueueId, memberId, reason));
      await this.refreshActiveCallQueue(callQueueId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to pause call queue member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async resumeMember(callQueueId: number, memberId: number): Promise<CallQueueMemberItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.resumeMember(callQueueId, memberId));
      await this.refreshActiveCallQueue(callQueueId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to resume call queue member.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async testRoute(callQueueId: number): Promise<CallQueueRoutePlan | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.callQueuesApi.testRoute(callQueueId));
      this.routePlanSubject.next(response.data ?? null);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to test call queue route.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  selectQueue(callQueue: CallQueueItem | null): void {
    this.activeQueueSubject.next(callQueue);
    this.activeQueueMembersSubject.next(callQueue?.members ?? []);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadCallQueues();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadCallQueues();
  }

  async setStrategy(value: string): Promise<void> {
    this.patchFilters({ strategy: value, page: 1 });
    await this.loadCallQueues();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadCallQueues();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.queuesSubject.next([]);
    this.activeQueueSubject.next(null);
    this.activeQueueMembersSubject.next([]);
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

  private async refreshActiveCallQueue(callQueueId: number): Promise<void> {
    await this.loadCallQueues();
    await this.openCallQueue(callQueueId);
  }

  private patchFilters(next: Partial<CallQueueFilters>): void {
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
