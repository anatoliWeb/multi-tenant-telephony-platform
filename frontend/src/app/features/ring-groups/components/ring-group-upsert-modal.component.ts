import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { SharedModule } from '../../../shared/shared.module';
import type { RingGroupItem, RingGroupStrategy, RingGroupStatus, RingGroupUpsertPayload } from '../models/ring-group.model';

type RingGroupsModalMode = 'create' | 'edit';

type RingGroupDraft = {
  name: string;
  slug: string;
  description: string;
  strategy: RingGroupStrategy;
  status: RingGroupStatus;
  ring_timeout_seconds: number;
  max_ring_duration_seconds: number;
};

@Component({
  selector: 'app-ring-group-upsert-modal',
  templateUrl: './ring-group-upsert-modal.component.html',
  styleUrls: ['./ring-group-upsert-modal.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule],
})
export class RingGroupUpsertModalComponent {
  @Input() open = false;
  @Input() mode: RingGroupsModalMode = 'create';
  @Input() ringGroup: RingGroupItem | null = null;
  @Input() strategies: RingGroupStrategy[] = ['simultaneous', 'sequential', 'random'];
  @Input() statuses: RingGroupStatus[] = ['active', 'suspended', 'archived'];
  @Input() saving = false;
  @Output() close = new EventEmitter<void>();
  @Output() save = new EventEmitter<RingGroupUpsertPayload>();

  draft: RingGroupDraft = this.buildDraft(null);

  ngOnChanges(): void {
    this.draft = this.buildDraft(this.ringGroup);
  }

  get titleKey(): string {
    return this.mode === 'create' ? 'ringGroups.modal.create' : 'ringGroups.modal.edit';
  }

  emitSave(): void {
    this.save.emit({
      name: this.draft.name.trim(),
      slug: this.safeOrNull(this.draft.slug),
      description: this.safeOrNull(this.draft.description),
      strategy: this.draft.strategy || 'simultaneous',
      status: this.draft.status || 'active',
      ring_timeout_seconds: Number(this.draft.ring_timeout_seconds || 20),
      max_ring_duration_seconds: Number(this.draft.max_ring_duration_seconds || 120),
    });
  }

  private buildDraft(ringGroup: RingGroupItem | null): RingGroupDraft {
    return {
      name: ringGroup?.name ?? '',
      slug: ringGroup?.slug ?? '',
      description: ringGroup?.description ?? '',
      strategy: ringGroup?.strategy ?? 'simultaneous',
      status: ringGroup?.status ?? 'active',
      ring_timeout_seconds: ringGroup?.ring_timeout_seconds ?? 20,
      max_ring_duration_seconds: ringGroup?.max_ring_duration_seconds ?? 120,
    };
  }

  private safeOrNull(value: string): string | null {
    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
  }
}
