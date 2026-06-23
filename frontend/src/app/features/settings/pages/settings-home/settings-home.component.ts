import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { RealtimeService } from '../../../../realtime/services/realtime.service';
import { TranslationFacadeService } from '../../../../i18n/services/translation-facade.service';
import { LocaleService } from '../../../../i18n/services/locale.service';
import { AppLoadingService } from '../../../../core/services/app-loading.service';
import { SettingsService } from '../../services/settings.service';
import type { SettingItem, SettingsFilters, SettingsListMeta, SettingsListPayload, SettingUpsertPayload } from '../../models/settings.model';
import type { SelectFilterOption } from '../../../../shared/components/select-filter/select-filter.component';

@Component({
  selector: 'app-settings-home',
  templateUrl: './settings-home.component.html',
  styleUrls: ['./settings-home.component.scss'],
  standalone: false,
})
export class SettingsHomeComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private refreshInFlight: Promise<void> | null = null;
  private lastRefreshKey = '';

  loading = false;
  saving = false;
  items: SettingItem[] = [];
  effective: SettingsListPayload['effective'] = {};
  groups: string[] = [];
  types: string[] = [];
  locales: string[] = [];
  meta: SettingsListMeta = { current_page: 1, last_page: 1, per_page: 15, total: 0 };
  filters: SettingsFilters = {
    search: '',
    group: '',
    type: '',
    is_active: '',
    channel: '',
    is_public: '',
    is_encrypted: '',
    page: 1,
    per_page: 15,
  };

  selected: SettingItem | null = null;
  modalOpen = false;
  modalMode: 'create' | 'edit' | 'view' = 'view';
  realtimeConnected = false;
  readonly activeOptions: SelectFilterOption[] = [
    { value: 'true', labelKey: 'common.filters.active' },
    { value: 'false', labelKey: 'common.filters.inactive' },
  ];
  readonly channelOptions: SelectFilterOption[] = [
    { value: 'frontend', labelKey: 'common.filters.frontend' },
    { value: 'backend', labelKey: 'common.filters.backend' },
  ];
  readonly visibilityOptions: SelectFilterOption[] = [
    { value: 'true', labelKey: 'settings.filters.publicOnly' },
    { value: 'false', labelKey: 'settings.filters.privateOnly' },
  ];
  readonly encryptionOptions: SelectFilterOption[] = [
    { value: 'true', labelKey: 'settings.filters.encryptedOnly' },
    { value: 'false', labelKey: 'settings.filters.plainOnly' },
  ];

  constructor(
    private readonly settingsService: SettingsService,
    private readonly permissionService: PermissionService,
    private readonly realtimeService: RealtimeService,
    private readonly t: TranslationFacadeService,
    private readonly localeService: LocaleService,
    private readonly appLoading: AppLoadingService,
  ) {
    this.locales = [...this.localeService.enabledLocales];
  }

  get canCreate(): boolean {
    return this.permissionService.can('settings.create') || this.permissionService.hasRole('admin');
  }
  get canEdit(): boolean {
    return this.permissionService.can('settings.edit') || this.permissionService.hasRole('admin');
  }
  get canDelete(): boolean {
    return this.permissionService.can('settings.delete') || this.permissionService.hasRole('admin');
  }

  ngOnInit(): void {
    this.realtimeService.status$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((state) => {
        this.realtimeConnected = state.connected;
      });
    void this.refresh();
  }

  async refresh(): Promise<void> {
    const refreshKey = JSON.stringify(this.filters);
    if (this.refreshInFlight && this.lastRefreshKey === refreshKey) {
      return this.refreshInFlight;
    }

    this.loading = true;
    this.appLoading.show('common.page.loading', 'page');
    this.lastRefreshKey = refreshKey;
    this.refreshInFlight = (async () => {
      try {
        const payload = await firstValueFrom(this.settingsService.list(this.filters));
        this.items = payload.settings;
        this.effective = payload.effective;
        this.groups = payload.groups;
        this.types = payload.types;
        this.meta = payload.meta;
      } finally {
        this.loading = false;
        this.appLoading.hide();
        this.refreshInFlight = null;
      }
    })();

    return this.refreshInFlight;
  }

  onFiltersChange(change: Partial<SettingsFilters>): void {
    const nextFilters = { ...this.filters, ...change, page: 1 };
    if (JSON.stringify(nextFilters) === JSON.stringify(this.filters)) {
      return;
    }
    this.filters = nextFilters;
    void this.refresh();
  }

  goToPage(page: number): void {
    if (page === this.filters.page) {
      return;
    }
    this.filters = { ...this.filters, page };
    void this.refresh();
  }

  openCreate(): void {
    this.selected = null;
    this.modalMode = 'create';
    this.modalOpen = true;
  }

  openEdit(item: SettingItem): void {
    this.selected = item;
    this.modalMode = 'edit';
    this.modalOpen = true;
  }

  openView(item: SettingItem): void {
    this.selected = item;
    this.modalMode = 'view';
    this.modalOpen = true;
  }

  closeModal(): void {
    this.modalOpen = false;
  }

  async persist(payload: SettingUpsertPayload): Promise<void> {
    this.saving = true;
    this.appLoading.show('common.submit.saving', 'submit');
    try {
      if (this.modalMode === 'create') {
        await firstValueFrom(this.settingsService.create(payload));
      } else if (this.selected) {
        await firstValueFrom(this.settingsService.update(this.selected.id, payload));
      }
      this.closeModal();
      await this.refresh();
    } finally {
      this.saving = false;
      this.appLoading.hide();
    }
  }

  async remove(item: SettingItem): Promise<void> {
    if (!confirm(this.t.t('settings.confirmDelete', `Delete setting "${item.label}"?`))) return;
    this.appLoading.show('common.submit.saving', 'submit');
    await firstValueFrom(this.settingsService.delete(item.id));
    try {
      await this.refresh();
    } finally {
      this.appLoading.hide();
    }
  }

  formatEffective(key: string): string {
    const value = this.effective[key]?.value;
    if (value === null || value === undefined) return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }

  mapStringOptions(values: string[]): SelectFilterOption[] {
    return values.map((value) => ({ value, label: value }));
  }

  onBooleanFilterChange(key: 'is_active' | 'is_public' | 'is_encrypted', value: string): void {
    const normalized = value === 'true' || value === 'false' ? value : '';
    this.onFiltersChange({ [key]: normalized } as Partial<SettingsFilters>);
  }

  onChannelChange(value: string): void {
    const normalized = value === 'frontend' || value === 'backend' ? value : '';
    this.onFiltersChange({ channel: normalized });
  }

  trackById(_: number, item: SettingItem): number {
    return item.id;
  }
}
