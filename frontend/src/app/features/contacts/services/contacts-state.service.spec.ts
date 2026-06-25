import { of, throwError } from 'rxjs';
import { vi } from 'vitest';
import { ContactsStateService } from './contacts-state.service';
import { ContactsApiService } from './contacts-api.service';

describe('ContactsStateService', () => {
  let service: ContactsStateService;
  let api: {
    listContacts: ReturnType<typeof vi.fn>;
    getContact: ReturnType<typeof vi.fn>;
    createContact: ReturnType<typeof vi.fn>;
    updateContact: ReturnType<typeof vi.fn>;
    deleteContact: ReturnType<typeof vi.fn>;
    listTags: ReturnType<typeof vi.fn>;
    exportContactsUrl: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    api = {
      listContacts: vi.fn(),
      getContact: vi.fn(),
      createContact: vi.fn(),
      updateContact: vi.fn(),
      deleteContact: vi.fn(),
      listTags: vi.fn(),
      exportContactsUrl: vi.fn().mockReturnValue('http://example.test/api/v1/contacts/export'),
    };

    service = new ContactsStateService(api as unknown as ContactsApiService);
  });

  it('loads contacts and tags during init', async () => {
    api.listContacts.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 1, uuid: 'contact-1', tenant_id: 'tenant-a', display_name: 'Alice Able', status: 'active' }],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }));
    api.listTags.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 10, uuid: 'tag-1', tenant_id: 'tenant-a', name: 'VIP', slug: 'vip' }],
    }));

    await service.init();

    let contactsCount = 0;
    let tagsCount = 0;
    service.contacts$.subscribe((items) => { contactsCount = items.length; });
    service.tags$.subscribe((items) => { tagsCount = items.length; });

    expect(api.listContacts).toHaveBeenCalledWith(expect.objectContaining({ page: 1, per_page: 15 }));
    expect(api.listTags).toHaveBeenCalled();
    expect(contactsCount).toBe(1);
    expect(tagsCount).toBe(1);
  });

  it('applies filters through backend reloads', async () => {
    api.listContacts.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }));

    await service.setSearch('alice');
    await service.setStatus('active');
    await service.setTag('vip');
    await service.setPage(2);

    expect(api.listContacts).toHaveBeenLastCalledWith(expect.objectContaining({
      search: 'alice',
      status: 'active',
      tag: 'vip',
      page: 2,
    }));
  });

  it('opens, creates, updates, and deletes contacts', async () => {
    api.getContact.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, uuid: 'contact-7', tenant_id: 'tenant-a', display_name: 'Alice', status: 'active' },
    }));
    api.createContact.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 8, uuid: 'contact-8', tenant_id: 'tenant-a', display_name: 'Bob', status: 'active' },
    }));
    api.updateContact.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: { id: 7, uuid: 'contact-7', tenant_id: 'tenant-a', display_name: 'Alice Updated', status: 'archived' },
    }));
    api.deleteContact.mockReturnValue(of({ success: true, message: 'ok', data: { deleted: true } }));
    api.listContacts.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }));

    await service.openContact(7);
    await service.createContact({ display_name: 'Bob', status: 'active', phones: [], emails: [], tag_ids: [] });
    await service.updateContact(7, { display_name: 'Alice Updated', status: 'archived', phones: [], emails: [], tag_ids: [] });
    await service.deleteContact(7);

    expect(api.getContact).toHaveBeenCalledWith(7);
    expect(api.createContact).toHaveBeenCalled();
    expect(api.updateContact).toHaveBeenCalledWith(7, expect.objectContaining({ display_name: 'Alice Updated' }));
    expect(api.deleteContact).toHaveBeenCalledWith(7);
  });

  it('resets tenant-scoped state fully', async () => {
    api.listContacts.mockReturnValue(of({
      success: true,
      message: 'ok',
      data: [{ id: 1, uuid: 'contact-1', tenant_id: 'tenant-a', display_name: 'Alice Able', status: 'active' }],
      meta: { current_page: 2, last_page: 3, per_page: 15, total: 20 },
    }));

    await service.loadContacts();
    service.selectContact({ id: 1, uuid: 'contact-1', tenant_id: 'tenant-a', display_name: 'Alice Able', status: 'active' });
    service.resetForTenantChange();

    let contactsCount = -1;
    let activeId: number | null = 99;
    service.contacts$.subscribe((items) => { contactsCount = items.length; });
    service.activeContact$.subscribe((item) => { activeId = item?.id ?? null; });

    expect(contactsCount).toBe(0);
    expect(activeId).toBeNull();
    expect(service.filters).toEqual({ search: '', status: '', tag: '', page: 1, per_page: 15 });
  });

  it('stores safe error messages', async () => {
    api.listContacts.mockReturnValue(throwError(() => new Error('Contacts failed')));

    await service.loadContacts();

    let errorMessage: string | null = null;
    service.error$.subscribe((value) => { errorMessage = value; });

    expect(errorMessage).toBe('Contacts failed');
  });
});
