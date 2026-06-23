import { Component, EventEmitter, Input, Output } from '@angular/core';
import type { SettingItem, SettingUpsertPayload } from '../models/settings.model';

type ModalMode = 'create' | 'edit' | 'view';

@Component({
  selector: 'app-settings-upsert-modal',
  templateUrl: './settings-upsert-modal.component.html',
  styleUrls: ['./settings-upsert-modal.component.scss'],
  standalone: false,
})
export class SettingsUpsertModalComponent {
  @Input() open = false;
  @Input() mode: ModalMode = 'create';
  @Input() setting: SettingItem | null = null;
  @Input() types: string[] = [];
  @Input() locales: string[] = ['en', 'uk', 'de'];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<SettingUpsertPayload>();

  draft: {
    key: string;
    label: string;
    group: string;
    description: string;
    type: string;
    valueText: string;
    defaultValueText: string;
    is_frontend: boolean;
    is_backend: boolean;
    is_public: boolean;
    is_encrypted: boolean;
    is_active: boolean;
    priority: number;
    translations: Record<string, { label: string; description: string }>;
  } | null = null;

  ngOnChanges(): void {
    this.resetDraft();
  }

  get readonly(): boolean {
    return this.mode === 'view';
  }

  get modeTitleKey(): string {
    if (this.mode === 'create') return 'settings.modal.create';
    if (this.mode === 'edit') return 'settings.modal.edit';
    return 'settings.modal.view';
  }

  private resetDraft(): void {
    const translations = this.locales.reduce<Record<string, { label: string; description: string }>>((acc, locale) => {
      acc[locale] = { label: '', description: '' };
      return acc;
    }, {});

    this.draft = {
      key: this.setting?.key ?? '',
      label: this.setting?.label ?? '',
      group: this.setting?.group ?? 'general',
      description: this.setting?.description ?? '',
      type: this.setting?.type ?? (this.types[0] ?? 'string'),
      valueText: this.serializeForField(this.setting?.value),
      defaultValueText: this.serializeForField(this.setting?.default_value),
      is_frontend: this.setting?.is_frontend ?? true,
      is_backend: this.setting?.is_backend ?? true,
      is_public: this.setting?.is_public ?? false,
      is_encrypted: this.setting?.is_encrypted ?? false,
      is_active: this.setting?.is_active ?? true,
      priority: this.setting?.priority ?? 100,
      translations,
    };
  }

  emitSave(): void {
    if (!this.draft) return;

    this.save.emit({
      key: this.draft.key,
      label: this.draft.label,
      group: this.draft.group,
      description: this.draft.description,
      type: this.draft.type,
      value: this.parseByType(this.draft.valueText, this.draft.type),
      default_value: this.parseByType(this.draft.defaultValueText, this.draft.type),
      is_frontend: this.draft.is_frontend,
      is_backend: this.draft.is_backend,
      is_public: this.draft.is_public,
      is_encrypted: this.draft.is_encrypted,
      is_active: this.draft.is_active,
      priority: this.draft.priority,
      translations: this.draft.translations,
    });
  }

  private serializeForField(value: unknown): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    return JSON.stringify(value);
  }

  private parseByType(raw: string, type: string): unknown {
    if (raw === '') return null;
    if (type === 'boolean' || type === 'toggle') return ['true', '1', 'yes', 'on'].includes(raw.toLowerCase());
    if (type === 'integer') return Number.parseInt(raw, 10);
    if (type === 'float') return Number.parseFloat(raw);
    if (type === 'json' || type === 'array') {
      try {
        return JSON.parse(raw);
      } catch {
        return raw;
      }
    }
    return raw;
  }
}
