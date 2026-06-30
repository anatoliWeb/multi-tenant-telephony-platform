import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import { SharedModule } from '../../../../shared/shared.module';
import { CallQueuesStateService } from '../../services/call-queues-state.service';
import type {
  CallQueueAssignmentOptions,
  CallQueueItem,
  CallQueueMemberItem,
  CallQueueMemberUpsertPayload,
  CallQueueStrategy,
  CallQueueStatus,
  CallQueueUpsertPayload,
} from '../../models/call-queue.model';
import { CallQueueUpsertModalComponent } from '../../components/call-queue-upsert-modal.component';
import { CallQueueMemberUpsertModalComponent } from '../../components/call-queue-member-upsert-modal.component';

@Component({
  selector: 'app-call-queues-shell',
  templateUrl: './call-queues-shell.component.html',
  styleUrls: ['./call-queues-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, CallQueueUpsertModalComponent, CallQueueMemberUpsertModalComponent],
})
export class CallQueuesShellComponent implements OnInit {
  readonly queues$;
  readonly activeQueue$;
  readonly activeQueueMembers$;
  readonly options$;
  readonly routePlan$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly detailLoading$;
  readonly optionsLoading$;
  readonly error$;

  isQueueModalOpen = false;
  queueModalMode: 'create' | 'edit' = 'create';
  queueModalItem: CallQueueItem | null = null;

  isMemberModalOpen = false;
  memberModalMode: 'create' | 'edit' = 'create';
  memberModalItem: CallQueueMemberItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canManageMembers: boolean;
  readonly canPauseMembers: boolean;
  readonly canTestRoute: boolean;

  constructor(
    private readonly callQueuesState: CallQueuesStateService,
    private readonly permissionService: PermissionService,
    public readonly tenantContext: TenantContextService,
  ) {
    this.queues$ = this.callQueuesState.queues$;
    this.activeQueue$ = this.callQueuesState.activeQueue$;
    this.activeQueueMembers$ = this.callQueuesState.activeQueueMembers$;
    this.options$ = this.callQueuesState.options$;
    this.routePlan$ = this.callQueuesState.routePlan$;
    this.filters$ = this.callQueuesState.filters$;
    this.pagination$ = this.callQueuesState.pagination$;
    this.loading$ = this.callQueuesState.loading$;
    this.saving$ = this.callQueuesState.saving$;
    this.detailLoading$ = this.callQueuesState.detailLoading$;
    this.optionsLoading$ = this.callQueuesState.optionsLoading$;
    this.error$ = this.callQueuesState.error$;
    this.canCreate = this.permissionService.hasPermission('call_queues.create');
    this.canUpdate = this.permissionService.hasPermission('call_queues.update');
    this.canDelete = this.permissionService.hasPermission('call_queues.delete');
    this.canManageMembers = this.permissionService.hasPermission('call_queues.manage_members');
    this.canPauseMembers = this.permissionService.hasPermission('call_queues.pause_members');
    this.canTestRoute = this.permissionService.hasPermission('call_queues.test_route');
  }

  ngOnInit(): void {
    if (!this.tenantContext.hasTenant()) {
      return;
    }

    void this.callQueuesState.init();
  }

  async selectQueue(queue: CallQueueItem): Promise<void> {
    this.callQueuesState.selectQueue(queue);
    await this.callQueuesState.openCallQueue(queue.id);
  }

  openCreateQueue(): void {
    this.queueModalMode = 'create';
    this.queueModalItem = null;
    this.isQueueModalOpen = true;
  }

  openEditQueue(queue: CallQueueItem): void {
    this.queueModalMode = 'edit';
    this.queueModalItem = queue;
    this.isQueueModalOpen = true;
  }

  closeQueueModal(): void {
    this.isQueueModalOpen = false;
    this.queueModalItem = null;
  }

  async saveQueue(payload: CallQueueUpsertPayload): Promise<void> {
    const result = this.queueModalMode === 'edit' && this.queueModalItem
      ? await this.callQueuesState.updateCallQueue(this.queueModalItem.id, payload)
      : await this.callQueuesState.createCallQueue(payload);

    if (result) {
      this.closeQueueModal();
    }
  }

  openCreateMember(): void {
    this.memberModalMode = 'create';
    this.memberModalItem = null;
    this.isMemberModalOpen = true;
  }

  openEditMember(member: CallQueueMemberItem): void {
    this.memberModalMode = 'edit';
    this.memberModalItem = member;
    this.isMemberModalOpen = true;
  }

  closeMemberModal(): void {
    this.isMemberModalOpen = false;
    this.memberModalItem = null;
  }

  async saveMember(payload: CallQueueMemberUpsertPayload): Promise<void> {
    const activeQueue = this.callQueuesState.activeQueue;
    if (!activeQueue) {
      return;
    }

    const result = this.memberModalMode === 'edit' && this.memberModalItem
      ? await this.callQueuesState.updateMember(activeQueue.id, this.memberModalItem.id, payload)
      : await this.callQueuesState.createMember(activeQueue.id, payload);

    if (result) {
      this.closeMemberModal();
    }
  }

  async deleteQueue(queue: CallQueueItem): Promise<void> {
    if (!window.confirm('Delete selected call queue?')) {
      return;
    }

    await this.callQueuesState.deleteCallQueue(queue.id);
  }

  async deleteMember(member: CallQueueMemberItem): Promise<void> {
    const activeQueue = this.callQueuesState.activeQueue;
    if (!activeQueue || !window.confirm('Delete selected queue member?')) {
      return;
    }

    await this.callQueuesState.deleteMember(activeQueue.id, member.id);
  }

  async pauseMember(member: CallQueueMemberItem): Promise<void> {
    const activeQueue = this.callQueuesState.activeQueue;
    if (!activeQueue) {
      return;
    }

    const reason = window.prompt('Pause reason', 'Temporary pause');
    if (!reason) {
      return;
    }

    await this.callQueuesState.pauseMember(activeQueue.id, member.id, reason);
  }

  async resumeMember(member: CallQueueMemberItem): Promise<void> {
    const activeQueue = this.callQueuesState.activeQueue;
    if (!activeQueue) {
      return;
    }

    await this.callQueuesState.resumeMember(activeQueue.id, member.id);
  }

  async testRoute(queue: CallQueueItem): Promise<void> {
    await this.callQueuesState.testRoute(queue.id);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.callQueuesState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.callQueuesState.setStatus(value);
  }

  async onStrategyChange(value: string): Promise<void> {
    await this.callQueuesState.setStrategy(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.callQueuesState.setPage(page);
  }

  trackQueue(_index: number, queue: CallQueueItem): number {
    return queue.id;
  }

  trackMember(_index: number, member: CallQueueMemberItem): number {
    return member.id;
  }

  renderMembers(members: CallQueueMemberItem[] | null | undefined): string {
    if (!members || members.length === 0) {
      return '-';
    }

    return members.map((member) => {
      if (member.member_type === 'extension' && member.extension) {
        return member.extension.label ? `${member.extension.number} - ${member.extension.label}` : member.extension.number;
      }

      if (member.member_type === 'user' && member.user) {
        return member.user.name;
      }

      return member.member_type;
    }).join(', ');
  }

  strategyOptions(options: CallQueueAssignmentOptions | null): CallQueueStrategy[] {
    return options?.strategies ?? ['ring_all', 'round_robin', 'sequential', 'random'];
  }

  statusOptions(options: CallQueueAssignmentOptions | null): CallQueueStatus[] {
    return options?.statuses ?? ['active', 'suspended', 'archived'];
  }
}
