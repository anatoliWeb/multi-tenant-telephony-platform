import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SharedModule } from '../../../shared/shared.module';
import type {
  IvrAssignmentOptions,
  IvrDestinationType,
  IvrMenuItem,
  IvrMenuStatus,
  IvrMenuUpsertPayload,
} from '../models/ivr.model';

@Component({
  selector: 'app-ivr-menu-upsert-modal',
  templateUrl: './ivr-menu-upsert-modal.component.html',
  styleUrls: ['./ivr-menu-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, FormsModule, SharedModule],
})
export class IvrMenuUpsertModalComponent {
  @Input() open = false;
  @Input() mode: 'create' | 'edit' = 'create';
  @Input() menu: IvrMenuItem | null = null;
  @Input() options: IvrAssignmentOptions | null = null;
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<IvrMenuUpsertPayload>();

  form: IvrMenuUpsertPayload = this.buildForm();

  ngOnChanges(): void {
    this.form = this.buildForm();
  }

  submit(): void {
    this.save.emit({
      ...this.form,
      name: this.form.name.trim(),
      slug: this.safeOrNull(this.form.slug),
      description: this.safeOrNull(this.form.description),
      greeting_text: this.safeOrNull(this.form.greeting_text),
      greeting_audio_path: this.safeOrNull(this.form.greeting_audio_path),
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

  onTimeoutDestinationTypeChange(type: IvrDestinationType | null): void {
    this.form.timeout_destination_type = type;
    if (!this.requiresDestinationId(type)) {
      this.form.timeout_destination_id = null;
    }
  }

  onInvalidDestinationTypeChange(type: IvrDestinationType | null): void {
    this.form.invalid_destination_type = type;
    if (!this.requiresDestinationId(type)) {
      this.form.invalid_destination_id = null;
    }
  }

  statusOptions(): IvrMenuStatus[] {
    return this.options?.statuses ?? ['active', 'suspended', 'archived'];
  }

  actionOptions(): string[] {
    return this.options?.actions ?? ['repeat', 'route', 'hangup'];
  }

  destinationTypeOptions(): IvrDestinationType[] {
    return this.options?.destination_types ?? ['extension', 'ring_group', 'call_queue', 'ivr_menu', 'hangup', 'voicemail_placeholder'];
  }

  private buildForm(): IvrMenuUpsertPayload {
    return {
      name: this.menu?.name ?? '',
      slug: this.menu?.slug ?? '',
      description: this.menu?.description ?? '',
      status: this.menu?.status ?? 'active',
      greeting_text: this.menu?.greeting_text ?? '',
      greeting_audio_path: this.menu?.greeting_audio_path ?? '',
      repeat_count: this.menu?.repeat_count ?? 1,
      input_timeout_seconds: this.menu?.input_timeout_seconds ?? 5,
      max_invalid_attempts: this.menu?.max_invalid_attempts ?? 3,
      timeout_action_type: this.menu?.timeout_action_type ?? 'repeat',
      timeout_destination_type: this.menu?.timeout_destination_type ?? null,
      timeout_destination_id: this.menu?.timeout_destination_id ?? null,
      invalid_action_type: this.menu?.invalid_action_type ?? 'repeat',
      invalid_destination_type: this.menu?.invalid_destination_type ?? null,
      invalid_destination_id: this.menu?.invalid_destination_id ?? null,
    };
  }

  private safeOrNull(value: string | null | undefined): string | null {
    const normalized = (value ?? '').trim();
    return normalized.length > 0 ? normalized : null;
  }
}
