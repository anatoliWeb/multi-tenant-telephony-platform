import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { ContactsShellComponent } from './contacts-shell.component';
import { ContactsStateService } from '../../services/contacts-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { ContactItem, ContactTag } from '../../models/contact.model';

describe('ContactsShellComponent', () => {
  let fixture: ComponentFixture<ContactsShellComponent>;
  let component: ContactsShellComponent;

  const contacts$ = new BehaviorSubject<ContactItem[]>([
    {
      id: 1,
      uuid: 'contact-1',
      tenant_id: 'tenant-a',
      display_name: 'Alice Able',
      company_name: 'Acme',
      status: 'active',
      phones: [{ id: 1, uuid: 'phone-1', raw_number: '+15550000001', normalized_number: '15550000001', is_primary: true, is_sms_capable: false, is_active: true }],
      emails: [],
      tags: [],
      primary_phone: { id: 1, uuid: 'phone-1', raw_number: '+15550000001', normalized_number: '15550000001', is_primary: true, is_sms_capable: false, is_active: true },
    },
  ]);
  const tags$ = new BehaviorSubject<ContactTag[]>([
    { id: 10, uuid: 'tag-1', tenant_id: 'tenant-a', name: 'VIP', slug: 'vip' },
  ]);
  const activeContact$ = new BehaviorSubject<ContactItem | null>(null);
  const filters$ = new BehaviorSubject({ search: '', status: '', tag: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const error$ = new BehaviorSubject<string | null>(null);
  const detailLoading$ = new BehaviorSubject(false);

  const contactsStateMock = {
    contacts$,
    tags$,
    activeContact$,
    filters$,
    pagination$,
    loading$,
    saving$,
    error$,
    detailLoading$,
    init: vi.fn().mockResolvedValue(undefined),
    selectContact: vi.fn(),
    openContact: vi.fn().mockResolvedValue(undefined),
    createContact: vi.fn().mockResolvedValue({ id: 2 }),
    updateContact: vi.fn().mockResolvedValue({ id: 1 }),
    deleteContact: vi.fn().mockResolvedValue(true),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setTag: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
    exportContacts: vi.fn(),
  };

  const permissionServiceMock = {
    hasPermission: vi.fn((permission: string) => permission !== 'contacts.delete'),
  };

  beforeEach(async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    await TestBed.configureTestingModule({
      imports: [ContactsShellComponent],
      providers: [
        { provide: ContactsStateService, useValue: contactsStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ContactsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads contacts on init', () => {
    expect(contactsStateMock.init).toHaveBeenCalled();
  });

  it('selects a contact from the list', async () => {
    const row: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="contact-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(contactsStateMock.selectContact).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(contactsStateMock.openContact).toHaveBeenCalledWith(1);
  });

  it('search and filters delegate to state service', async () => {
    await component.onSearchChange('alice');
    await component.onStatusChange('active');
    await component.onTagChange('vip');
    await component.onPageChange(2);

    expect(contactsStateMock.setSearch).toHaveBeenCalledWith('alice');
    expect(contactsStateMock.setStatus).toHaveBeenCalledWith('active');
    expect(contactsStateMock.setTag).toHaveBeenCalledWith('vip');
    expect(contactsStateMock.setPage).toHaveBeenCalledWith(2);
  });

  it('create flow opens modal and saves through state service', async () => {
    component.openCreate();
    expect(component.isModalOpen).toBe(true);

    await component.saveContact({
      display_name: 'New Contact',
      status: 'active',
      phones: [{ raw_number: '+15550000002', is_primary: true, is_sms_capable: false, is_active: true }],
      emails: [],
      tag_ids: [],
    });

    expect(contactsStateMock.createContact).toHaveBeenCalled();
    expect(component.isModalOpen).toBe(false);
  });

  it('edit flow saves through update service', async () => {
    component.openEdit(contacts$.value[0]);

    await component.saveContact({
      display_name: 'Alice Updated',
      status: 'archived',
      phones: [{ raw_number: '+15550000003', is_primary: true, is_sms_capable: false, is_active: true }],
      emails: [],
      tag_ids: [10],
    });

    expect(contactsStateMock.updateContact).toHaveBeenCalledWith(1, expect.objectContaining({ display_name: 'Alice Updated' }));
  });

  it('delete action respects permissions and delegates to state service', async () => {
    activeContact$.next(contacts$.value[0]);
    fixture.detectChanges();

    expect(component.canDelete).toBe(false);
    await component.deleteContact(contacts$.value[0]);

    expect(contactsStateMock.deleteContact).toHaveBeenCalledWith(1);
  });

  it('detail pane renders selected contact', () => {
    activeContact$.next(contacts$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="contact-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Alice Able');
  });
});
