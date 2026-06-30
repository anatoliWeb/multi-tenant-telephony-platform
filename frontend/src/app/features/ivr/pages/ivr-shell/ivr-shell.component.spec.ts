import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { IvrShellComponent } from './ivr-shell.component';
import { IvrsStateService } from '../../services/ivrs-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import type { IvrAssignmentOptions, IvrMenuItem, IvrOptionItem, IvrRoutePlan } from '../../models/ivr.model';

describe('IvrShellComponent', () => {
  let fixture: ComponentFixture<IvrShellComponent>;
  let component: IvrShellComponent;

  const menus$ = new BehaviorSubject<IvrMenuItem[]>([
    {
      id: 1,
      uuid: 'ivr-menu-1',
      tenant_id: 'tenant-a',
      name: 'Main IVR',
      slug: 'main-ivr',
      description: 'Primary menu',
      status: 'active',
      greeting_text: 'Welcome',
      greeting_audio_path: null,
      repeat_count: 1,
      input_timeout_seconds: 5,
      max_invalid_attempts: 3,
      timeout_action_type: 'route',
      timeout_destination_type: 'ring_group',
      timeout_destination_id: 12,
      timeout_destination_summary: 'ring_group:12',
      invalid_action_type: 'repeat',
      invalid_destination_type: null,
      invalid_destination_id: null,
      invalid_destination_summary: null,
      options_count: 1,
      active_options_count: 1,
      options: [],
    },
  ]);
  const activeMenu$ = new BehaviorSubject<IvrMenuItem | null>(null);
  const activeOptions$ = new BehaviorSubject<IvrOptionItem[]>([
    {
      id: 100,
      uuid: 'ivr-option-1',
      tenant_id: 'tenant-a',
      ivr_menu_id: 1,
      digit: '1',
      label: 'Sales',
      destination_type: 'call_queue',
      destination_id: 22,
      destination_summary: 'call_queue:22',
      priority: 1,
      is_active: true,
      metadata: {},
    },
  ]);
  const options$ = new BehaviorSubject<IvrAssignmentOptions>({
    extensions: [{ id: 99, number: '2001', label: 'Support Desk', status: 'active' }],
    ring_groups: [{ id: 12, name: 'Support Group', slug: 'support-group', status: 'active' }],
    call_queues: [{ id: 22, name: 'Sales Queue', slug: 'sales-queue', status: 'active' }],
    ivr_menus: [{ id: 1, name: 'Main IVR', slug: 'main-ivr', status: 'active' }],
    statuses: ['active', 'suspended', 'archived'],
    destination_types: ['extension', 'ring_group', 'call_queue', 'ivr_menu', 'hangup', 'voicemail_placeholder'],
    actions: ['repeat', 'route', 'hangup'],
    digits: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '#'],
  });
  const routePlan$ = new BehaviorSubject<IvrRoutePlan | null>({
    ivr_menu: { id: 1, uuid: 'ivr-menu-1', name: 'Main IVR', slug: 'main-ivr', status: 'active' },
    resolved_at: new Date('2026-06-30T10:00:00.000Z').toISOString(),
    input_type: 'digit',
    digit: '1',
    reason: 'digit',
    option: {
      id: 100,
      uuid: 'ivr-option-1',
      digit: '1',
      label: 'Sales',
    },
    destination: { type: 'call_queue', id: 22, summary: 'Call queue: Sales Queue' },
    notes: ['Dry-run only'],
  });
  const filters$ = new BehaviorSubject({ search: '', status: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const optionsLoading$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);

  const ivrStateMock = {
    menus$,
    activeMenu$,
    activeOptions$,
    options$,
    routePlan$,
    filters$,
    pagination$,
    loading$,
    saving$,
    detailLoading$,
    optionsLoading$,
    error$,
    get activeMenu() {
      return activeMenu$.value;
    },
    init: vi.fn().mockResolvedValue(undefined),
    selectMenu: vi.fn((menu: IvrMenuItem | null) => activeMenu$.next(menu)),
    openMenu: vi.fn().mockResolvedValue(undefined),
    createMenu: vi.fn().mockResolvedValue({ id: 2 }),
    updateMenu: vi.fn().mockResolvedValue({ id: 1 }),
    deleteMenu: vi.fn().mockResolvedValue(true),
    createOption: vi.fn().mockResolvedValue({ id: 101 }),
    updateOption: vi.fn().mockResolvedValue({ id: 100 }),
    deleteOption: vi.fn().mockResolvedValue(true),
    testRoute: vi.fn().mockResolvedValue(routePlan$.value),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
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
    activeMenu$.next(null);
    activeOptions$.next([
      {
        id: 100,
        uuid: 'ivr-option-1',
        tenant_id: 'tenant-a',
        ivr_menu_id: 1,
        digit: '1',
        label: 'Sales',
        destination_type: 'call_queue',
        destination_id: 22,
        destination_summary: 'call_queue:22',
        priority: 1,
        is_active: true,
        metadata: {},
      },
    ]);

    vi.spyOn(window, 'confirm').mockReturnValue(true);
    vi.spyOn(window, 'prompt').mockReturnValue('1');

    await TestBed.configureTestingModule({
      imports: [IvrShellComponent],
      providers: [
        { provide: IvrsStateService, useValue: ivrStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
        { provide: TenantContextService, useValue: tenantContextMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(IvrShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads ivr menus on init when a tenant is selected', () => {
    expect(ivrStateMock.init).toHaveBeenCalled();
  });

  it('does not load menus before tenant selection', () => {
    tenantSelected = false;
    fixture = TestBed.createComponent(IvrShellComponent);
    fixture.detectChanges();

    expect(ivrStateMock.init).toHaveBeenCalledTimes(1);
  });

  it('selects a menu and drives create, edit, option and route flows', async () => {
    const row: HTMLTableRowElement | null = fixture.nativeElement.querySelector('[data-testid="ivr-menu-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(ivrStateMock.selectMenu).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(ivrStateMock.openMenu).toHaveBeenCalledWith(1);

    component.openCreateMenu();
    await component.saveMenu({
      name: 'New IVR',
      slug: 'new-ivr',
      description: 'Created from tests',
      status: 'active',
      greeting_text: 'Hello',
      greeting_audio_path: null,
      repeat_count: 1,
      input_timeout_seconds: 5,
      max_invalid_attempts: 3,
      timeout_action_type: 'repeat',
      timeout_destination_type: null,
      timeout_destination_id: null,
      invalid_action_type: 'repeat',
      invalid_destination_type: null,
      invalid_destination_id: null,
    });

    component.openEditMenu(menus$.value[0]);
    await component.saveMenu({
      name: 'Main IVR',
      slug: 'main-ivr',
      description: 'Updated',
      status: 'active',
      greeting_text: 'Welcome',
      greeting_audio_path: null,
      repeat_count: 1,
      input_timeout_seconds: 5,
      max_invalid_attempts: 3,
      timeout_action_type: 'route',
      timeout_destination_type: 'ring_group',
      timeout_destination_id: 12,
      invalid_action_type: 'repeat',
      invalid_destination_type: null,
      invalid_destination_id: null,
    });

    activeMenu$.next(menus$.value[0]);
    activeOptions$.next([activeOptions$.value[0]]);
    component.openCreateOption();
    await component.saveOption({
      digit: '2',
      label: 'Support',
      destination_type: 'ring_group',
      destination_id: 12,
      priority: 2,
      is_active: true,
    });

    component.openEditOption(activeOptions$.value[0]);
    await component.saveOption({
      digit: '1',
      label: 'Sales Desk',
      destination_type: 'call_queue',
      destination_id: 22,
      priority: 1,
      is_active: true,
    });

    await component.testRoute(menus$.value[0]);

    expect(ivrStateMock.createMenu).toHaveBeenCalled();
    expect(ivrStateMock.updateMenu).toHaveBeenCalledWith(1, expect.objectContaining({ description: 'Updated' }));
    expect(ivrStateMock.createOption).toHaveBeenCalledWith(1, expect.objectContaining({ digit: '2' }));
    expect(ivrStateMock.updateOption).toHaveBeenCalledWith(1, 100, expect.objectContaining({ label: 'Sales Desk' }));
    expect(ivrStateMock.testRoute).toHaveBeenCalledWith(1, 'digit', '1');
  });

  it('renders selected menu details', () => {
    activeMenu$.next(menus$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="ivr-menu-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Main IVR');
    expect(fixture.nativeElement.textContent).toContain('Route Test');
  });
});
