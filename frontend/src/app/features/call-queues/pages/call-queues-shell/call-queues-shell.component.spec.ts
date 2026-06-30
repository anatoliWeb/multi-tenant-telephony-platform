import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { CallQueuesShellComponent } from './call-queues-shell.component';
import { CallQueuesStateService } from '../../services/call-queues-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import type { CallQueueItem } from '../../models/call-queue.model';

describe('CallQueuesShellComponent', () => {
  let fixture: ComponentFixture<CallQueuesShellComponent>;
  let component: CallQueuesShellComponent;

  const queues$ = new BehaviorSubject<CallQueueItem[]>([
    {
      id: 1,
      uuid: 'queue-1',
      tenant_id: 'tenant-1',
      name: 'Support Queue',
      slug: 'support-queue',
      strategy: 'ring_all',
      status: 'active',
      max_wait_time_seconds: 300,
      ring_timeout_seconds: 20,
      retry_delay_seconds: 5,
      max_attempts: 3,
      announce_position: false,
      announce_estimated_wait: false,
      members_count: 1,
      active_members_count: 1,
      paused_members_count: 0,
      overflow_destination_summary: 'user:1',
    } as CallQueueItem,
  ]);

  const activeQueue$ = new BehaviorSubject<CallQueueItem | null>(null);
  const activeQueueMembers$ = new BehaviorSubject([]);
  const options$ = new BehaviorSubject(null);
  const routePlan$ = new BehaviorSubject(null);
  const filters$ = new BehaviorSubject({ search: '', status: '', strategy: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const optionsLoading$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);

  const stateMock = {
    queues$,
    activeQueue$,
    activeQueueMembers$,
    options$,
    routePlan$,
    filters$,
    pagination$,
    loading$,
    saving$,
    detailLoading$,
    optionsLoading$,
    error$,
    get activeQueue() {
      return activeQueue$.value;
    },
    init: vi.fn().mockResolvedValue(undefined),
    selectQueue: vi.fn(),
    openCallQueue: vi.fn().mockResolvedValue(undefined),
    createCallQueue: vi.fn().mockResolvedValue({ id: 2 }),
    updateCallQueue: vi.fn().mockResolvedValue({ id: 1 }),
    deleteCallQueue: vi.fn().mockResolvedValue(true),
    createMember: vi.fn().mockResolvedValue({ id: 10 }),
    updateMember: vi.fn().mockResolvedValue({ id: 10 }),
    deleteMember: vi.fn().mockResolvedValue(true),
    pauseMember: vi.fn().mockResolvedValue({ id: 10 }),
    resumeMember: vi.fn().mockResolvedValue({ id: 10 }),
    testRoute: vi.fn().mockResolvedValue({}),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setStrategy: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
  };

  const permissionMock = {
    hasPermission: vi.fn().mockReturnValue(true),
  };

  const tenantContextMock = {
    hasTenant: vi.fn().mockReturnValue(true),
  };

  beforeEach(() => {
    activeQueue$.next(null);
    activeQueueMembers$.next([]);
    options$.next(null);
    routePlan$.next(null);
    error$.next(null);

    TestBed.configureTestingModule({
      imports: [CallQueuesShellComponent],
      providers: [
        { provide: CallQueuesStateService, useValue: stateMock },
        { provide: PermissionService, useValue: permissionMock },
        { provide: TenantContextService, useValue: tenantContextMock },
      ],
    });

    fixture = TestBed.createComponent(CallQueuesShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('initializes when a tenant is selected', () => {
    expect(stateMock.init).toHaveBeenCalled();
  });

  it('opens and saves queues and members', async () => {
    component.openCreateQueue();
    await component.saveQueue({
      name: 'New Queue',
      strategy: 'ring_all',
      status: 'active',
      max_wait_time_seconds: 300,
      ring_timeout_seconds: 20,
      retry_delay_seconds: 5,
      max_attempts: 3,
    });

    expect(stateMock.createCallQueue).toHaveBeenCalled();

    activeQueue$.next(queues$.value[0]);
    component.openCreateMember();
    await component.saveMember({
      member_type: 'user',
      user_id: 2,
      priority: 1,
      penalty: 0,
      is_active: true,
    });

    expect(stateMock.createMember).toHaveBeenCalled();
  });

  it('shows tenant selection gate', () => {
    tenantContextMock.hasTenant.mockReturnValue(false);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Select a tenant');
  });
});
