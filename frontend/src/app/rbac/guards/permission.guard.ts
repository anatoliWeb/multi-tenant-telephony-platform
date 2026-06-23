import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, Router, UrlTree } from '@angular/router';
import { PermissionService } from '../services/permission.service';

@Injectable()
export class PermissionGuard implements CanActivate {
  constructor(
    private readonly permissionService: PermissionService,
    private readonly router: Router,
  ) {}

  canActivate(route: ActivatedRouteSnapshot): boolean | UrlTree {
    const requiredPermission = route.data['permission'] as string | undefined;
    const requiredPermissions = route.data['permissions'] as string[] | undefined;

    if (!requiredPermission && (!requiredPermissions || requiredPermissions.length === 0)) {
      return true;
    }

    const hasAccess = requiredPermission
      ? this.permissionService.can(requiredPermission)
      : this.permissionService.hasAnyPermission(requiredPermissions ?? []);

    return hasAccess ? true : this.router.createUrlTree(['/dashboard']);
  }
}
