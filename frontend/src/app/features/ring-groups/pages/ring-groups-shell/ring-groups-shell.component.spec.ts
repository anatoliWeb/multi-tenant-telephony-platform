import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { RingGroupsShellComponent } from './ring-groups-shell.component';
import { RingGroupsStateService } from '../../services/ring-groups-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import type {
  RingGroupAssignmentOptions,
  RingGroupItem,
  RingGroupMemberItem,
  RingGroupRoutePlan,
} from '../../models/ring-group.model';

describe('RingGroupsShellComponent', () => {
  let fixture: ComponentFixture<RingGroupsShellComponent>;
  let component: RingGroupsShellComponent;

  const ringGroups$ = new BehaviorSubject<RingGroupItem[]>([
    {
      id: 1,
      uuid: 'ring-group-1',
      tenant_id: 'tenant-a',
      name: 'Support Ring Group',
      slug: 'support-ring-group',
      description: 'Primary support route',
      strategy: 'sequential',
      status: 'active',
      ring_timeout_seconds: 25,
      max_ring_duration_seconds: 120,
      members_count: 2,
      active_members_count: 2,
      members: [],
    },
  ]);
  const activeRingGroup$ = new BehaviorSubject<RingGroupItem | null>(null);
  const activeRingGroupMembers$ = new BehaviorSubject<RingGroupMemberItem[]>([
    {
      id: 10,
      uuid: 'member-1',
      tenant_id: 'tenant-a',
      ring_group_id: 1,
      member_type: 'extension',
      extension_id: 99,
      user_id: null,
      priority: 1,
      delay_seconds: 0,
      timeout_seconds: 20,
      is_active: true,
      extension: { id: 99, number: '2001', label: 'Support Desk' },
      user: null,
      metadata: {},
    },
  ]);
  const options$ = new BehaviorSubject<RingGroupAssignmentOptions>({
    extensions: [{ id: 99, number: '2001', label: 'Support Desk', status: 'active' }],
    users: [{ id: 7, name: 'Alice', email: 'alice@example.test' }],
    strategies: ['simultaneous', 'sequential', 'random'],
    statuses: ['active', 'suspended', 'archived'],
  });
  const routePlan$ = new BehaviorSubject<RingGroupRoutePlan | null>({
    ring_group: { id: 1, uuid: 'ring-group-1', name: 'Support Ring Group', strategy: 'sequential', status: 'active' },
    resolved_at: new Date('2026-06-30T10:00:00.000Z').toISOString(),
    active_member_count: 1,
    members: [
      {
        id: 10,
        uuid: 'member-1',
        member_type: 'extension',
        priority: 1,
        delay_seconds: 0,
        timeout_seconds: 20,
        is_active: true,
        extension: { id: 99, number: '2001', label: 'Support Desk' },
      },
    ],
    failover: { type: null, id: null },
  });
  const filters$ = new BehaviorSubject({ search: '', status: '', strategy: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const optionsLoading$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);

  const ringGroupsStateMock = {
    ringGroups$,
    activeRingGroup$,
    activeRingGroupMembers$,
    options$,
    routePlan$,
    filters$,
    pagination$,
    loading$,
    saving$,
    detailLoading$,
    optionsLoading$,
    error$,
    get activeRingGroup() {
      return activeRingGroup$.value;
    },
    init: vi.fn().mockResolvedValue(undefined),
    selectRingGroup: vi.fn((ringGroup: RingGroupItem | null) => activeRingGroup$.next(ringGroup)),
    openRingGroup: vi.fn().mockResolvedValue(undefined),
    createRingGroup: vi.fn().mockResolvedValue({ id: 2 }),
    updateRingGroup: vi.fn().mockResolvedValue({ id: 1 }),
    deleteRingGroup: vi.fn().mockResolvedValue(true),
    createMember: vi.fn().mockResolvedValue({ id: 11 }),
    updateMember: vi.fn().mockResolvedValue({ id: 10 }),
    deleteMember: vi.fn().mockResolvedValue(true),
    testRoute: vi.fn().mockResolvedValue(routePlan$.value),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setStrategy: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
  };

  const permissionServiceMock = {
    hasPermission: vi.fn(() => true),
  };

  let tenantSelected = true;
  const tenantContextMock = {
    hasTenant: vi.fn(() => tenantSelected),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    tenantSelected = true;
    ringGroups$.next([
      {
        id: 1,
        uuid: 'ring-group-1',
        tenant_id: 'tenant-a',
        name: 'Support Ring Group',
        slug: 'support-ring-group',
        description: 'Primary support route',
        strategy: 'sequential',
        status: 'active',
        ring_timeout_seconds: 25,
        max_ring_duration_seconds: 120,
        members_count: 2,
        active_members_count: 2,
        members: [],
      },
    ]);
    activeRingGroup$.next(null);
    activeRingGroupMembers$.next([
      {
        id: 10,
        uuid: 'member-1',
        tenant_id: 'tenant-a',
        ring_group_id: 1,
        member_type: 'extension',
        extension_id: 99,
        user_id: null,
        priority: 1,
        delay_seconds: 0,
        timeout_seconds: 20,
        is_active: true,
        extension: { id: 99, number: '2001', label: 'Support Desk' },
        user: null,
        metadata: {},
      },
    ]);
    options$.next({
      extensions: [{ id: 99, number: '2001', label: 'Support Desk', status: 'active' }],
      users: [{ id: 7, name: 'Alice', email: 'alice@example.test' }],
      strategies: ['simultaneous', 'sequential', 'random'],
      statuses: ['active', 'suspended', 'archived'],
    });
    routePlan$.next({
      ring_group: { id: 1, uuid: 'ring-group-1', name: 'Support Ring Group', strategy: 'sequential', status: 'active' },
      resolved_at: new Date('2026-06-30T10:00:00.000Z').toISOString(),
      active_member_count: 1,
      members: [
        {
          id: 10,
          uuid: 'member-1',
          member_type: 'extension',
          priority: 1,
          delay_seconds: 0,
          timeout_seconds: 20,
          is_active: true,
          extension: { id: 99, number: '2001', label: 'Support Desk' },
        },
      ],
      failover: { type: null, id: null },
    });

    vi.spyOn(window, 'confirm').mockReturnValue(true);

    await TestBed.configureTestingModule({
      imports: [RingGroupsShellComponent],
      providers: [
        { provide: RingGroupsStateService, useValue: ringGroupsStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
        { provide: TenantContextService, useValue: tenantContextMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(RingGroupsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads ring groups on init when a tenant is selected', () => {
    expect(ringGroupsStateMock.init).toHaveBeenCalled();
  });

  it('does not request ring groups before tenant selection', async () => {
    const initCalls = ringGroupsStateMock.init.mock.calls.length;
    tenantSelected = false;
    fixture = TestBed.createComponent(RingGroupsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(ringGroupsStateMock.init).toHaveBeenCalledTimes(initCalls);
  });

  it('selects a ring group from the list', async () => {
    const row: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="ring-group-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(ringGroupsStateMock.selectRingGroup).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(ringGroupsStateMock.openRingGroup).toHaveBeenCalledWith(1);
  });

  it('supports create, edit, member and route testing flows', async () => {
    component.openCreateRingGroup();
    await component.saveRingGroup({
      name: 'New Group',
      slug: 'new-group',
      description: 'Created from tests',
      strategy: 'simultaneous',
      status: 'active',
      ring_timeout_seconds: 30,
      max_ring_duration_seconds: 120,
    });

    component.openEditRingGroup(ringGroups$.value[0]);
    await component.saveRingGroup({
      name: 'Support Ring Group',
      slug: 'support-ring-group',
      description: 'Updated',
      strategy: 'sequential',
      status: 'active',
      ring_timeout_seconds: 25,
      max_ring_duration_seconds: 120,
    });

    activeRingGroup$.next(ringGroups$.value[0]);
    activeRingGroupMembers$.next([activeRingGroupMembers$.value[0]]);
    component.openCreateMember();
    await component.saveMember({
      member_type: 'extension',
      extension_id: 99,
      priority: 1,
      delay_seconds: 0,
      timeout_seconds: 20,
      is_active: true,
    });

    component.openEditMember(activeRingGroupMembers$.value[0]);
    await component.saveMember({
      member_type: 'extension',
      extension_id: 99,
      priority: 2,
      delay_seconds: 2,
      timeout_seconds: 30,
      is_active: true,
    });

    await component.testRoute(ringGroups$.value[0]);

    expect(ringGroupsStateMock.createRingGroup).toHaveBeenCalled();
    expect(ringGroupsStateMock.updateRingGroup).toHaveBeenCalledWith(1, expect.objectContaining({ description: 'Updated' }));
    expect(ringGroupsStateMock.createMember).toHaveBeenCalledWith(1, expect.objectContaining({ extension_id: 99 }));
    expect(ringGroupsStateMock.updateMember).toHaveBeenCalledWith(1, 10, expect.objectContaining({ priority: 2 }));
    expect(ringGroupsStateMock.testRoute).toHaveBeenCalledWith(1);
  });

  it('renders selected ring group details and route plan', () => {
    activeRingGroup$.next(ringGroups$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="ring-group-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Support Ring Group');
    expect(fixture.nativeElement.textContent).toContain('2001');
    expect(fixture.nativeElement.textContent).toContain('Route plan');
  });
});
