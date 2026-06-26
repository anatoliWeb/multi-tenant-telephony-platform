import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { PhoneNumbersShellComponent } from './phone-numbers-shell.component';
import { PhoneNumbersStateService } from '../../services/phone-numbers-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { PhoneNumberItem } from '../../models/phone-number.model';

describe('PhoneNumbersShellComponent', () => {
  let fixture: ComponentFixture<PhoneNumbersShellComponent>;
  let component: PhoneNumbersShellComponent;

  const phoneNumbers$ = new BehaviorSubject<PhoneNumberItem[]>([
    {
      id: 1,
      uuid: 'did-1',
      number: '+15550001001',
      normalized_number: '+15550001001',
      display_number: '+1 555 000 1001',
      type: 'did',
      status: 'active',
      assignment_status: 'assigned',
      is_primary: true,
      provider_name: 'manual',
      assigned_user: {
        id: 1,
        name: 'Alice',
        email: 'alice@example.test',
        extension: { id: 11, number: '2001', label: 'Support' },
      },
    },
  ]);
  const activePhoneNumber$ = new BehaviorSubject<PhoneNumberItem | null>(null);
  const users$ = new BehaviorSubject([
    { id: 1, name: 'Alice', email: 'alice@example.test', extension: { id: 11, number: '2001', label: 'Support' } },
  ]);
  const filters$ = new BehaviorSubject({ search: '', status: '', assigned: '', primary: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);

  const phoneNumbersStateMock = {
    phoneNumbers$,
    activePhoneNumber$,
    users$,
    filters$,
    pagination$,
    loading$,
    saving$,
    detailLoading$,
    error$,
    init: vi.fn().mockResolvedValue(undefined),
    selectPhoneNumber: vi.fn(),
    openPhoneNumber: vi.fn().mockResolvedValue(undefined),
    createPhoneNumber: vi.fn().mockResolvedValue({ id: 2 }),
    updatePhoneNumber: vi.fn().mockResolvedValue({ id: 1 }),
    assignPhoneNumber: vi.fn().mockResolvedValue(undefined),
    unassignPhoneNumber: vi.fn().mockResolvedValue(undefined),
    setPrimary: vi.fn().mockResolvedValue(undefined),
    activate: vi.fn().mockResolvedValue(undefined),
    suspend: vi.fn().mockResolvedValue(undefined),
    release: vi.fn().mockResolvedValue(undefined),
    deletePhoneNumber: vi.fn().mockResolvedValue(true),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setAssigned: vi.fn().mockResolvedValue(undefined),
    setPrimaryFilter: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
  };

  const permissionServiceMock = {
    hasPermission: vi.fn(() => true),
  };

  beforeEach(async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    await TestBed.configureTestingModule({
      imports: [PhoneNumbersShellComponent],
      providers: [
        { provide: PhoneNumbersStateService, useValue: phoneNumbersStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PhoneNumbersShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads phone numbers on init', () => {
    expect(phoneNumbersStateMock.init).toHaveBeenCalled();
  });

  it('selects a phone number from the list', () => {
    const row: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="phone-number-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(phoneNumbersStateMock.selectPhoneNumber).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(phoneNumbersStateMock.openPhoneNumber).toHaveBeenCalledWith(1);
  });

  it('delegates filter changes to the state service', async () => {
    await component.onSearchChange('1001');
    await component.onStatusChange('active');
    await component.onAssignedChange('assigned');
    await component.onPrimaryChange('true');
    await component.onPageChange(2);

    expect(phoneNumbersStateMock.setSearch).toHaveBeenCalledWith('1001');
    expect(phoneNumbersStateMock.setStatus).toHaveBeenCalledWith('active');
    expect(phoneNumbersStateMock.setAssigned).toHaveBeenCalledWith('assigned');
    expect(phoneNumbersStateMock.setPrimaryFilter).toHaveBeenCalledWith('true');
    expect(phoneNumbersStateMock.setPage).toHaveBeenCalledWith(2);
  });

  it('supports create, assign, primary, and release flows', async () => {
    component.openCreate();
    await component.savePhoneNumber({
      number: '+15550001002',
      display_number: '+1 555 000 1002',
      status: 'active',
      assigned_user_id: 1,
      is_primary: false,
      type: 'did',
    });

    await component.assignToUser(phoneNumbers$.value[0], '1');
    await component.setPrimary(phoneNumbers$.value[0]);
    await component.unassign(phoneNumbers$.value[0]);
    await component.release(phoneNumbers$.value[0]);

    expect(phoneNumbersStateMock.createPhoneNumber).toHaveBeenCalled();
    expect(phoneNumbersStateMock.assignPhoneNumber).toHaveBeenCalledWith(1, 1, false);
    expect(phoneNumbersStateMock.setPrimary).toHaveBeenCalledWith(1);
    expect(phoneNumbersStateMock.unassignPhoneNumber).toHaveBeenCalledWith(1);
    expect(phoneNumbersStateMock.release).toHaveBeenCalledWith(1);
  });

  it('renders selected DID details with related extension only as informational data', () => {
    activePhoneNumber$.next(phoneNumbers$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="phone-number-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('+1 555 000 1001');
    expect(fixture.nativeElement.textContent).toContain('2001');
    expect(fixture.nativeElement.textContent).not.toContain('Assigned contact');
  });
});
