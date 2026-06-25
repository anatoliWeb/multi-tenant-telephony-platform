import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject } from 'rxjs';
import { vi } from 'vitest';
import { ExtensionsShellComponent } from './extensions-shell.component';
import { ExtensionsStateService } from '../../services/extensions-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { ExtensionItem } from '../../models/extension.model';

describe('ExtensionsShellComponent', () => {
  let fixture: ComponentFixture<ExtensionsShellComponent>;
  let component: ExtensionsShellComponent;

  const extensions$ = new BehaviorSubject<ExtensionItem[]>([
    {
      id: 1,
      uuid: 'extension-1',
      tenant_id: 'tenant-a',
      number: '2001',
      label: 'Support Desk',
      status: 'active',
      provisioning_status: 'provisioned',
      registration_status: 'unregistered',
      assigned_user: { id: 1, name: 'Alice', email: 'alice@example.test' },
      assigned_contact: null,
      credential: { username: '2001', secret_hint: 'abcd', version: 1 },
      provider_state: { provider: 'fake', endpoint_status: 'active', registration_status: 'unregistered' },
    },
  ]);
  const activeExtension$ = new BehaviorSubject<ExtensionItem | null>(null);
  const users$ = new BehaviorSubject([{ id: 1, name: 'Alice', email: 'alice@example.test' }]);
  const contacts$ = new BehaviorSubject([{ id: 10, display_name: 'Support Contact', company_name: 'Acme' }]);
  const filters$ = new BehaviorSubject({ search: '', status: '', assigned: '', page: 1, per_page: 15 });
  const pagination$ = new BehaviorSubject({ current_page: 1, last_page: 1, per_page: 15, total: 1 });
  const loading$ = new BehaviorSubject(false);
  const saving$ = new BehaviorSubject(false);
  const detailLoading$ = new BehaviorSubject(false);
  const latestSecret$ = new BehaviorSubject<string | null>(null);
  const error$ = new BehaviorSubject<string | null>(null);

  const extensionsStateMock = {
    extensions$,
    activeExtension$,
    users$,
    contacts$,
    filters$,
    pagination$,
    loading$,
    saving$,
    detailLoading$,
    latestSecret$,
    error$,
    init: vi.fn().mockResolvedValue(undefined),
    selectExtension: vi.fn(),
    openExtension: vi.fn().mockResolvedValue(undefined),
    createExtension: vi.fn().mockResolvedValue({ id: 2 }),
    updateExtension: vi.fn().mockResolvedValue({ id: 1 }),
    rotateCredentials: vi.fn().mockResolvedValue(undefined),
    deleteExtension: vi.fn().mockResolvedValue(true),
    dismissLatestSecret: vi.fn(),
    setSearch: vi.fn().mockResolvedValue(undefined),
    setStatus: vi.fn().mockResolvedValue(undefined),
    setAssigned: vi.fn().mockResolvedValue(undefined),
    setPage: vi.fn().mockResolvedValue(undefined),
  };

  const permissionServiceMock = {
    hasPermission: vi.fn(() => true),
  };

  beforeEach(async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    await TestBed.configureTestingModule({
      imports: [ExtensionsShellComponent],
      providers: [
        { provide: ExtensionsStateService, useValue: extensionsStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ExtensionsShellComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads extensions on init', () => {
    expect(extensionsStateMock.init).toHaveBeenCalled();
  });

  it('selects an extension from the list', async () => {
    const row: HTMLButtonElement | null = fixture.nativeElement.querySelector('[data-testid="extension-row"]');
    expect(row).not.toBeNull();
    row?.click();

    expect(extensionsStateMock.selectExtension).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    expect(extensionsStateMock.openExtension).toHaveBeenCalledWith(1);
  });

  it('create and rotate flows delegate to the state service', async () => {
    component.openCreate();
    await component.saveExtension({
      number: '2002',
      label: 'Sales Desk',
      status: 'active',
      assigned_user_id: 1,
      assigned_contact_id: 10,
    });

    await component.rotateCredentials(extensions$.value[0]);

    expect(extensionsStateMock.createExtension).toHaveBeenCalled();
    expect(extensionsStateMock.rotateCredentials).toHaveBeenCalledWith(1);
  });

  it('renders selected extension details', () => {
    activeExtension$.next(extensions$.value[0]);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[data-testid="extension-detail"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('2001');
  });
});
