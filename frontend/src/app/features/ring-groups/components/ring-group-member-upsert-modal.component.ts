import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { SharedModule } from '../../../shared/shared.module';
import type {
  RingGroupItem,
  RingGroupMemberItem,
  RingGroupMemberType,
  RingGroupMemberUpsertPayload,
  RingGroupMemberExtensionOption,
  RingGroupMemberUserOption,
} from '../models/ring-group.model';

type RingGroupMemberDraft = {
  member_type: RingGroupMemberType;
  extension_id: number | null;
  user_id: number | null;
  priority: number;
  delay_seconds: number;
  timeout_seconds: number;
  is_active: boolean;
};

@Component({
  selector: 'app-ring-group-member-upsert-modal',
  templateUrl: './ring-group-member-upsert-modal.component.html',
  styleUrls: ['./ring-group-member-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class RingGroupMemberUpsertModalComponent {
  @Input() open = false;
  @Input() ringGroup: RingGroupItem | null = null;
  @Input() member: RingGroupMemberItem | null = null;
  @Input() extensions: RingGroupMemberExtensionOption[] = [];
  @Input() users: RingGroupMemberUserOption[] = [];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<RingGroupMemberUpsertPayload>();

  draft: RingGroupMemberDraft = this.buildDraft(null);

  ngOnChanges(): void {
    this.draft = this.buildDraft(this.member);
  }

  get titleKey(): string {
    return this.member ? 'ringGroups.memberModal.edit' : 'ringGroups.memberModal.create';
  }

  emitSave(): void {
    this.save.emit({
      member_type: this.draft.member_type || 'extension',
      extension_id: this.draft.member_type === 'extension' ? this.draft.extension_id : null,
      user_id: this.draft.member_type === 'user' ? this.draft.user_id : null,
      priority: Number(this.draft.priority || 1),
      delay_seconds: Number(this.draft.delay_seconds || 0),
      timeout_seconds: Number(this.draft.timeout_seconds || 20),
      is_active: this.draft.is_active,
    });
  }

  private buildDraft(member: RingGroupMemberItem | null): RingGroupMemberDraft {
    return {
      member_type: member?.member_type ?? 'extension',
      extension_id: member?.extension?.id ?? member?.extension_id ?? null,
      user_id: member?.user?.id ?? member?.user_id ?? null,
      priority: member?.priority ?? 1,
      delay_seconds: member?.delay_seconds ?? 0,
      timeout_seconds: member?.timeout_seconds ?? 20,
      is_active: member?.is_active ?? true,
    };
  }
}
