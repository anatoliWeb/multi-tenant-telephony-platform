import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import { SharedModule } from '../../../../shared/shared.module';
import { IvrsStateService } from '../../services/ivrs-state.service';
import type {
  IvrAssignmentOptions,
  IvrMenuItem,
  IvrOptionItem,
  IvrMenuUpsertPayload,
  IvrOptionUpsertPayload,
  IvrRoutePlan,
  IvrMenuStatus,
} from '../../models/ivr.model';
import { IvrMenuUpsertModalComponent } from '../../components/ivr-menu-upsert-modal.component';
import { IvrOptionUpsertModalComponent } from '../../components/ivr-option-upsert-modal.component';

@Component({
  selector: 'app-ivr-shell',
  templateUrl: './ivr-shell.component.html',
  styleUrls: ['./ivr-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, IvrMenuUpsertModalComponent, IvrOptionUpsertModalComponent],
})
export class IvrShellComponent implements OnInit {
  readonly menus$;
  readonly activeMenu$;
  readonly activeOptions$;
  readonly options$;
  readonly routePlan$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly detailLoading$;
  readonly optionsLoading$;
  readonly error$;

  isMenuModalOpen = false;
  menuModalMode: 'create' | 'edit' = 'create';
  menuModalItem: IvrMenuItem | null = null;

  isOptionModalOpen = false;
  optionModalMode: 'create' | 'edit' = 'create';
  optionModalItem: IvrOptionItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canManageOptions: boolean;
  readonly canTestRoute: boolean;
  hasTenant = false;

  constructor(
    private readonly ivrState: IvrsStateService,
    private readonly permissionService: PermissionService,
    public readonly tenantContext: TenantContextService,
  ) {
    this.menus$ = this.ivrState.menus$;
    this.activeMenu$ = this.ivrState.activeMenu$;
    this.activeOptions$ = this.ivrState.activeOptions$;
    this.options$ = this.ivrState.options$;
    this.routePlan$ = this.ivrState.routePlan$;
    this.filters$ = this.ivrState.filters$;
    this.pagination$ = this.ivrState.pagination$;
    this.loading$ = this.ivrState.loading$;
    this.saving$ = this.ivrState.saving$;
    this.detailLoading$ = this.ivrState.detailLoading$;
    this.optionsLoading$ = this.ivrState.optionsLoading$;
    this.error$ = this.ivrState.error$;
    this.canCreate = this.permissionService.hasPermission('ivr.create');
    this.canUpdate = this.permissionService.hasPermission('ivr.update');
    this.canDelete = this.permissionService.hasPermission('ivr.delete');
    this.canManageOptions = this.permissionService.hasPermission('ivr.manage_options');
    this.canTestRoute = this.permissionService.hasPermission('ivr.test_route');
  }

  ngOnInit(): void {
    this.hasTenant = this.tenantContext.hasTenant();
    if (!this.hasTenant) {
      return;
    }

    void this.ivrState.init();
  }

  async selectMenu(menu: IvrMenuItem): Promise<void> {
    this.ivrState.selectMenu(menu);
    await this.ivrState.openMenu(menu.id);
  }

  openCreateMenu(): void {
    this.menuModalMode = 'create';
    this.menuModalItem = null;
    this.isMenuModalOpen = true;
  }

  openEditMenu(menu: IvrMenuItem): void {
    this.menuModalMode = 'edit';
    this.menuModalItem = menu;
    this.isMenuModalOpen = true;
  }

  closeMenuModal(): void {
    this.isMenuModalOpen = false;
    this.menuModalItem = null;
  }

  async saveMenu(payload: IvrMenuUpsertPayload): Promise<void> {
    const result = this.menuModalMode === 'edit' && this.menuModalItem
      ? await this.ivrState.updateMenu(this.menuModalItem.id, payload)
      : await this.ivrState.createMenu(payload);

    if (result) {
      this.closeMenuModal();
    }
  }

  openCreateOption(): void {
    this.optionModalMode = 'create';
    this.optionModalItem = null;
    this.isOptionModalOpen = true;
  }

  openEditOption(option: IvrOptionItem): void {
    this.optionModalMode = 'edit';
    this.optionModalItem = option;
    this.isOptionModalOpen = true;
  }

  closeOptionModal(): void {
    this.isOptionModalOpen = false;
    this.optionModalItem = null;
  }

  async saveOption(payload: IvrOptionUpsertPayload): Promise<void> {
    const activeMenu = this.ivrState.activeMenu;
    if (!activeMenu) {
      return;
    }

    const result = this.optionModalMode === 'edit' && this.optionModalItem
      ? await this.ivrState.updateOption(activeMenu.id, this.optionModalItem.id, payload)
      : await this.ivrState.createOption(activeMenu.id, payload);

    if (result) {
      this.closeOptionModal();
    }
  }

  async deleteMenu(menu: IvrMenuItem): Promise<void> {
    if (!window.confirm('Delete selected IVR menu?')) {
      return;
    }

    await this.ivrState.deleteMenu(menu.id);
  }

  async deleteOption(option: IvrOptionItem): Promise<void> {
    const activeMenu = this.ivrState.activeMenu;
    if (!activeMenu || !window.confirm('Delete selected IVR option?')) {
      return;
    }

    await this.ivrState.deleteOption(activeMenu.id, option.id);
  }

  async testRoute(menu: IvrMenuItem): Promise<void> {
    const digit = window.prompt('Enter a digit for dry-run routing, or leave blank to test timeout.', '1');
    await this.ivrState.testRoute(menu.id, digit === '' ? 'timeout' : 'digit', digit?.trim() || null);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.ivrState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.ivrState.setStatus(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.ivrState.setPage(page);
  }

  trackMenu(_index: number, menu: IvrMenuItem): number {
    return menu.id;
  }

  trackOption(_index: number, option: IvrOptionItem): number {
    return option.id;
  }

  statusOptions(options: IvrAssignmentOptions | null): IvrMenuStatus[] {
    return options?.statuses ?? ['active', 'suspended', 'archived'];
  }

  renderOptions(options: IvrOptionItem[] | null | undefined): string {
    if (!options || options.length === 0) {
      return '-';
    }

    return options.map((option) => `${option.digit} ${option.label}`).join(', ');
  }
}
