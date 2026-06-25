import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { map } from 'rxjs';
import { SharedModule } from '../../../../shared/shared.module';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { ExtensionsStateService } from '../../services/extensions-state.service';
import type { ExtensionItem, ExtensionUpsertPayload } from '../../models/extension.model';
import { ExtensionUpsertModalComponent } from '../../components/extension-upsert-modal.component';

type ExtensionsModalMode = 'create' | 'edit';

@Component({
  selector: 'app-extensions-shell',
  templateUrl: './extensions-shell.component.html',
  styleUrls: ['./extensions-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, ExtensionUpsertModalComponent],
})
export class ExtensionsShellComponent implements OnInit {
  readonly extensions$;
  readonly activeExtension$;
  readonly users$;
  readonly contacts$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly detailLoading$;
  readonly latestSecret$;
  readonly error$;
  readonly userOptions$;
  readonly contactOptions$;

  isModalOpen = false;
  modalMode: ExtensionsModalMode = 'create';
  modalExtension: ExtensionItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canRotateCredentials: boolean;

  constructor(
    private readonly extensionsState: ExtensionsStateService,
    private readonly permissionService: PermissionService,
  ) {
    this.extensions$ = this.extensionsState.extensions$;
    this.activeExtension$ = this.extensionsState.activeExtension$;
    this.users$ = this.extensionsState.users$;
    this.contacts$ = this.extensionsState.contacts$;
    this.filters$ = this.extensionsState.filters$;
    this.pagination$ = this.extensionsState.pagination$;
    this.loading$ = this.extensionsState.loading$;
    this.saving$ = this.extensionsState.saving$;
    this.detailLoading$ = this.extensionsState.detailLoading$;
    this.latestSecret$ = this.extensionsState.latestSecret$;
    this.error$ = this.extensionsState.error$;
    this.userOptions$ = this.users$.pipe(map((users) => users.map((user) => ({ value: String(user.id), label: user.name }))));
    this.contactOptions$ = this.contacts$.pipe(map((contacts) => contacts.map((contact) => ({ value: String(contact.id), label: contact.display_name }))));
    this.canCreate = this.permissionService.hasPermission('extensions.create');
    this.canUpdate = this.permissionService.hasPermission('extensions.update');
    this.canDelete = this.permissionService.hasPermission('extensions.delete');
    this.canRotateCredentials = this.permissionService.hasPermission('extensions.manage_credentials');
  }

  ngOnInit(): void {
    void this.extensionsState.init();
  }

  async selectExtension(extension: ExtensionItem): Promise<void> {
    this.extensionsState.selectExtension(extension);
    await this.extensionsState.openExtension(extension.id);
  }

  openCreate(): void {
    this.modalMode = 'create';
    this.modalExtension = null;
    this.isModalOpen = true;
  }

  openEdit(extension: ExtensionItem): void {
    this.modalMode = 'edit';
    this.modalExtension = extension;
    this.isModalOpen = true;
  }

  closeModal(): void {
    this.isModalOpen = false;
    this.modalExtension = null;
  }

  async saveExtension(payload: ExtensionUpsertPayload): Promise<void> {
    const result = this.modalMode === 'edit' && this.modalExtension
      ? await this.extensionsState.updateExtension(this.modalExtension.id, payload)
      : await this.extensionsState.createExtension(payload);

    if (result) {
      this.closeModal();
    }
  }

  async deleteExtension(extension: ExtensionItem): Promise<void> {
    if (!window.confirm('Delete selected extension?')) {
      return;
    }

    await this.extensionsState.deleteExtension(extension.id);
  }

  async rotateCredentials(extension: ExtensionItem): Promise<void> {
    await this.extensionsState.rotateCredentials(extension.id);
  }

  dismissLatestSecret(): void {
    this.extensionsState.dismissLatestSecret();
  }

  async onSearchChange(value: string): Promise<void> {
    await this.extensionsState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.extensionsState.setStatus(value);
  }

  async onAssignedChange(value: string): Promise<void> {
    await this.extensionsState.setAssigned(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.extensionsState.setPage(page);
  }

  trackExtension(_index: number, extension: ExtensionItem): number {
    return extension.id;
  }
}
