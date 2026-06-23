import { Component, OnInit } from '@angular/core';
import { RealtimeService } from '../../../../realtime/services/realtime.service';
import { NotificationsService } from '../../services/notifications.service';
import { PermissionService } from '../../../../rbac/services/permission.service';
import type { NotificationPreferences } from '../../models/notification.model';

@Component({
  selector: 'app-notifications-home',
  templateUrl: './notifications-home.component.html',
  styleUrls: ['./notifications-home.component.scss'],
  standalone: false,
})
export class NotificationsHomeComponent implements OnInit {
  readonly items$;
  readonly unreadCount$;
  readonly loading$;
  readonly error$;
  readonly preferences$;
  readonly canDelete: boolean;
  isMutating = false;
  isPreferencesSaving = false;
  preferencesMessage: string | null = null;
  draftPreferences: NotificationPreferences = {
    'system.enabled': true,
    'realtime.enabled': true,
    'email.enabled': true,
    'activity.enabled': true,
  };

  constructor(
    private readonly notifications: NotificationsService,
    private readonly realtime: RealtimeService,
    private readonly permissionService: PermissionService,
  ) {
    this.items$ = this.notifications.items$;
    this.unreadCount$ = this.notifications.unreadCount$;
    this.loading$ = this.notifications.loading$;
    this.error$ = this.notifications.error$;
    this.preferences$ = this.notifications.preferences$;
    this.canDelete = this.permissionService.hasPermission('notifications.delete');
  }

  ngOnInit(): void {
    this.realtime.connect();
    this.notifications.init();
    this.preferences$.subscribe((preferences: NotificationPreferences) => {
      this.draftPreferences = { ...preferences };
    });
  }

  async refresh(): Promise<void> {
    await this.notifications.refresh();
  }

  async markAsRead(id: string): Promise<void> {
    this.isMutating = true;
    try {
      await this.notifications.markAsRead(id);
    } finally {
      this.isMutating = false;
    }
  }

  async markAllAsRead(): Promise<void> {
    this.isMutating = true;
    try {
      await this.notifications.markAllAsRead();
    } finally {
      this.isMutating = false;
    }
  }

  async delete(id: string): Promise<void> {
    this.isMutating = true;
    try {
      await this.notifications.delete(id);
    } finally {
      this.isMutating = false;
    }
  }

  setPreference(key: keyof NotificationPreferences, value: boolean): void {
    this.draftPreferences = {
      ...this.draftPreferences,
      [key]: value,
    };
  }

  hasPreferenceChanges(current: NotificationPreferences | null): boolean {
    if (!current) {
      return false;
    }

    return (Object.keys(this.draftPreferences) as Array<keyof NotificationPreferences>)
      .some((key) => this.draftPreferences[key] !== current[key]);
  }

  async savePreferences(): Promise<void> {
    this.isPreferencesSaving = true;
    this.preferencesMessage = null;
    try {
      await this.notifications.savePreferences(this.draftPreferences);
      this.preferencesMessage = 'Preferences saved.';
    } catch (error) {
      this.preferencesMessage = (error as { message?: string })?.message ?? 'Failed to save preferences.';
    } finally {
      this.isPreferencesSaving = false;
    }
  }
}
