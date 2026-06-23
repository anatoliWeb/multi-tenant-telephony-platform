import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router } from '@angular/router';
import { vi } from 'vitest';
import { PermissionGuard } from './permission.guard';
import { PermissionService } from '../services/permission.service';

describe('PermissionGuard', () => {
  let guard: PermissionGuard;
  let permissionService: { can: ReturnType<typeof vi.fn>; hasAnyPermission: ReturnType<typeof vi.fn> };
  let router: { createUrlTree: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    permissionService = {
      can: vi.fn(),
      hasAnyPermission: vi.fn(),
    };
    router = {
      createUrlTree: vi.fn().mockReturnValue({ redirected: true }),
    };

    TestBed.configureTestingModule({
      providers: [
        PermissionGuard,
        { provide: PermissionService, useValue: permissionService },
        { provide: Router, useValue: router },
      ],
    });

    guard = TestBed.inject(PermissionGuard);
  });

  it('allows route when user has required single permission', () => {
    permissionService.can.mockReturnValue(true);
    const route = ({ data: { permission: 'chat.view' } } as unknown) as ActivatedRouteSnapshot;

    const result = guard.canActivate(route);

    expect(permissionService.can).toHaveBeenCalledWith('chat.view');
    expect(result).toBe(true);
  });

  it('denies route and redirects when single permission missing', () => {
    permissionService.can.mockReturnValue(false);
    const route = ({ data: { permission: 'chat.view' } } as unknown) as ActivatedRouteSnapshot;

    const result = guard.canActivate(route);

    expect(permissionService.can).toHaveBeenCalledWith('chat.view');
    expect(router.createUrlTree).toHaveBeenCalledWith(['/dashboard']);
    expect(result).toEqual({ redirected: true });
  });

  it('supports OR permissions via permissions array', () => {
    permissionService.hasAnyPermission.mockReturnValue(true);
    const route = ({ data: { permissions: ['chat.view', 'chat.conversations.view'] } } as unknown) as ActivatedRouteSnapshot;

    const result = guard.canActivate(route);

    expect(permissionService.hasAnyPermission).toHaveBeenCalledWith(['chat.view', 'chat.conversations.view']);
    expect(result).toBe(true);
  });
});

