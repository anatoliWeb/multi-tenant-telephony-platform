import { computed, ref } from 'vue';

import { api } from '../../../services/api/client';
import { realtimeClient } from '../../../shared/services/realtime/realtime.client';
import type {
  NotificationItem,
  NotificationListQuery,
  NotificationPreferences,
  NotificationStatusFilter,
} from '../notifications.types';

interface NotificationListApiItem {
  id?: string;
  type?: string;
  title?: string | null;
  message?: string | null;
  is_read?: boolean;
  read_at?: string | null;
  created_at?: string | null;
}

interface NotificationListApiResponse {
  data?: NotificationListApiItem[];
}

interface NotificationUnreadCountResponse {
  count?: number;
}

interface NotificationPreferencesResponse {
  preferences?: NotificationPreferences;
}

const notifications = ref<NotificationItem[]>([]);
const unreadCount = ref(0);
const preferences = ref<NotificationPreferences>({
  'system.enabled': true,
  'realtime.enabled': true,
  'email.enabled': true,
  'activity.enabled': true,
});
const isLoading = ref(false);
const isRefreshing = ref(false);
const errorMessage = ref('');
const hasLoadedOnce = ref(false);
const lastSyncAt = ref<string | null>(null);

const normalizeNotification = (entry: NotificationListApiItem, index: number): NotificationItem => {
  return {
    id: String(entry.id ?? `notification-${index}`),
    type: String(entry.type ?? 'system'),
    title: entry.title ?? null,
    message: entry.message ?? null,
    is_read: Boolean(entry.is_read),
    read_at: entry.read_at ?? null,
    created_at: entry.created_at ?? null,
  };
};

let realtimeUnsubscribe: (() => void) | null = null;
let realtimeNotificationCreatedUnsubscribe: (() => void) | null = null;
let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
const REALTIME_REFRESH_DELAY_MS = 1200;

const scheduleRealtimeRefresh = (): void => {
  if (document.hidden) {
    return;
  }

  if (realtimeRefreshTimer) {
    clearTimeout(realtimeRefreshTimer);
  }

  realtimeRefreshTimer = setTimeout(() => {
    void notificationsService.refresh();
  }, REALTIME_REFRESH_DELAY_MS);
};

const recalculateUnreadCount = (): void => {
  unreadCount.value = notifications.value.filter((item) => !item.is_read).length;
};

export const notificationsService = {
  notifications: computed(() => notifications.value),
  unreadCount: computed(() => unreadCount.value),
  preferences: computed(() => preferences.value),
  isLoading: computed(() => isLoading.value),
  isRefreshing: computed(() => isRefreshing.value),
  errorMessage: computed(() => errorMessage.value),
  hasLoadedOnce: computed(() => hasLoadedOnce.value),
  lastSyncAt: computed(() => lastSyncAt.value),

  initRealtimeBridge(userId?: number): void {
    if (realtimeUnsubscribe) {
      if (userId && !realtimeNotificationCreatedUnsubscribe) {
        realtimeNotificationCreatedUnsubscribe = realtimeClient.onNotificationCreated(userId, () => {
          scheduleRealtimeRefresh();
        });
      }
      return;
    }

    realtimeUnsubscribe = realtimeClient.onSystemNotification(() => {
      scheduleRealtimeRefresh();
    });

    if (userId) {
      realtimeNotificationCreatedUnsubscribe = realtimeClient.onNotificationCreated(userId, () => {
        scheduleRealtimeRefresh();
      });
    }
  },

  disposeRealtimeBridge(): void {
    realtimeUnsubscribe?.();
    realtimeUnsubscribe = null;
    realtimeNotificationCreatedUnsubscribe?.();
    realtimeNotificationCreatedUnsubscribe = null;

    if (realtimeRefreshTimer) {
      clearTimeout(realtimeRefreshTimer);
      realtimeRefreshTimer = null;
    }
  },

  async loadNotifications(query: NotificationListQuery = {}): Promise<void> {
    const shouldUseLoader = !hasLoadedOnce.value;

    try {
      errorMessage.value = '';
      if (shouldUseLoader) {
        isLoading.value = true;
      } else {
        isRefreshing.value = true;
      }

      const payload = await api.get<NotificationListApiResponse>('/v1/notifications', {
        params: {
          status: query.status ?? 'all',
          limit: query.limit ?? 50,
        },
      });

      const rows = Array.isArray(payload.data?.data) ? payload.data.data : [];
      notifications.value = rows.map((entry, index) => normalizeNotification(entry, index));
      recalculateUnreadCount();
      hasLoadedOnce.value = true;
      lastSyncAt.value = new Date().toISOString();
    } catch (error) {
      errorMessage.value = (error as { message?: string })?.message ?? 'Failed to load notifications.';
      throw error;
    } finally {
      isLoading.value = false;
      isRefreshing.value = false;
    }
  },

  async loadUnreadCount(): Promise<void> {
    try {
      const payload = await api.get<NotificationUnreadCountResponse>('/v1/notifications/unread-count');
      unreadCount.value = Number(payload.data?.count ?? 0);
      lastSyncAt.value = new Date().toISOString();
    } catch {
      // Keep current count if lightweight endpoint fails.
    }
  },

  async loadPreferences(): Promise<void> {
    const payload = await api.get<NotificationPreferencesResponse>('/v1/notifications/preferences');
    preferences.value = {
      ...preferences.value,
      ...(payload.data?.preferences ?? {}),
    };
  },

  async savePreferences(nextPreferences: NotificationPreferences): Promise<void> {
    const payload = await api.patch<NotificationPreferencesResponse>('/v1/notifications/preferences', {
      preferences: nextPreferences,
    });

    preferences.value = {
      ...preferences.value,
      ...(payload.data?.preferences ?? nextPreferences),
    };
  },

  async refresh(): Promise<void> {
    try {
      await this.loadNotifications({ status: 'all', limit: 50 });
    } catch {
      // Preserve previous UI state on background refresh failures.
    } finally {
      await this.loadUnreadCount();
    }
  },

  async markAsRead(notificationId: string): Promise<void> {
    try {
      await api.patch(`/v1/notifications/${notificationId}/read`);
      const index = notifications.value.findIndex((item) => item.id === notificationId);
      if (index >= 0 && !notifications.value[index].is_read) {
        notifications.value[index] = {
          ...notifications.value[index],
          is_read: true,
          read_at: new Date().toISOString(),
        };
        recalculateUnreadCount();
      }
    } finally {
      await this.loadUnreadCount();
    }
  },

  async markAllAsRead(): Promise<void> {
    try {
      await api.patch('/v1/notifications/read-all');
      notifications.value = notifications.value.map((item) => ({
        ...item,
        is_read: true,
        read_at: item.read_at ?? new Date().toISOString(),
      }));
      unreadCount.value = 0;
    } finally {
      await this.loadUnreadCount();
    }
  },

  async deleteNotification(notificationId: string): Promise<void> {
    try {
      await api.delete(`/v1/notifications/${notificationId}`);
      notifications.value = notifications.value.filter((item) => item.id !== notificationId);
      recalculateUnreadCount();
    } finally {
      await this.loadUnreadCount();
    }
  },

  setStatusFilter(status: NotificationStatusFilter): NotificationStatusFilter {
    return status;
  },
};
