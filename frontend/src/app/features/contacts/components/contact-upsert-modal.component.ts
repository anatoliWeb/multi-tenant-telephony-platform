import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { SharedModule } from '../../../shared/shared.module';
import type {
  ContactEmailDraft,
  ContactItem,
  ContactPhoneDraft,
  ContactTag,
  ContactUpsertPayload,
} from '../models/contact.model';

type ContactsModalMode = 'create' | 'edit' | 'view';

type ContactDraft = {
  first_name: string;
  last_name: string;
  display_name: string;
  company_name: string;
  job_title: string;
  notes: string;
  status: string;
  phones: ContactPhoneDraft[];
  emails: ContactEmailDraft[];
  tag_ids: number[];
};

@Component({
  selector: 'app-contact-upsert-modal',
  templateUrl: './contact-upsert-modal.component.html',
  styleUrls: ['./contact-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class ContactUpsertModalComponent {
  @Input() open = false;
  @Input() mode: ContactsModalMode = 'create';
  @Input() contact: ContactItem | null = null;
  @Input() tags: ContactTag[] = [];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<ContactUpsertPayload>();

  draft: ContactDraft = this.buildDraft(null);

  ngOnChanges(): void {
    this.draft = this.buildDraft(this.contact);
  }

  get readonly(): boolean {
    return this.mode === 'view';
  }

  get titleKey(): string {
    if (this.mode === 'create') {
      return 'contacts.modal.create';
    }

    if (this.mode === 'edit') {
      return 'contacts.modal.edit';
    }

    return 'contacts.modal.view';
  }

  addPhone(): void {
    this.draft.phones = [
      ...this.draft.phones,
      this.emptyPhoneDraft(this.draft.phones.length === 0),
    ];
  }

  removePhone(index: number): void {
    this.draft.phones = this.draft.phones.filter((_, itemIndex) => itemIndex !== index);
    this.ensureSinglePrimaryPhone();
  }

  setPrimaryPhone(index: number): void {
    this.draft.phones = this.draft.phones.map((phone, itemIndex) => ({
      ...phone,
      is_primary: itemIndex === index,
    }));
  }

  addEmail(): void {
    this.draft.emails = [
      ...this.draft.emails,
      this.emptyEmailDraft(this.draft.emails.length === 0),
    ];
  }

  removeEmail(index: number): void {
    this.draft.emails = this.draft.emails.filter((_, itemIndex) => itemIndex !== index);
    this.ensureSinglePrimaryEmail();
  }

  setPrimaryEmail(index: number): void {
    this.draft.emails = this.draft.emails.map((email, itemIndex) => ({
      ...email,
      is_primary: itemIndex === index,
    }));
  }

  toggleTag(tagId: number, checked: boolean): void {
    const values = new Set(this.draft.tag_ids);
    if (checked) {
      values.add(tagId);
    } else {
      values.delete(tagId);
    }

    this.draft.tag_ids = [...values];
  }

  emitSave(): void {
    this.ensureSinglePrimaryPhone();
    this.ensureSinglePrimaryEmail();

    this.save.emit({
      first_name: this.safeOrNull(this.draft.first_name),
      last_name: this.safeOrNull(this.draft.last_name),
      display_name: this.safeOrNull(this.draft.display_name),
      company_name: this.safeOrNull(this.draft.company_name),
      job_title: this.safeOrNull(this.draft.job_title),
      notes: this.safeOrNull(this.draft.notes),
      status: this.draft.status || 'active',
      phones: this.draft.phones
        .filter((phone) => phone.raw_number.trim().length > 0)
        .map((phone) => ({
          label: this.safeOrNull(phone.label),
          raw_number: phone.raw_number.trim(),
          extension: this.safeOrNull(phone.extension),
          is_primary: phone.is_primary,
          is_sms_capable: phone.is_sms_capable,
          is_active: phone.is_active,
        })),
      emails: this.draft.emails
        .filter((email) => email.email.trim().length > 0)
        .map((email) => ({
          label: this.safeOrNull(email.label),
          email: email.email.trim(),
          is_primary: email.is_primary,
          is_active: email.is_active,
        })),
      tag_ids: [...this.draft.tag_ids],
    });
  }

  private buildDraft(contact: ContactItem | null): ContactDraft {
    return {
      first_name: contact?.first_name ?? '',
      last_name: contact?.last_name ?? '',
      display_name: contact?.display_name ?? '',
      company_name: contact?.company_name ?? '',
      job_title: contact?.job_title ?? '',
      notes: contact?.notes ?? '',
      status: contact?.status ?? 'active',
      phones: contact?.phones?.length
        ? contact.phones.map((phone, index) => ({
            label: phone.label ?? '',
            raw_number: phone.raw_number ?? '',
            extension: phone.extension ?? '',
            is_primary: phone.is_primary || index === 0,
            is_sms_capable: phone.is_sms_capable,
            is_active: phone.is_active,
          }))
        : [this.emptyPhoneDraft(true)],
      emails: contact?.emails?.length
        ? contact.emails.map((email, index) => ({
            label: email.label ?? '',
            email: email.email ?? '',
            is_primary: email.is_primary || index === 0,
            is_active: email.is_active,
          }))
        : [this.emptyEmailDraft(true)],
      tag_ids: contact?.tags?.map((tag) => tag.id) ?? [],
    };
  }

  private emptyPhoneDraft(isPrimary: boolean): ContactPhoneDraft {
    return {
      label: '',
      raw_number: '',
      extension: '',
      is_primary: isPrimary,
      is_sms_capable: false,
      is_active: true,
    };
  }

  private emptyEmailDraft(isPrimary: boolean): ContactEmailDraft {
    return {
      label: '',
      email: '',
      is_primary: isPrimary,
      is_active: true,
    };
  }

  private ensureSinglePrimaryPhone(): void {
    const primaryIndex = this.draft.phones.findIndex((phone) => phone.is_primary);
    const resolvedPrimaryIndex = primaryIndex >= 0 ? primaryIndex : 0;
    this.draft.phones = this.draft.phones.map((phone, index) => ({
      ...phone,
      is_primary: index === resolvedPrimaryIndex,
    }));
  }

  private ensureSinglePrimaryEmail(): void {
    const primaryIndex = this.draft.emails.findIndex((email) => email.is_primary);
    const resolvedPrimaryIndex = primaryIndex >= 0 ? primaryIndex : 0;
    this.draft.emails = this.draft.emails.map((email, index) => ({
      ...email,
      is_primary: index === resolvedPrimaryIndex,
    }));
  }

  private safeOrNull(value: string): string | null {
    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
  }
}
