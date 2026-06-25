import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { map } from 'rxjs';
import { SharedModule } from '../../../../shared/shared.module';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { ContactsStateService } from '../../services/contacts-state.service';
import type { ContactItem, ContactTag, ContactUpsertPayload } from '../../models/contact.model';
import { ContactUpsertModalComponent } from '../../components/contact-upsert-modal.component';

type ContactsModalMode = 'create' | 'edit' | 'view';

@Component({
  selector: 'app-contacts-shell',
  templateUrl: './contacts-shell.component.html',
  styleUrls: ['./contacts-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, ContactUpsertModalComponent],
})
export class ContactsShellComponent implements OnInit {
  readonly contacts$;
  readonly tags$;
  readonly tagOptions$;
  readonly activeContact$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly error$;
  readonly detailLoading$;

  isModalOpen = false;
  modalMode: ContactsModalMode = 'create';
  modalContact: ContactItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canExport: boolean;

  constructor(
    private readonly contactsState: ContactsStateService,
    private readonly permissionService: PermissionService,
  ) {
    this.contacts$ = this.contactsState.contacts$;
    this.tags$ = this.contactsState.tags$;
    this.tagOptions$ = this.tags$.pipe(map((tags) => tags.map((tag) => ({ value: tag.slug, label: tag.name }))));
    this.activeContact$ = this.contactsState.activeContact$;
    this.filters$ = this.contactsState.filters$;
    this.pagination$ = this.contactsState.pagination$;
    this.loading$ = this.contactsState.loading$;
    this.saving$ = this.contactsState.saving$;
    this.error$ = this.contactsState.error$;
    this.detailLoading$ = this.contactsState.detailLoading$;
    this.canCreate = this.permissionService.hasPermission('contacts.create');
    this.canUpdate = this.permissionService.hasPermission('contacts.update');
    this.canDelete = this.permissionService.hasPermission('contacts.delete');
    this.canExport = this.permissionService.hasPermission('contacts.export');
  }

  ngOnInit(): void {
    void this.contactsState.init();
  }

  async selectContact(contact: ContactItem): Promise<void> {
    this.contactsState.selectContact(contact);
    await this.contactsState.openContact(contact.id);
  }

  openCreate(): void {
    this.modalMode = 'create';
    this.modalContact = null;
    this.isModalOpen = true;
  }

  openEdit(contact: ContactItem): void {
    this.modalMode = 'edit';
    this.modalContact = contact;
    this.isModalOpen = true;
  }

  openView(contact: ContactItem): void {
    this.modalMode = 'view';
    this.modalContact = contact;
    this.isModalOpen = true;
  }

  closeModal(): void {
    this.isModalOpen = false;
    this.modalContact = null;
  }

  async saveContact(payload: ContactUpsertPayload): Promise<void> {
    const success = this.modalMode === 'edit' && this.modalContact
      ? await this.contactsState.updateContact(this.modalContact.id, payload)
      : await this.contactsState.createContact(payload);

    if (success) {
      this.closeModal();
    }
  }

  async deleteContact(contact: ContactItem): Promise<void> {
    const confirmed = window.confirm('Delete selected contact?');
    if (!confirmed) {
      return;
    }

    await this.contactsState.deleteContact(contact.id);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.contactsState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.contactsState.setStatus(value);
  }

  async onTagChange(value: string): Promise<void> {
    await this.contactsState.setTag(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.contactsState.setPage(page);
  }

  exportContacts(): void {
    this.contactsState.exportContacts();
  }

  trackContact(_index: number, contact: ContactItem): number {
    return contact.id;
  }

  trackTag(_index: number, tag: ContactTag): number {
    return tag.id;
  }
}
