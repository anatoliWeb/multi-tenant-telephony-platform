import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SharedModule } from '../../../shared/shared.module';
import type {
  IvrAssignmentOptions,
  IvrDestinationType,
  IvrMenuItem,
  IvrOptionItem,
  IvrOptionUpsertPayload,
} from '../models/ivr.model';

@Component({
  selector: 'app-ivr-option-upsert-modal',
  templateUrl: './ivr-option-upsert-modal.component.html',
  styleUrls: ['./ivr-option-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule, SharedModule],
})
export class IvrOptionUpsertModalComponent {
  @Input() open = false;
  @Input() mode: 'create' | 'edit' = 'create';
  @Input() menu: IvrMenuItem | null = null;
  @Input() option: IvrOptionItem | null = null;
  @Input() options: IvrAssignmentOptions | null = null;
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<IvrOptionUpsertPayload>();

  form: IvrOptionUpsertPayload = this.buildForm();

  ngOnChanges(): void {
    this.form = this.buildForm();
  }

  submit(): void {
    this.save.emit({
      ...this.form,
      digit: this.form.digit.trim(),
      label: this.form.label.trim(),
    });
  }

  destinationChoices(type: IvrDestinationType | null | undefined): Array<{ id: number; label: string; sub_label?: string | null }> {
    if (!type || !this.requiresDestinationId(type)) {
      return [];
    }

    if (type === 'extension') {
      return (this.options?.extensions ?? []).map((item) => ({
        id: item.id,
        label: item.number,
        sub_label: item.label ?? item.status ?? null,
      }));
    }

    if (type === 'ring_group') {
      return (this.options?.ring_groups ?? []).map((item) => ({
        id: item.id,
        label: item.name,
        sub_label: item.slug,
      }));
    }

    if (type === 'call_queue') {
      return (this.options?.call_queues ?? []).map((item) => ({
        id: item.id,
        label: item.name,
        sub_label: item.slug,
      }));
    }

    if (type === 'ivr_menu') {
      return (this.options?.ivr_menus ?? []).map((item) => ({
        id: item.id,
        label: item.name,
        sub_label: item.slug,
      }));
    }

    return [];
  }

  requiresDestinationId(type: IvrDestinationType | null | undefined): boolean {
    return type === 'extension' || type === 'ring_group' || type === 'call_queue' || type === 'ivr_menu';
  }

  onDestinationTypeChange(type: IvrDestinationType | null): void {
    this.form.destination_type = type ?? 'extension';
    if (!this.requiresDestinationId(this.form.destination_type)) {
      this.form.destination_id = null;
    }
  }

  destinationTypeOptions(): IvrDestinationType[] {
    return this.options?.destination_types ?? ['extension', 'ring_group', 'call_queue', 'ivr_menu', 'hangup', 'voicemail_placeholder'];
  }

  private buildForm(): IvrOptionUpsertPayload {
    return {
      digit: this.option?.digit ?? '1',
      label: this.option?.label ?? '',
      destination_type: this.option?.destination_type ?? 'extension',
      destination_id: this.option?.destination_id ?? null,
      priority: this.option?.priority ?? 1,
      is_active: this.option?.is_active ?? true,
    };
  }
}
