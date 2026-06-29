import { Component } from '@angular/core';
import { TenantContextService } from '../../../core/services/tenant-context.service';
import { PermissionService } from '../../../rbac/services/permission.service';

type NavItem = {
  route: string;
  labelKey: string;
  permission?: string;
  permissions?: string[];
  scope?: 'platform' | 'tenant';
};

@Component({
  selector: 'app-sidebar',
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.scss'],
  standalone: false,
})
export class SidebarComponent {
  readonly navItems: NavItem[] = [
    { route: '/dashboard', labelKey: 'layout.nav.dashboard' },
    { route: '/profile', labelKey: 'layout.nav.profile' },
    { route: '/settings', labelKey: 'layout.nav.settings', permission: 'settings.view', scope: 'tenant' },
    { route: '/notifications', labelKey: 'layout.nav.notifications', permissions: ['notifications.view'], scope: 'tenant' },
    { route: '/chat', labelKey: 'layout.nav.chat', permissions: ['chat.view', 'chat.conversations.view'], scope: 'tenant' },
    { route: '/contacts', labelKey: 'layout.nav.contacts', permissions: ['contacts.view'], scope: 'tenant' },
    { route: '/extensions', labelKey: 'layout.nav.extensions', permissions: ['extensions.view'], scope: 'tenant' },
    { route: '/phone-numbers', labelKey: 'layout.nav.phoneNumbers', permissions: ['phone_numbers.view'], scope: 'tenant' },
    { route: '/call-logs', labelKey: 'layout.nav.callLogs', permissions: ['call_logs.view'], scope: 'tenant' },
  ];

  constructor(
    private readonly permissionService: PermissionService,
    private readonly tenantContext: TenantContextService,
  ) {}

  get visibleNavItems(): NavItem[] {
    return this.navItems.filter((item) => this.canAccess(item));
  }

  private canAccess(item: NavItem): boolean {
    if (item.scope === 'tenant' && !this.tenantContext.hasTenant()) {
      return false;
    }

    if (item.permission) {
      return item.scope === 'tenant'
        ? this.permissionService.hasTenantPermission(item.permission)
        : this.permissionService.hasPermission(item.permission);
    }

    if (item.permissions && item.permissions.length > 0) {
      return item.scope === 'tenant'
        ? this.permissionService.hasAnyTenantPermission(item.permissions)
        : this.permissionService.hasAnyPermission(item.permissions);
    }

    return true;
  }
}
