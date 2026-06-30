import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import { SharedModule } from '../../../../shared/shared.module';
import { RingGroupsStateService } from '../../services/ring-groups-state.service';
import type {
  RingGroupAssignmentOptions,
  RingGroupItem,
  RingGroupMemberItem,
  RingGroupMemberUpsertPayload,
  RingGroupStrategy,
  RingGroupStatus,
  RingGroupUpsertPayload,
} from '../../models/ring-group.model';
import { RingGroupUpsertModalComponent } from '../../components/ring-group-upsert-modal.component';
import { RingGroupMemberUpsertModalComponent } from '../../components/ring-group-member-upsert-modal.component';

type RingGroupsModalMode = 'create' | 'edit';
type RingGroupsMemberModalMode = 'create' | 'edit';

@Component({
  selector: 'app-ring-groups-shell',
  templateUrl: './ring-groups-shell.component.html',
  styleUrls: ['./ring-groups-shell.component.scss'],
  standalone: true,
  imports: [CommonModule, SharedModule, RingGroupUpsertModalComponent, RingGroupMemberUpsertModalComponent],
})
export class RingGroupsShellComponent implements OnInit {
  readonly ringGroups$;
  readonly activeRingGroup$;
  readonly activeRingGroupMembers$;
  readonly options$;
  readonly routePlan$;
  readonly filters$;
  readonly pagination$;
  readonly loading$;
  readonly saving$;
  readonly detailLoading$;
  readonly optionsLoading$;
  readonly error$;

  isRingGroupModalOpen = false;
  ringGroupModalMode: RingGroupsModalMode = 'create';
  ringGroupModalItem: RingGroupItem | null = null;

  isMemberModalOpen = false;
  memberModalMode: RingGroupsMemberModalMode = 'create';
  memberModalItem: RingGroupMemberItem | null = null;

  readonly canCreate: boolean;
  readonly canUpdate: boolean;
  readonly canDelete: boolean;
  readonly canManageMembers: boolean;
  readonly canTestRoute: boolean;

  constructor(
    private readonly ringGroupsState: RingGroupsStateService,
    private readonly permissionService: PermissionService,
    public readonly tenantContext: TenantContextService,
  ) {
    this.ringGroups$ = this.ringGroupsState.ringGroups$;
    this.activeRingGroup$ = this.ringGroupsState.activeRingGroup$;
    this.activeRingGroupMembers$ = this.ringGroupsState.activeRingGroupMembers$;
    this.options$ = this.ringGroupsState.options$;
    this.routePlan$ = this.ringGroupsState.routePlan$;
    this.filters$ = this.ringGroupsState.filters$;
    this.pagination$ = this.ringGroupsState.pagination$;
    this.loading$ = this.ringGroupsState.loading$;
    this.saving$ = this.ringGroupsState.saving$;
    this.detailLoading$ = this.ringGroupsState.detailLoading$;
    this.optionsLoading$ = this.ringGroupsState.optionsLoading$;
    this.error$ = this.ringGroupsState.error$;
    this.canCreate = this.permissionService.hasPermission('ring_groups.create');
    this.canUpdate = this.permissionService.hasPermission('ring_groups.update');
    this.canDelete = this.permissionService.hasPermission('ring_groups.delete');
    this.canManageMembers = this.permissionService.hasPermission('ring_groups.manage_members');
    this.canTestRoute = this.permissionService.hasPermission('ring_groups.test_route');
  }

  ngOnInit(): void {
    if (!this.tenantContext.hasTenant()) {
      return;
    }

    void this.ringGroupsState.init();
  }

  async selectRingGroup(ringGroup: RingGroupItem): Promise<void> {
    this.ringGroupsState.selectRingGroup(ringGroup);
    await this.ringGroupsState.openRingGroup(ringGroup.id);
  }

  openCreateRingGroup(): void {
    this.ringGroupModalMode = 'create';
    this.ringGroupModalItem = null;
    this.isRingGroupModalOpen = true;
  }

  openEditRingGroup(ringGroup: RingGroupItem): void {
    this.ringGroupModalMode = 'edit';
    this.ringGroupModalItem = ringGroup;
    this.isRingGroupModalOpen = true;
  }

  closeRingGroupModal(): void {
    this.isRingGroupModalOpen = false;
    this.ringGroupModalItem = null;
  }

  async saveRingGroup(payload: RingGroupUpsertPayload): Promise<void> {
    const result = this.ringGroupModalMode === 'edit' && this.ringGroupModalItem
      ? await this.ringGroupsState.updateRingGroup(this.ringGroupModalItem.id, payload)
      : await this.ringGroupsState.createRingGroup(payload);

    if (result) {
      this.closeRingGroupModal();
    }
  }

  openCreateMember(): void {
    this.memberModalMode = 'create';
    this.memberModalItem = null;
    this.isMemberModalOpen = true;
  }

  openEditMember(member: RingGroupMemberItem): void {
    this.memberModalMode = 'edit';
    this.memberModalItem = member;
    this.isMemberModalOpen = true;
  }

  closeMemberModal(): void {
    this.isMemberModalOpen = false;
    this.memberModalItem = null;
  }

  async saveMember(payload: RingGroupMemberUpsertPayload): Promise<void> {
    const activeRingGroup = this.ringGroupsState.activeRingGroup;
    if (!activeRingGroup) {
      return;
    }

    const result = this.memberModalMode === 'edit' && this.memberModalItem
      ? await this.ringGroupsState.updateMember(activeRingGroup.id, this.memberModalItem.id, payload)
      : await this.ringGroupsState.createMember(activeRingGroup.id, payload);

    if (result) {
      this.closeMemberModal();
    }
  }

  async deleteRingGroup(ringGroup: RingGroupItem): Promise<void> {
    if (!window.confirm('Delete selected ring group?')) {
      return;
    }

    await this.ringGroupsState.deleteRingGroup(ringGroup.id);
  }

  async deleteMember(member: RingGroupMemberItem): Promise<void> {
    const activeRingGroup = this.ringGroupsState.activeRingGroup;
    if (!activeRingGroup || !window.confirm('Delete selected ring group member?')) {
      return;
    }

    await this.ringGroupsState.deleteMember(activeRingGroup.id, member.id);
  }

  async testRoute(ringGroup: RingGroupItem): Promise<void> {
    await this.ringGroupsState.testRoute(ringGroup.id);
  }

  async onSearchChange(value: string): Promise<void> {
    await this.ringGroupsState.setSearch(value);
  }

  async onStatusChange(value: string): Promise<void> {
    await this.ringGroupsState.setStatus(value);
  }

  async onStrategyChange(value: string): Promise<void> {
    await this.ringGroupsState.setStrategy(value);
  }

  async onPageChange(page: number): Promise<void> {
    await this.ringGroupsState.setPage(page);
  }

  trackRingGroup(_index: number, ringGroup: RingGroupItem): number {
    return ringGroup.id;
  }

  trackMember(_index: number, member: RingGroupMemberItem): number {
    return member.id;
  }

  renderMembers(members: RingGroupMemberItem[] | null | undefined): string {
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

  strategyOptions(options: RingGroupAssignmentOptions | null): RingGroupStrategy[] {
    return options?.strategies ?? ['simultaneous', 'sequential', 'random'];
  }

  statusOptions(options: RingGroupAssignmentOptions | null): RingGroupStatus[] {
    return options?.statuses ?? ['active', 'suspended', 'archived'];
  }
}
