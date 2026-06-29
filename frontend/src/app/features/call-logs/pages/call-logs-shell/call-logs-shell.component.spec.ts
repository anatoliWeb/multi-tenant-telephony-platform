import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { CallLogsShellComponent } from './call-logs-shell.component';
import { CallLogsStateService } from '../../services/call-logs-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { CallLogItem } from '../../models/call-log.model';

describe('CallLogsShellComponent', () => {
  let fixture: ComponentFixture<CallLogsShellComponent>;
  let component: CallLogsShellComponent;

  const callLogs$ = new BehaviorSubject<CallLogItem[]>([
    {
      id: 1,
      uuid: 'call-1',
      provider_id: 'fake',
      provider_call_id: 'provider-call-1',
      direction: 'outbound',
      status: 'completed',
      disposition: 'answered',
      from_number: '+15550001001',
      to_number: '+15550009999',
      caller: {
        user: { id: 1, name: 'Alice', email: 'alice@example.test' },
        extension: { id: 11, number: '2001', label: 'Support' },
        phone_number: { id: 21, number: '+15550001001', display_number: '+1 555 000 1001' },
        contact: null,
      },
      callee: {
        user: null,
        extension: null,
        phone_number: null,
        contact: { id: 31, display_name: 'Customer A' },
      },
      ringing_seconds: 5,
      talk_seconds: 60,
      billable_seconds: 60,
      total_seconds: 65,
      recording_available: false,
    },
  ] as CallLogItem[]);
  const activeCallLog$ = new BehaviorSubject<CallLogItem | null>(null);
  const events$ = new BehaviorSubject([
    { id: 1, uuid: 'event-1', provider_event_id: 'event-1', provider_id: 'fake', type: 'call_completed', summary: { disposition: 'answered' } },
  ]);
  const users$ = new BehaviorSubject([
    { id: 1, name: 'Alice', email: 'alice@example.test', extension: { id: 11, number: '2001', label: 'Support' } },
  ]);
  const filters$ = new BehaviorSubject({ search: '', direction: '', status: '', disposition: '', user: '', date_from: '', date_to: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 2, per_page: 15, total: 1 });
  const statistics$ = new BehaviorSubject({
    window: { date_from: '2026-06-01', date_to: '2026-06-26' },
    total_calls: 10,
    answered_calls: 7,
    missed_calls: 2,
    failed_calls: 1,
    inbound_calls: 3,
    outbound_calls: 5,
    internal_calls: 2,
    total_talk_seconds: 420,
    average_talk_seconds: 60,
    answer_rate: 70,
    calls_by_day: [],
    calls_by_status: [],
    calls_by_direction: [],
    top_users: [],
  });
  const loading$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const statisticsLoading$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);

  const callLogsStateMock = {
    callLogs$,
    activeCallLog$,
    events$,
    users$,
    filters$,
    pagination$,
    statistics$,
    loading$,
    detailLoading$,
    statisticsLoading$,
    error$,
    init: vi.fn().mockResolvedValue(undefined),
    selectCallLog: vi.fn(),
    openCallLog: vi.fn().mockResolvedValue(undefined),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setDirection: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setDisposition: vi.fn().mockResolvedValue(undefined),
    setUser: vi.fn().mockResolvedValue(undefined),
    setDateRange: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
  };

  const permissionServiceMock = {
    hasPermission: vi.fn((permission: string) => permission !== 'call_logs.export'),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CallLogsShellComponent],
      providers: [
        { provide: CallLogsStateService, useValue: callLogsStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(CallLogsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads call logs on init', () => {
    expect(callLogsStateMock.init).toHaveBeenCalledWith(true);
  });

  it('selects a call log from the list', () => {
    const row: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="call-log-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(callLogsStateMock.selectCallLog).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(callLogsStateMock.openCallLog).toHaveBeenCalledWith(1);
  });

  it('delegates filter and pagination changes to the state service', async () => {
    await component.onSearchChange('1555');
    await component.onDirectionChange('outbound');
    await component.onStatusChange('completed');
    await component.onDispositionChange('answered');
    await component.onUserChange('1');
    await component.onDateRangeChange('2026-06-01', '2026-06-26');
    await component.onPageChange(2);

    expect(callLogsStateMock.setSearch).toHaveBeenCalledWith('1555');
    expect(callLogsStateMock.setDirection).toHaveBeenCalledWith('outbound');
    expect(callLogsStateMock.setStatus).toHaveBeenCalledWith('completed');
    expect(callLogsStateMock.setDisposition).toHaveBeenCalledWith('answered');
    expect(callLogsStateMock.setUser).toHaveBeenCalledWith('1');
    expect(callLogsStateMock.setDateRange).toHaveBeenCalledWith('2026-06-01', '2026-06-26');
    expect(callLogsStateMock.setPage).toHaveBeenCalledWith(2);
  });

  it('renders statistics cards and selected call details', () => {
    activeCallLog$.next(callLogs$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="statistics-card"]')).not.toBeNull();
    expect(fixture.nativeElement.querySelector('[data-testid="call-log-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('2001');
    expect(fixture.nativeElement.textContent).toContain('Customer A');
  });

  it('formats duration safely for display', () => {
    expect(component.formatDuration(65)).toBe('1:05');
    expect(component.formatDuration(0)).toBe('0:00');
  });
});
