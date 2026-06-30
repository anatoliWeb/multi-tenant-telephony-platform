import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SharedModule } from '../../../shared/shared.module';
import type { CallQueueItem, CallQueueStrategy, CallQueueStatus, CallQueueUpsertPayload } from '../models/call-queue.model';

@Component({
  selector: 'app-call-queue-upsert-modal',
  templateUrl: './call-queue-upsert-modal.component.html',
  styleUrls: ['./call-queue-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule, SharedModule],
})
export class CallQueueUpsertModalComponent {
  @Input() isOpen = false;
  @Input() mode: 'create' | 'edit' = 'create';
  @Input() queue: CallQueueItem | null = null;
  @Input() strategies: CallQueueStrategy[] = ['ring_all', 'round_robin', 'sequential', 'random'];
  @Input() statuses: CallQueueStatus[] = ['active', 'suspended', 'archived'];
  @Input() saving = false;
  @Output() save = new EventEmitter<CallQueueUpsertPayload>();
  @Output() close = new EventEmitter<void>();

  form: CallQueueUpsertPayload = this.buildForm();

  ngOnChanges(): void {
    this.form = this.buildForm();
  }

  submit(): void {
    this.save.emit({ ...this.form });
  }

  private buildForm(): CallQueueUpsertPayload {
    return {
      name: this.queue?.name ?? '',
      slug: this.queue?.slug ?? '',
      description: this.queue?.description ?? '',
      strategy: this.queue?.strategy ?? 'ring_all',
      status: this.queue?.status ?? 'active',
      max_wait_time_seconds: this.queue?.max_wait_time_seconds ?? 300,
      ring_timeout_seconds: this.queue?.ring_timeout_seconds ?? 20,
      retry_delay_seconds: this.queue?.retry_delay_seconds ?? 5,
      max_attempts: this.queue?.max_attempts ?? 3,
      music_on_hold: this.queue?.music_on_hold ?? '',
      announce_position: this.queue?.announce_position ?? false,
      announce_estimated_wait: this.queue?.announce_estimated_wait ?? false,
      overflow_destination_type: this.queue?.overflow_destination_type ?? null,
      overflow_destination_id: this.queue?.overflow_destination_id ?? null,
    };
  }
}
