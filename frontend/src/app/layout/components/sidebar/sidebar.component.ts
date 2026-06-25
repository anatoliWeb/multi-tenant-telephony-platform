import { Component } from '@angular/core';
import { PermissionService } from '../../../rbac/services/permission.service';

type NavItem = {
  route: string;
  labelKey: string;
  permission?: string;
  permissions?: string[];
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
    { route: '/settings', labelKey: 'layout.nav.settings', permission: 'settings.view' },
    { route: '/notifications', labelKey: 'layout.nav.notifications', permissions: ['notifications.view'] },
    { route: '/chat', labelKey: 'layout.nav.chat', permissions: ['chat.view', 'chat.conversations.view'] },
    { route: '/contacts', labelKey: 'layout.nav.contacts', permissions: ['contacts.view'] },
  ];

  constructor(private readonly permissionService: PermissionService) {}

  get visibleNavItems(): NavItem[] {
    return this.navItems.filter((item) => this.canAccess(item));
  }

  private canAccess(item: NavItem): boolean {
    if (item.permission) {
      return this.permissionService.hasPermission(item.permission);
    }

    if (item.permissions && item.permissions.length > 0) {
      return this.permissionService.hasAnyPermission(item.permissions);
    }

    return true;
  }
}
