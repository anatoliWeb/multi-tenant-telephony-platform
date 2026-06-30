import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { IvrsApiService } from './ivrs-api.service';
import type {
  IvrAssignmentOptions,
  IvrFilters,
  IvrMenuItem,
  IvrMenuUpsertPayload,
  IvrOptionItem,
  IvrOptionUpsertPayload,
  IvrPaginationMeta,
  IvrRoutePlan,
} from '../models/ivr.model';

const DEFAULT_FILTERS: IvrFilters = {
  search: '',
  status: '',
  page: 1,
  per_page: 15,
};

@Injectable({ providedIn: 'root' })
export class IvrsStateService {
  private readonly menusSubject = new BehaviorSubject<IvrMenuItem[]>([]);
  private readonly activeMenuSubject = new BehaviorSubject<IvrMenuItem | null>(null);
  private readonly activeOptionsSubject = new BehaviorSubject<IvrOptionItem[]>([]);
  private readonly optionsSubject = new BehaviorSubject<IvrAssignmentOptions | null>(null);
  private readonly routePlanSubject = new BehaviorSubject<IvrRoutePlan | null>(null);
  private readonly filtersSubject = new BehaviorSubject<IvrFilters>({ ...DEFAULT_FILTERS });
  private readonly paginationSubject = new BehaviorSubject<IvrPaginationMeta>({
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

  readonly menus$ = this.menusSubject.asObservable();
  readonly activeMenu$ = this.activeMenuSubject.asObservable();
  readonly activeOptions$ = this.activeOptionsSubject.asObservable();
  readonly options$ = this.optionsSubject.asObservable();
  readonly routePlan$ = this.routePlanSubject.asObservable();
  readonly filters$ = this.filtersSubject.asObservable();
  readonly pagination$ = this.paginationSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly saving$ = this.savingSubject.asObservable();
  readonly detailLoading$ = this.detailLoadingSubject.asObservable();
  readonly optionsLoading$ = this.optionsLoadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();

  constructor(private readonly api: IvrsApiService) {}

  get activeMenu(): IvrMenuItem | null {
    return this.activeMenuSubject.value;
  }

  async init(): Promise<void> {
    await Promise.all([
      this.loadOptions(),
      this.loadMenus(),
    ]);
  }

  async loadMenus(): Promise<void> {
    const version = ++this.requestVersion;
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.listIvrMenus(this.filtersSubject.value));
      if (version !== this.requestVersion) {
        return;
      }

      this.menusSubject.next(Array.isArray(response.data) ? response.data : []);
      this.paginationSubject.next({
        current_page: Number(response.meta?.['current_page'] ?? this.filtersSubject.value.page),
        last_page: Number(response.meta?.['last_page'] ?? 1),
        per_page: Number(response.meta?.['per_page'] ?? this.filtersSubject.value.per_page),
        total: Number(response.meta?.['total'] ?? 0),
      });

      const activeMenuId = this.activeMenuSubject.value?.id ?? null;
      if (activeMenuId && !this.menusSubject.value.some((item) => item.id === activeMenuId)) {
        this.activeMenuSubject.next(null);
        this.activeOptionsSubject.next([]);
      }
    } catch (error) {
      if (version !== this.requestVersion) {
        return;
      }

      this.menusSubject.next([]);
      this.paginationSubject.next({
        current_page: this.filtersSubject.value.page,
        last_page: 1,
        per_page: this.filtersSubject.value.per_page,
        total: 0,
      });
      this.errorSubject.next(this.toSafeError(error, 'Failed to load IVR menus.'));
    } finally {
      if (version === this.requestVersion) {
        this.loadingSubject.next(false);
      }
    }
  }

  async loadOptions(): Promise<void> {
    this.optionsLoadingSubject.next(true);

    try {
      const response = await firstValueFrom(this.api.options());
      this.optionsSubject.next(response.data ?? null);
    } catch {
      this.optionsSubject.next(null);
    } finally {
      this.optionsLoadingSubject.next(false);
    }
  }

