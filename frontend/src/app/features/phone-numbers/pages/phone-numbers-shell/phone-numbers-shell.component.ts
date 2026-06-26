import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PhoneNumbersStateService } from '../../services/phone-numbers-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { PhoneNumberItem, PhoneNumberUpsertPayload } from '../../models/phone-number.model';
import { SharedModule } from '../../../../shared/shared.module';
import { PhoneNumberUpsertModalComponent } from '../../components/phone-number-upsert-modal.component';

type PhoneNumbersModalMode = 'create' | 'edit';

@Component({
  selector: 'app-phone-numbers-shell',
  templateUrl: './phone-numbers-shell.component.html',
  styleUrls: ['./phone-numbers-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, PhoneNumberUpsertModalComponent],
})
export class PhoneNumbersShellComponent implements OnInit {
  readonly phoneNumbers$;
  readonly activePhoneNumber$;
  readonly users$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly detailLoading$;
  readonly error$;

  isModalOpen = false;
  modalMode: PhoneNumbersModalMode = 'create';
  modalPhoneNumber: PhoneNumberItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canAssign: boolean;
  readonly canSetPrimary: boolean;
  readonly canProvision: boolean;
  readonly canRelease: boolean;

  constructor(
    private readonly phoneNumbersState: PhoneNumbersStateService,
    private readonly permissionService: PermissionService,
  ) {
    this.phoneNumbers$ = this.phoneNumbersState.phoneNumbers$;
    this.activePhoneNumber$ = this.phoneNumbersState.activePhoneNumber$;
    this.users$ = this.phoneNumbersState.users$;
    this.filters$ = this.phoneNumbersState.filters$;
    this.pagination$ = this.phoneNumbersState.pagination$;
    this.loading$ = this.phoneNumbersState.loading$;
    this.saving$ = this.phoneNumbersState.saving$;
    this.detailLoading$ = this.phoneNumbersState.detailLoading$;
    this.error$ = this.phoneNumbersState.error$;
    this.canCreate = this.permissionService.hasPermission('phone_numbers.create');
    this.canUpdate = this.permissionService.hasPermission('phone_numbers.update');
    this.canDelete = this.permissionService.hasPermission('phone_numbers.delete');
    this.canAssign = this.permissionService.hasPermission('phone_numbers.assign');
    this.canSetPrimary = this.permissionService.hasPermission('phone_numbers.set_primary');
    this.canProvision = this.permissionService.hasPermission('phone_numbers.provision');
    this.canRelease = this.permissionService.hasPermission('phone_numbers.release');
  }

  ngOnInit(): void {
    void this.phoneNumbersState.init();
  }

  async selectPhoneNumber(phoneNumber: PhoneNumberItem): Promise<void> {
    this.phoneNumbersState.selectPhoneNumber(phoneNumber);
    await this.phoneNumbersState.openPhoneNumber(phoneNumber.id);
  }

  openCreate(): void {
    this.modalMode = 'create';
    this.modalPhoneNumber = null;
    this.isModalOpen = true;
  }

  openEdit(phoneNumber: PhoneNumberItem): void {
    this.modalMode = 'edit';
    this.modalPhoneNumber = phoneNumber;
    this.isModalOpen = true;
  }

  closeModal(): void {
    this.isModalOpen = false;
    this.modalPhoneNumber = null;
  }

  async savePhoneNumber(payload: PhoneNumberUpsertPayload): Promise<void> {
    const result = this.modalMode === 'edit' && this.modalPhoneNumber
      ? await this.phoneNumbersState.updatePhoneNumber(this.modalPhoneNumber.id, payload)
      : await this.phoneNumbersState.createPhoneNumber(payload);

    if (result) {
      this.closeModal();
    }
  }

  async assignToUser(phoneNumber: PhoneNumberItem, userId: string): Promise<void> {
    const assignedUserId = Number(userId);
    if (!assignedUserId) {
      return;
    }

    await this.phoneNumbersState.assignPhoneNumber(phoneNumber.id, assignedUserId, !phoneNumber.assigned_user);
  }

  async unassign(phoneNumber: PhoneNumberItem): Promise<void> {
    await this.phoneNumbersState.unassignPhoneNumber(phoneNumber.id);
  }

  async setPrimary(phoneNumber: PhoneNumberItem): Promise<void> {
    await this.phoneNumbersState.setPrimary(phoneNumber.id);
  }

  async activate(phoneNumber: PhoneNumberItem): Promise<void> {
    await this.phoneNumbersState.activate(phoneNumber.id);
  }

  async suspend(phoneNumber: PhoneNumberItem): Promise<void> {
    await this.phoneNumbersState.suspend(phoneNumber.id);
  }

  async release(phoneNumber: PhoneNumberItem): Promise<void> {
    await this.phoneNumbersState.release(phoneNumber.id);
  }

  async deletePhoneNumber(phoneNumber: PhoneNumberItem): Promise<void> {
    if (!window.confirm('Delete selected phone number?')) {
      return;
    }

    await this.phoneNumbersState.deletePhoneNumber(phoneNumber.id);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.phoneNumbersState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.phoneNumbersState.setStatus(value);
  }

  async onAssignedChange(value: string): Promise<void> {
    await this.phoneNumbersState.setAssigned(value);
  }

  async onPrimaryChange(value: string): Promise<void> {
    await this.phoneNumbersState.setPrimaryFilter(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.phoneNumbersState.setPage(page);
  }

  trackPhoneNumber(_index: number, phoneNumber: PhoneNumberItem): number {
    return phoneNumber.id;
  }
}
