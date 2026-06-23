import { Injectable } from '@angular/core';
import { BehaviorSubject, firstValueFrom } from 'rxjs';
import { ApiClientService } from '../../../api/services/api-client.service';
import type { NotificationPreferences, NotificationPreview } from '../models/notification.model';
import { RealtimeService } from '../../../realtime/services/realtime.service';

interface NotificationApiItem {
  id?: string;
  type?: string;
  title?: string | null;
  message?: string | null;
  is_read?: boolean;
  read_at?: string | null;
  created_at?: string | null;
}

interface NotificationUnreadCountPayload {
  count?: number;
}

interface NotificationPreferencesPayload {
  preferences?: NotificationPreferences;
}

@Injectable({ providedIn: 'root' })
export class NotificationsService {
  private readonly itemsSubject = new BehaviorSubject<NotificationPreview[]>([]);
  private readonly unreadCountSubject = new BehaviorSubject<number>(0);
  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  private readonly errorSubject = new BehaviorSubject<string | null>(null);
  private readonly preferencesSubject = new BehaviorSubject<NotificationPreferences>({
    'system.enabled': true,
    'realtime.enabled': true,
    'email.enabled': true,
    'activity.enabled': true,
  });
  private refreshTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly refreshDelayMs = 1200;
  private initialized = false;

  readonly items$ = this.itemsSubject.asObservable();
  readonly unreadCount$ = this.unreadCountSubject.asObservable();
  readonly loading$ = this.loadingSubject.asObservable();
  readonly error$ = this.errorSubject.asObservable();
  readonly preferences$ = this.preferencesSubject.asObservable();

  constructor(
    private readonly realtimeService: RealtimeService,
    private readonly apiClient: ApiClientService,
  ) {}

  init(): void {
    if (this.initialized) {
      return;
    }

    this.initialized = true;
    this.realtimeService.events$.subscribe((events) => {
      if (events.length === 0) {
        return;
      }

      this.scheduleRefresh();
    });
    this.realtimeService.notificationCreated$.subscribe((events) => {
      if (events.length === 0) {
        return;
      }

      this.scheduleRefresh();
    });

    void this.refresh();
  }

  async refresh(): Promise<void> {
    this.loadingSubject.next(true);
    this.errorSubject.next(null);

    try {
      await Promise.all([
        this.loadList(),
        this.loadUnreadCount(),
        this.loadPreferences(),
      ]);
    } catch (error) {
      const message = (error as { message?: string })?.message ?? 'Failed to load notifications.';
      this.errorSubject.next(message);
    } finally {
      this.loadingSubject.next(false);
    }
  }

  async markAsRead(id: string): Promise<void> {
    await firstValueFrom(this.apiClient.patch(`/v1/notifications/${id}/read`, {}));
    this.itemsSubject.next(this.itemsSubject.value.map((item) => (item.id === id ? { ...item, read: true } : item)));
    this.recalculateUnreadCount();
    await this.loadUnreadCount();
  }

  async markAllAsRead(): Promise<void> {
    await firstValueFrom(this.apiClient.patch('/v1/notifications/read-all', {}));
    this.itemsSubject.next(this.itemsSubject.value.map((item) => ({ ...item, read: true })));
    this.unreadCountSubject.next(0);
    await this.loadUnreadCount();
  }

  async delete(id: string): Promise<void> {
    await firstValueFrom(this.apiClient.delete(`/v1/notifications/${id}`));
    this.itemsSubject.next(this.itemsSubject.value.filter((item) => item.id !== id));
    this.recalculateUnreadCount();
    await this.loadUnreadCount();
  }

  unreadCount(): number {
    return this.unreadCountSubject.value;
  }

  async loadPreferences(): Promise<void> {
    const response = await firstValueFrom(this.apiClient.get<NotificationPreferencesPayload>('/v1/notifications/preferences'));
    this.preferencesSubject.next({
      ...this.preferencesSubject.value,
      ...(response.data?.preferences ?? {}),
    });
  }

  async savePreferences(nextPreferences: NotificationPreferences): Promise<void> {
    const response = await firstValueFrom(this.apiClient.patch<NotificationPreferencesPayload, { preferences: NotificationPreferences }>(
      '/v1/notifications/preferences',
      { preferences: nextPreferences },
    ));

    this.preferencesSubject.next({
      ...this.preferencesSubject.value,
      ...(response.data?.preferences ?? nextPreferences),
    });
  }

  private async loadList(): Promise<void> {
    const response = await firstValueFrom(this.apiClient.get<NotificationApiItem[]>('/v1/notifications', {
      params: {
        status: 'all',
        limit: 50,
      },
    }));

    const items = Array.isArray(response.data) ? response.data : [];
    this.itemsSubject.next(items.map((item, index) => this.normalizeItem(item, index)));
    this.recalculateUnreadCount();
  }

  private async loadUnreadCount(): Promise<void> {
    const response = await firstValueFrom(this.apiClient.get<NotificationUnreadCountPayload>('/v1/notifications/unread-count'));
    this.unreadCountSubject.next(Number(response.data?.count ?? 0));
  }

  private normalizeItem(item: NotificationApiItem, index: number): NotificationPreview {
    const type = item.type;
    const normalizedType: NotificationPreview['type'] = type === 'success' || type === 'warning' || type === 'error'
      ? type
      : 'info';

    return {
      id: String(item.id ?? `notification-${index}`),
      type: normalizedType,
      title: item.title ?? null,
      message: item.message ?? null,
      createdAt: item.created_at ?? null,
      read: Boolean(item.is_read),
    };
  }

  private recalculateUnreadCount(): void {
    this.unreadCountSubject.next(this.itemsSubject.value.filter((item) => !item.read).length);
  }

  private scheduleRefresh(): void {
    if (this.refreshTimer) {
      clearTimeout(this.refreshTimer);
    }

    this.refreshTimer = setTimeout(() => {
      void this.refresh();
    }, this.refreshDelayMs);
  }
}