  async openMenu(menuId: number): Promise<void> {
    this.detailLoadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.getIvrMenu(menuId));
      const menu = response.data ?? null;
      this.activeMenuSubject.next(menu);
      this.activeOptionsSubject.next(menu?.options ?? []);
      if (!menu?.options && menu) {
        await this.loadMenuOptions(menu.id);
      }
    } catch (error) {
      this.activeMenuSubject.next(null);
      this.activeOptionsSubject.next([]);
      this.errorSubject.next(this.toSafeError(error, 'Failed to load IVR menu details.'));
    } finally {
      this.detailLoadingSubject.next(false);
    }
  }

  async createMenu(payload: IvrMenuUpsertPayload): Promise<IvrMenuItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.createIvrMenu(payload));
      await this.loadMenus();
      if (response.data?.id) {
        await this.openMenu(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to create IVR menu.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateMenu(menuId: number, payload: IvrMenuUpsertPayload): Promise<IvrMenuItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.updateIvrMenu(menuId, payload));
      await this.loadMenus();
      if (response.data?.id) {
        await this.openMenu(response.data.id);
      }

      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update IVR menu.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteMenu(menuId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.api.deleteIvrMenu(menuId));
      if (this.activeMenuSubject.value?.id === menuId) {
        this.activeMenuSubject.next(null);
        this.activeOptionsSubject.next([]);
      }
      await this.loadMenus();
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete IVR menu.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async createOption(menuId: number, payload: IvrOptionUpsertPayload): Promise<IvrOptionItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.createOption(menuId, payload));
      await this.refreshActiveMenu(menuId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to add IVR option.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async updateOption(menuId: number, optionId: number, payload: IvrOptionUpsertPayload): Promise<IvrOptionItem | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.updateOption(menuId, optionId, payload));
      await this.refreshActiveMenu(menuId);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to update IVR option.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async deleteOption(menuId: number, optionId: number): Promise<boolean> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await firstValueFrom(this.api.deleteOption(menuId, optionId));
      await this.refreshActiveMenu(menuId);
      return true;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to delete IVR option.'));
      return false;
    } finally {
      this.savingSubject.next(false);
    }
  }

  async testRoute(menuId: number, inputType = 'digit', digit?: string | null): Promise<IvrRoutePlan | null> {
    this.savingSubject.next(true);
    this.errorSubject.next(null);

    try {
      const response = await firstValueFrom(this.api.testRoute(menuId, { input_type: inputType, digit }));
      this.routePlanSubject.next(response.data ?? null);
      return response.data ?? null;
    } catch (error) {
      this.errorSubject.next(this.toSafeError(error, 'Failed to test IVR route.'));
      return null;
    } finally {
      this.savingSubject.next(false);
    }
  }

  selectMenu(menu: IvrMenuItem | null): void {
    this.activeMenuSubject.next(menu);
    this.activeOptionsSubject.next(menu?.options ?? []);
  }

  async setSearch(value: string): Promise<void> {
    this.patchFilters({ search: value, page: 1 });
    await this.loadMenus();
  }

  async setStatus(value: string): Promise<void> {
    this.patchFilters({ status: value, page: 1 });
    await this.loadMenus();
  }

  async setPage(page: number): Promise<void> {
    this.patchFilters({ page });
    await this.loadMenus();
  }

  resetForTenantChange(): void {
    this.requestVersion += 1;
    this.menusSubject.next([]);
    this.activeMenuSubject.next(null);
    this.activeOptionsSubject.next([]);
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

  private async refreshActiveMenu(menuId: number): Promise<void> {
    await this.loadMenus();
    await this.openMenu(menuId);
  }

  private async loadMenuOptions(menuId: number): Promise<void> {
    try {
      const response = await firstValueFrom(this.api.listOptions(menuId));
      this.activeOptionsSubject.next(response.data ?? []);
    } catch {
      this.activeOptionsSubject.next([]);
    }
  }

  private patchFilters(next: Partial<IvrFilters>): void {
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
