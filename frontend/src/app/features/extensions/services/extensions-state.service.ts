import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { ExtensionsApiService } from './extensions-api.service';
import type {
  ExtensionAssignmentContact,
  ExtensionAssignmentUser,
  ExtensionFilters,
  ExtensionItem,
  ExtensionPaginationMeta,
  ExtensionUpsertPayload,
} from '../models/extension.model';

const DEFAULT_FILTERS: ExtensionFilters = {
  search: '',
  status: '',
  assigned: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class ExtensionsStateService {
  private readonly extensionsSubject = new BehaviorSubject<ExtensionItem[]>([]);
  private readonly activeExtensionSubject = new BehaviorSubject<ExtensionItem | null>(null);
  private readonly usersSubject = new BehaviorSubject<ExtensionAssignmentUser[]>([]);
  private readonly contactsSubject = new BehaviorSubject<ExtensionAssignmentContact[]>([]);
  private readonly filtersSubject = new BehaviorSubject<ExtensionFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<ExtensionPaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_FILTERS.per_page,
    total: 0,
  });
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly savingSubject = new BehaviorSubject<boolean>(false);
  private readonly detailLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly optionsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly latestSecretSubject = new BehaviorSubject<string | null>(null);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private requestVersion = 0;

  readonly extensions$ = this.extensionsSubject.asObservable();
  readonly activeExtension$ = this.activeExtensionSubject.asObservable();
  readonly users$ = this.usersSubject.asObservable();
  readonly contacts$ = this.contactsSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly latestSecret$ = this.latestSecretSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly extensionsApi: ExtensionsApiService) {}

  get activeExtension(): ExtensionItem | null {
    return this.activeExtensionSubject.value;
  }

  async init(): Promise<void> {
    await Promise.all([
      this.loadAssignmentOptions(),
      this.loadExtensions(),
    ]);
  }

  async loadExtensions(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.extensionsApi.listExtensions(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.extensionsSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeExtensionId = this.activeExtensionSubject.value?.id ?? null;
      if (activeExtensionId && !this.extensionsSubject.value.some((item) => item.id === activeExtensionId)) {
        this.activeExtensionSubject.next(null);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.extensionsSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load extensions.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadAssignmentOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.extensionsApi.assignmentOptions());
      this.usersSubject.next(Array.isArray(response.data?.users) ? response.data.users : []);
      this.contactsSubject.next(Array.isArray(response.data?.contacts) ? response.data.contacts : []);
    } catch {
      this.usersSubject.next([]);
      this.contactsSubject.next([]);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async openExtension(extensionId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.extensionsApi.getExtension(extensionId));
      this.activeExtensionSubject.next(response.data ?? null);
    } catch (error) {
      this.activeExtensionSubject.next(null);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load extension details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createExtension(payload: ExtensionUpsertPayload): Promise<ExtensionItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.extensionsApi.createExtension(payload));
      this.latestSecretSubject.next(response.data?.plain_secret ?? null);
      await this.loadExtensions();
      if (response.data?.id) {
        await this.openExtension(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create extension.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateExtension(extensionId: number, payload: ExtensionUpsertPayload): Promise<ExtensionItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.extensionsApi.updateExtension(extensionId, payload));
      await this.loadExtensions();
      if (response.data?.id) {
        await this.openExtension(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update extension.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async rotateCredentials(extensionId: number): Promise<void> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.extensionsApi.rotateCredentials(extensionId));
      this.latestSecretSubject.next(response.data?.plain_secret ?? null);
      await this.loadExtensions();
      await this.openExtension(extensionId);
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to rotate credentials.'));
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteExtension(extensionId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.extensionsApi.deleteExtension(extensionId));
      if (this.activeExtensionSubject.value?.id === extensionId) {
        this.activeExtensionSubject.next(null);
      }
      await this.loadExtensions();
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete extension.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  selectExtension(extension: ExtensionItem | null): void {
    this.activeExtensionSubject.next(extension);
  }

  dismissLatestSecret(): void {
    this.latestSecretSubject.next(null);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadExtensions();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadExtensions();
  }

  async setAssigned(value: string): Promise<void> {
    this.patchFilters({ assigned: value, page: 1 });
    await this.loadExtensions();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadExtensions();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.extensionsSubject.next([]);
    this.activeExtensionSubject.next(null);
    this.usersSubject.next([]);
    this.contactsSubject.next([]);
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
    this.latestSecretSubject.next(null);
    this.errorSubject.next(null);
  }

  private patchFilters(next: Partial<ExtensionFilters>): void {
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
