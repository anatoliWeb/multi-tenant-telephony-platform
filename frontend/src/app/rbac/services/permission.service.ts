import { Injectable } from '@angular/core';
import { AuthStateService } from '../../core/services/auth-state.service';

@Injectable({ providedIn: 'root' })
export class PermissionService {
  constructor(private readonly authState: AuthStateService) {}

  hasPermission(permission: string): boolean {
    return this.authState.hasPermission(permission);
  }

  hasTenantPermission(permission: string): boolean {
    return this.authState.hasTenantPermission(permission);
  }

  hasRole(role: string): boolean {
    return this.authState.hasRole(role);
  }

  can(permission: string): boolean {
    return this.hasPermission(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasPermission(permission));
  }

  hasAnyTenantPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasTenantPermission(permission));
  }
}
