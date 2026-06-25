import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { ContactsApiService } from './contacts-api.service';
import type {
  ContactFilters,
  ContactItem,
  ContactPaginationMeta,
  ContactTag,
  ContactUpsertPayload,
} from '../models/contact.model';

const DEFAULT_FILTERS: ContactFilters = {
  search: '',
  status: '',
  tag: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class ContactsStateService {
  private readonly contactsSubject = new BehaviorSubject<ContactItem[]>([]);
  private readonly tagsSubject = new BehaviorSubject<ContactTag[]>([]);
  private readonly activeContactSubject = new BehaviorSubject<ContactItem | null>(null);
  private readonly filtersSubject = new BehaviorSubject<ContactFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<ContactPaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: DEFAULT_FILTERS.per_page,
    total: 0,
  });
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly savingSubject = new BehaviorSubject<boolean>(false);
  private readonly tagsLoadingSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private readonly detailLoadingSubject = new BehaviorSubject<boolean>(false);
  private requestVersion = 0;

  readonly contacts$ = this.contactsSubject.asObservable();
  readonly tags$ = this.tagsSubject.asObservable();
  readonly activeContact$ = this.activeContactSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly tagsLoading$ = this.tagsLoadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();

  constructor(private readonly contactsApi: ContactsApiService) {}

  get activeContact(): ContactItem | null {
    return this.activeContactSubject.value;
  }

  get filters(): ContactFilters {
    return this.filtersSubject.value;
  }

  async init(): Promise<void> {
    await Promise.all([
      this.loadTags(),
      this.loadContacts(),
    ]);
  }

  async loadContacts(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.contactsApi.listContacts(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.contactsSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page ?? 1),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeContactId = this.activeContactSubject.value?.id ?? null;
      if (activeContactId && !this.contactsSubject.value.some((contact) => contact.id === activeContactId)) {
        this.activeContactSubject.next(null);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.contactsSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load contacts.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadTags(): Promise<void> {
    this.tagsLoadingSubject.next(true);
    try {
      const response = await firstValueFrom(this.contactsApi.listTags());
      this.tagsSubject.next(Array.isArray(response.data) ? response.data : []);
    } catch {
      this.tagsSubject.next([]);
    } finally {
      this.tagsLoadingSubject.next(false);
    }
  }

  async openContact(contactId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.contactsApi.getContact(contactId));
      this.activeContactSubject.next(response.data ?? null);
    } catch (error) {
      this.activeContactSubject.next(null);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load contact details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createContact(payload: ContactUpsertPayload): Promise<ContactItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.contactsApi.createContact(payload));
      await this.loadContacts();
      if (response.data?.id) {
        await this.openContact(response.data.id);
      }
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create contact.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateContact(contactId: number, payload: ContactUpsertPayload): Promise<ContactItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);
    try {
      const response = await firstValueFrom(this.contactsApi.updateContact(contactId, payload));
      await this.loadContacts();
      if (response.data?.id) {
        await this.openContact(response.data.id);
      }
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update contact.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteContact(contactId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);
    try {
      await firstValueFrom(this.contactsApi.deleteContact(contactId));
      if (this.activeContactSubject.value?.id === contactId) {
        this.activeContactSubject.next(null);
      }
      await this.loadContacts();
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete contact.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  selectContact(contact: ContactItem | null): void {
    this.activeContactSubject.next(contact);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadContacts();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadContacts();
  }

  async setTag(value: string): Promise<void> {
    this.patchFilters({ tag: value, page: 1 });
    await this.loadContacts();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadContacts();
  }

  async refresh(): Promise<void> {
    await this.loadContacts();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.contactsSubject.next([]);
    this.tagsSubject.next([]);
    this.activeContactSubject.next(null);
    this.filtersSubject.next({ ...DEFAULT_FILTERS });
    this.paginationSubject.next({
      current_page: 1,
      last_page: 1,
      per_page: DEFAULT_FILTERS.per_page,
      total: 0,
    });
    this.loadingSubject.next(false);
    this.savingSubject.next(false);
    this.tagsLoadingSubject.next(false);
    this.detailLoadingSubject.next(false);
    this.errorSubject.next(null);
  }

  exportContacts(): void {
    window.open(this.contactsApi.exportContactsUrl(), '_blank', 'noopener');
  }

  private patchFilters(next: Partial<ContactFilters>): void {
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
