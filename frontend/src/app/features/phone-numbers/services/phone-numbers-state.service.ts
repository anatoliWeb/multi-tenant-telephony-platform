import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { PhoneNumbersApiService } from './phone-numbers-api.service';
import type {
  PhoneNumberAssignedUser,
  PhoneNumberFilters,
  PhoneNumberItem,
  PhoneNumberPaginationMeta,
  PhoneNumberUpsertPayload,
} from '../models/phone-number.model';

const DEFAULT_FILTERS: PhoneNumberFilters = {
  search: '',
  status: '',
  assigned: '',
  primary: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class PhoneNumbersStateService {
  private readonly phoneNumbersSubject = new BehaviorSubject<PhoneNumberItem[]>([]);
  private readonly activePhoneNumberSubject = new BehaviorSubject<PhoneNumberItem | null>(null);
  private readonly usersSubject = new BehaviorSubject<PhoneNumberAssignedUser[]>([]);
  private readonly filtersSubject = new BehaviorSubject<PhoneNumberFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<PhoneNumberPaginationMeta>({
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

  readonly phoneNumbers$ = this.phoneNumbersSubject.asObservable();
  readonly activePhoneNumber$ = this.activePhoneNumberSubject.asObservable();
  readonly users$ = this.usersSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly phoneNumbersApi: PhoneNumbersApiService) {}

  async init(): Promise<void> {
    await Promise.all([
      this.loadAssignmentOptions(),
      this.loadPhoneNumbers(),
    ]);
  }

  async loadPhoneNumbers(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.phoneNumbersApi.listPhoneNumbers(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.phoneNumbersSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activePhoneNumberId = this.activePhoneNumberSubject.value?.id ?? null;
      if (activePhoneNumberId && !this.phoneNumbersSubject.value.some((item) => item.id === activePhoneNumberId)) {
        this.activePhoneNumberSubject.next(null);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.phoneNumbersSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load phone numbers.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadAssignmentOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.phoneNumbersApi.assignmentOptions());
      this.usersSubject.next(Array.isArray(response.data?.users) ? response.data.users : []);
    } catch {
      this.usersSubject.next([]);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async openPhoneNumber(phoneNumberId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.phoneNumbersApi.getPhoneNumber(phoneNumberId));
      this.activePhoneNumberSubject.next(response.data ?? null);
    } catch (error) {
      this.activePhoneNumberSubject.next(null);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load phone number details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createPhoneNumber(payload: PhoneNumberUpsertPayload): Promise<PhoneNumberItem | null> {
    return this.persist(async () => {
      const response = await firstValueFrom(this.phoneNumbersApi.createPhoneNumber(payload));
      await this.loadPhoneNumbers();
      if (response.data?.id) {
        await this.openPhoneNumber(response.data.id);
      }

      return response.data ?? null;
    }, 'Failed to create phone number.');
  }

  async updatePhoneNumber(phoneNumberId: number, payload: PhoneNumberUpsertPayload): Promise<PhoneNumberItem | null> {
    return this.persist(async () => {
      const response = await firstValueFrom(this.phoneNumbersApi.updatePhoneNumber(phoneNumberId, payload));
      await this.loadPhoneNumbers();
      if (response.data?.id) {
        await this.openPhoneNumber(response.data.id);
      }

      return response.data ?? null;
    }, 'Failed to update phone number.');
  }

  async assignPhoneNumber(phoneNumberId: number, assignedUserId: number, isPrimary: boolean): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.assignPhoneNumber(phoneNumberId, assignedUserId, isPrimary));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to assign phone number.');
  }

  async unassignPhoneNumber(phoneNumberId: number): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.unassignPhoneNumber(phoneNumberId));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to unassign phone number.');
  }

  async setPrimary(phoneNumberId: number): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.setPrimary(phoneNumberId));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to set primary DID.');
  }

  async activate(phoneNumberId: number): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.activate(phoneNumberId));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to activate phone number.');
  }

  async suspend(phoneNumberId: number): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.suspend(phoneNumberId));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to suspend phone number.');
  }

  async release(phoneNumberId: number): Promise<void> {
    await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.release(phoneNumberId));
      await this.loadPhoneNumbers();
      await this.openPhoneNumber(phoneNumberId);
      return null;
    }, 'Failed to release phone number.');
  }

  async deletePhoneNumber(phoneNumberId: number): Promise<boolean> {
    const result = await this.persist(async () => {
      await firstValueFrom(this.phoneNumbersApi.deletePhoneNumber(phoneNumberId));
      if (this.activePhoneNumberSubject.value?.id === phoneNumberId) {
        this.activePhoneNumberSubject.next(null);
      }
      await this.loadPhoneNumbers();
      return true;
    }, 'Failed to delete phone number.');

    return result ?? false;
  }

  selectPhoneNumber(phoneNumber: PhoneNumberItem | null): void {
    this.activePhoneNumberSubject.next(phoneNumber);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadPhoneNumbers();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadPhoneNumbers();
  }

  async setAssigned(value: string): Promise<void> {
    this.patchFilters({ assigned: value, page: 1 });
    await this.loadPhoneNumbers();
  }

  async setPrimaryFilter(value: string): Promise<void> {
    this.patchFilters({ primary: value, page: 1 });
    await this.loadPhoneNumbers();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadPhoneNumbers();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.phoneNumbersSubject.next([]);
    this.activePhoneNumberSubject.next(null);
    this.usersSubject.next([]);
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

  private async persist<T>(callback: () => Promise<T>, fallbackError: string): Promise<T | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      return await callback();
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, fallbackError));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  private patchFilters(next: Partial<PhoneNumberFilters>): void {
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
