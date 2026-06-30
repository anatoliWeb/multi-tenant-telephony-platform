import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SharedModule } from '../../../shared/shared.module';
import type {
  CallQueueAssignmentOptions,
  CallQueueItem,
  CallQueueMemberItem,
  CallQueueMemberType,
  CallQueueMemberUpsertPayload,
} from '../models/call-queue.model';

@Component({
  selector: 'app-call-queue-member-upsert-modal',
  templateUrl: './call-queue-member-upsert-modal.component.html',
  styleUrls: ['./call-queue-member-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule, SharedModule],
})
export class CallQueueMemberUpsertModalComponent {
  @Input() isOpen = false;
  @Input() mode: 'create' | 'edit' = 'create';
  @Input() queue: CallQueueItem | null = null;
  @Input() member: CallQueueMemberItem | null = null;
  @Input() options: CallQueueAssignmentOptions | null = null;
  @Input() saving = false;
  @Output() save = new EventEmitter<CallQueueMemberUpsertPayload>();
  @Output() close = new EventEmitter<void>();

  form: CallQueueMemberUpsertPayload = this.buildForm();

  ngOnChanges(): void {
    this.form = this.buildForm();
  }

  submit(): void {
    this.save.emit({ ...this.form });
  }

  memberTypeOptions(): CallQueueMemberType[] {
    return ['extension', 'user'];
  }

  private buildForm(): CallQueueMemberUpsertPayload {
    return {
      member_type: this.member?.member_type ?? 'extension',
      extension_id: this.member?.extension_id ?? null,
      user_id: this.member?.user_id ?? null,
      priority: this.member?.priority ?? 1,
      penalty: this.member?.penalty ?? 0,
      is_active: this.member?.is_active ?? true,
    };
  }
}
