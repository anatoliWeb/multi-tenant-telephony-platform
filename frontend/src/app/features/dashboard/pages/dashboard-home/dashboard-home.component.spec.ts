import { NO_ERRORS_SCHEMA, Pipe, PipeTransform } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { BehaviorSubject, of } from 'rxjs';
import { vi } from 'vitest';
import { DashboardHomeComponent } from './dashboard-home.component';
import { TenantContextService } from '../../../../core/services/tenant-context.service';
import { AuthStateService } from '../../../../core/services/auth-state.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import { RealtimeService } from '../../../../realtime/services/realtime.service';
import { ApiClientService } from '../../../../api/services/api-client.service';

@Pipe({
  name: 't',
  standalone: false,
})
class MockTranslatePipe implements PipeTransform {
  transform(value: string): string {
    return (
      {
        'dashboard.tenantSelection.title': 'Select a tenant to continue',
        'dashboard.tenantSelection.subtitle': 'Platform Admin must choose an active tenant before tenant modules and requests become available.',
        'layout.topbar.selectTenant': 'Select tenant',
      }[value] ?? value
    );
  }
}

describe('DashboardHomeComponent', () => {
  let fixture: ComponentFixture<DashboardHomeComponent>;
  let activeTenantSubject: BehaviorSubject<{ id: string; name: string } | null>;
  const realtimeMock = {
    status$: of({ connected: false }),
    events$: of([]),
    activityEvents$: of([]),
    onlineUsers$: of([]),
    dashboardPresence$: of([]),
    connect: vi.fn(),
    joinPresence: vi.fn(),
    leavePresence: vi.fn(),
    clearEvents: vi.fn(),
  };
  const apiClientMock = {
    get: vi.fn(() => of({ data: { users: 0, tokens: 0, recent_activity: [] } })),
    post: vi.fn(),
  };
  const authStateMock = {
    user$: of(null),
    permissions$: of([]),
    roles$: of([]),
  };
  const permissionServiceMock = {
    hasRole: vi.fn(() => false),
  };

  beforeEach(async () => {
    activeTenantSubject = new BehaviorSubject<{ id: string; name: string } | null>(null);
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      declarations: [DashboardHomeComponent, MockTranslatePipe],
      providers: [
        { provide: RealtimeService, useValue: realtimeMock },
        { provide: ApiClientService, useValue: apiClientMock },
        { provide: AuthStateService, useValue: authStateMock },
        { provide: PermissionService, useValue: permissionServiceMock },
        {
          provide: TenantContextService,
          useValue: {
            activeTenant$: activeTenantSubject.asObservable(),
            hasTenant: vi.fn(() => Boolean(activeTenantSubject.value)),
          },
        },
      ],
      schemas: [NO_ERRORS_SCHEMA],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardHomeComponent);
  });

  it('renders translated tenant-selection copy before a tenant is selected', () => {
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Select a tenant to continue');
    expect(text).toContain('Platform Admin must choose an active tenant before tenant modules and requests become available.');
    expect(text).not.toContain('dashboard.tenantSelection.title');
    expect(text).not.toContain('dashboard.tenantSelection.subtitle');
  });

  it('does not request dashboard stats before an active tenant exists', () => {
    fixture.detectChanges();

    expect(apiClientMock.get).not.toHaveBeenCalled();
    expect(realtimeMock.connect).not.toHaveBeenCalled();
  });
});
