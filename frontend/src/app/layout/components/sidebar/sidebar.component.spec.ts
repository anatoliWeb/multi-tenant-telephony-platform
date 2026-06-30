import { Pipe, PipeTransform } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { vi } from 'vitest';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import { SidebarComponent } from './sidebar.component';
import { PermissionService } from '../../../rbac/services/permission.service';

@Pipe({
  name: 't',
  standalone: false,
})
class MockTranslatePipe implements PipeTransform {
  transform(value: string): string {
    return (
      {
        'layout.brand': 'Multi-Tenant Telephony Platform',
        'layout.nav.dashboard': 'Dashboard',
        'layout.nav.profile': 'Profile',
        'layout.nav.settings': 'Settings',
        'layout.nav.notifications': 'Notifications',
        'layout.nav.chat': 'Chat',
        'layout.nav.contacts': 'Contacts',
        'layout.nav.extensions': 'Extensions',
        'layout.nav.ringGroups': 'Ring groups',
        'layout.nav.callQueues': 'Call queues',
        'layout.nav.phoneNumbers': 'Phone numbers',
        'layout.nav.callLogs': 'Call Logs',
      }[value] ?? value
    );
  }
}

describe('SidebarComponent', () => {
  let fixture: ComponentFixture<SidebarComponent>;

  const grantedPermissions = new Set<string>();
  const tenantGrantedPermissions = new Set<string>();
  let tenantSelected = true;
  const permissionServiceMock = {
    hasPermission: vi.fn((permission: string) => grantedPermissions.has(permission)),
    hasAnyPermission: vi.fn((permissions: string[]) => permissions.some((permission) => grantedPermissions.has(permission))),
    hasTenantPermission: vi.fn((permission: string) => tenantGrantedPermissions.has(permission)),
    hasAnyTenantPermission: vi.fn((permissions: string[]) => permissions.some((permission) => tenantGrantedPermissions.has(permission))),
  };
  const tenantContextMock = {
    hasTenant: vi.fn(() => tenantSelected),
  };

  beforeEach(async () => {
    grantedPermissions.clear();
    tenantGrantedPermissions.clear();
    tenantSelected = true;

    await TestBed.configureTestingModule({
      declarations: [SidebarComponent, MockTranslatePipe],
      imports: [RouterTestingModule],
      providers: [
        { provide: PermissionService, useValue: permissionServiceMock },
        { provide: TenantContextService, useValue: tenantContextMock },
      ],
    }).compileComponents();
  });

  it('renders translated tenant navigation for an authorized role', () => {
    [
      'chat.view',
      'contacts.view',
      'extensions.view',
      'ring_groups.view',
      'call_queues.view',
      'phone_numbers.view',
      'call_logs.view',
      'settings.view',
    ].forEach((permission) => tenantGrantedPermissions.add(permission));

    fixture = TestBed.createComponent(SidebarComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Chat');
    expect(text).toContain('Contacts');
    expect(text).toContain('Extensions');
    expect(text).toContain('Ring groups');
    expect(text).toContain('Call queues');
    expect(text).toContain('Phone numbers');
    expect(text).toContain('Call Logs');
    expect(text).toContain('Settings');
    expect(text).not.toContain('layout.nav.chat');
  });

  it('hides restricted tenant navigation for a limited role', () => {
    tenantGrantedPermissions.add('chat.conversations.view');

    fixture = TestBed.createComponent(SidebarComponent);
    fixture.detectChanges();

    const nativeElement = fixture.nativeElement as HTMLElement;
    const links = Array.from(
      nativeElement.querySelectorAll('a') as NodeListOf<HTMLAnchorElement>,
    ).map((link) => link.textContent?.trim());
    expect(links).toContain('Chat');
    expect(links).not.toContain('Contacts');
    expect(links).not.toContain('Extensions');
    expect(links).not.toContain('Ring groups');
    expect(links).not.toContain('Call queues');
    expect(links).not.toContain('Phone numbers');
    expect(links).not.toContain('Call Logs');
  });

  it('hides tenant navigation until a tenant is selected', () => {
    tenantSelected = false;

    fixture = TestBed.createComponent(SidebarComponent);
    fixture.detectChanges();

    const nativeElement = fixture.nativeElement as HTMLElement;
    const links = Array.from(
      nativeElement.querySelectorAll('a') as NodeListOf<HTMLAnchorElement>,
    ).map((link) => link.textContent?.trim());
    expect(links).toEqual(['Dashboard', 'Profile']);
  });
});
