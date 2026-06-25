import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { SharedModule } from '../../../shared/shared.module';
import type {
  ExtensionAssignmentContact,
  ExtensionAssignmentUser,
  ExtensionItem,
  ExtensionUpsertPayload,
} from '../models/extension.model';

type ExtensionsModalMode = 'create' | 'edit';

type ExtensionDraft = {
  number: string;
  label: string;
  status: string;
  assigned_user_id: number | null;
  assigned_contact_id: number | null;
};

@Component({
  selector: 'app-extension-upsert-modal',
  templateUrl: './extension-upsert-modal.component.html',
  styleUrls: ['./extension-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class ExtensionUpsertModalComponent {
  @Input() open = false;
  @Input() mode: ExtensionsModalMode = 'create';
  @Input() extension: ExtensionItem | null = null;
  @Input() users: ExtensionAssignmentUser[] = [];
  @Input() contacts: ExtensionAssignmentContact[] = [];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<ExtensionUpsertPayload>();

  draft: ExtensionDraft = this.buildDraft(null);

  ngOnChanges(): void {
    this.draft = this.buildDraft(this.extension);
  }

  get titleKey(): string {
    return this.mode === 'create' ? 'extensions.modal.create' : 'extensions.modal.edit';
  }

  emitSave(): void {
    this.save.emit({
      number: this.draft.number.trim(),
      label: this.safeOrNull(this.draft.label),
      status: this.draft.status || 'active',
      assigned_user_id: this.draft.assigned_user_id,
      assigned_contact_id: this.draft.assigned_contact_id,
    });
  }

  private buildDraft(extension: ExtensionItem | null): ExtensionDraft {
    return {
      number: extension?.number ?? '',
      label: extension?.label ?? '',
      status: extension?.status ?? 'active',
      assigned_user_id: extension?.assigned_user?.id ?? null,
      assigned_contact_id: extension?.assigned_contact?.id ?? null,
    };
  }

  private safeOrNull(value: string): string | null {
    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
  }
}
