import { Pipe, PipeTransform } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { TopbarComponent } from './topbar.component';

@Pipe({
  name: 't',
  standalone: false,
})
class MockTranslatePipe implements PipeTransform {
  transform(value: string): string {
    return (
      {
        'layout.topbar.title': 'Multi-Tenant Telephony Platform',
        'layout.topbar.subtitle': 'Session-ready shell',
        'layout.topbar.selectTenant': 'Select tenant',
        'layout.topbar.selectedTenant': 'Selected tenant',
        'layout.topbar.noTenantSelected': 'No tenant selected',
        'layout.topbar.softphone': 'Softphone',
        'common.status.guest': 'Guest',
        'common.actions.logout': 'Logout',
      }[value] ?? value
    );
  }
}

describe('TopbarComponent', () => {
  let fixture: ComponentFixture<TopbarComponent>;
  let component: TopbarComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      declarations: [TopbarComponent, MockTranslatePipe],
    }).compileComponents();

    fixture = TestBed.createComponent(TopbarComponent);
    component = fixture.componentInstance;
  });

  it('renders platform admin tenant summaries without throwing', () => {
    component.tenants = [
      {
        id: 'tenant-a',
        uuid: 'tenant-a',
        name: 'Tenant A',
        slug: 'tenant-a',
        status: 'active',
        timezone: 'UTC',
        locale: 'en',
        currency: 'USD',
        settings: {},
        activated_at: null,
        suspended_at: null,
        created_at: null,
        updated_at: null,
      },
      {
        id: 'tenant-b',
        uuid: 'tenant-b',
        name: 'Tenant B',
        slug: 'tenant-b',
        status: 'active',
        timezone: 'UTC',
        locale: 'en',
        currency: 'USD',
        settings: {},
        activated_at: null,
        suspended_at: null,
        created_at: null,
        updated_at: null,
      },
    ];

    expect(() => fixture.detectChanges()).not.toThrow();

    const [tenantSelect] = fixture.debugElement.queryAll(By.css('select'));
    const options = tenantSelect.queryAll(By.css('option'));
    expect(options.map((option) => option.nativeElement.textContent.trim())).toEqual([
      'Select tenant',
      'Tenant A',
      'Tenant B',
    ]);
  });

  it('renders tenant membership payloads and selected state safely', () => {
    component.activeTenantId = 'tenant-a';
    component.tenants = [
      {
        id: 'membership-1',
        tenant_id: 'tenant-a',
        user_id: 1,
        status: 'active',
        invited_by: null,
        invited_at: null,
        accepted_at: null,
        activated_at: null,
        suspended_at: null,
        tenant: {
          id: 'tenant-a',
          uuid: 'tenant-a',
          name: 'Tenant A',
          slug: 'tenant-a',
          status: 'active',
          timezone: 'UTC',
          locale: 'en',
          currency: 'USD',
          settings: {},
          activated_at: null,
          suspended_at: null,
          created_at: null,
          updated_at: null,
        },
        created_at: null,
        updated_at: null,
      },
    ];

    fixture.detectChanges();

    const [tenantSelect] = fixture.debugElement.queryAll(By.css('select'));
    const tenantSelectElement = tenantSelect.nativeElement as HTMLSelectElement;
    expect(tenantSelectElement.getAttribute('aria-label')).toBe('Selected tenant');
    expect(Array.from(tenantSelectElement.options).some((option) => option.value === 'tenant-a')).toBe(true);
    expect(component.trackTenant(0, component.tenants[0])).toBe('tenant-a');
  });

  it('does not show the raw tenant selector translation key', () => {
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Select tenant');
    expect(fixture.nativeElement.textContent).not.toContain('layout.topbar.selectTenant');
  });

  it('shows the softphone launcher only when enabled', () => {
    component.canOpenSoftphone = true;
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Softphone');
  });

  it('hides the softphone launcher by default', () => {
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.softphone-btn')).toBeNull();
  });
});
