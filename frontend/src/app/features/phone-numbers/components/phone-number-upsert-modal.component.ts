import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { SharedModule } from '../../../shared/shared.module';
import type {
  PhoneNumberAssignedUser,
  PhoneNumberItem,
  PhoneNumberUpsertPayload,
} from '../models/phone-number.model';

type PhoneNumbersModalMode = 'create' | 'edit';

type PhoneNumberDraft = {
  number: string;
  display_number: string;
  status: string;
  assigned_user_id: number | null;
  is_primary: boolean;
  provider_name: string;
};

@Component({
  selector: 'app-phone-number-upsert-modal',
  templateUrl: './phone-number-upsert-modal.component.html',
  styleUrls: ['./phone-number-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class PhoneNumberUpsertModalComponent {
  @Input() open = false;
  @Input() mode: PhoneNumbersModalMode = 'create';
  @Input() phoneNumber: PhoneNumberItem | null = null;
  @Input() users: PhoneNumberAssignedUser[] = [];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<PhoneNumberUpsertPayload>();

  draft: PhoneNumberDraft = this.buildDraft(null);

  ngOnChanges(): void {
    this.draft = this.buildDraft(this.phoneNumber);
  }

  get titleKey(): string {
    return this.mode === 'create' ? 'phoneNumbers.modal.create' : 'phoneNumbers.modal.edit';
  }

  emitSave(): void {
    this.save.emit({
      number: this.draft.number.trim(),
      display_number: this.safeOrNull(this.draft.display_number),
      status: this.draft.status || 'active',
      assigned_user_id: this.draft.assigned_user_id,
      is_primary: this.draft.assigned_user_id !== null ? this.draft.is_primary : false,
      provider_name: this.safeOrNull(this.draft.provider_name),
      type: 'did',
    });
  }

  private buildDraft(phoneNumber: PhoneNumberItem | null): PhoneNumberDraft {
    return {
      number: phoneNumber?.number ?? '',
      display_number: phoneNumber?.display_number ?? '',
      status: phoneNumber?.status ?? 'active',
      assigned_user_id: phoneNumber?.assigned_user?.id ?? null,
      is_primary: phoneNumber?.is_primary ?? false,
      provider_name: phoneNumber?.provider_name ?? 'manual',
    };
  }

  private safeOrNull(value: string): string | null {
    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
  }
}
