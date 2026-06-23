<template>
  <section class="notifications-page">
    <header class="notifications-page__header c-card">
      <div>
        <h2 class="notifications-page__title">Notifications</h2>
        <p class="notifications-page__subtitle">Track system messages and keep your inbox state synchronized.</p>
      </div>
      <div class="notifications-page__header-actions">
        <span class="notifications-page__badge">{{ unreadCount }} unread</span>
        <button
          type="button"
          class="notifications-page__action-btn"
          :disabled="isMutating || unreadCount === 0"
          @click="onMarkAllAsRead"
        >
          Mark all as read
        </button>
      </div>
    </header>

    <section class="c-card notifications-page__content">
      <div class="notifications-page__preferences">
        <h3>Preferences</h3>
        <div class="notifications-page__preferences-grid">
          <label v-for="item in preferenceControls" :key="item.key" class="notifications-page__toggle">
            <input
              type="checkbox"
              :checked="draftPreferences[item.key]"
              :disabled="isPreferencesSaving"
              @change="onTogglePreference(item.key, $event)"
            />
            <span>{{ item.label }}</span>
          </label>
        </div>
        <div class="notifications-page__preferences-actions">
          <button
            type="button"
            class="notifications-page__action-btn"
            :disabled="isPreferencesSaving || !hasPreferenceChanges"
            @click="onSavePreferences"
          >
            Save preferences
          </button>
          <small v-if="preferencesMessage" class="notifications-page__preferences-message">{{ preferencesMessage }}</small>
        </div>
      </div>

      <div class="notifications-page__toolbar">
        <label class="notifications-page__filter-label">
          Status
          <select v-model="statusFilter" class="notifications-page__filter-select" @change="onFilterChange">
            <option value="all">All</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
          </select>
        </label>
        <button type="button" class="notifications-page__refresh-btn" :disabled="isLoading || isRefreshing" @click="onRefresh">
          Refresh
        </button>
      </div>

      <div v-if="isLoading" class="notifications-page__state">
        <BaseLoader label="Loading notifications..." />
      </div>

      <BaseErrorState v-else-if="errorMessage" title="Failed to load notifications" :description="errorMessage">
        <button type="button" class="notifications-page__refresh-btn" @click="onRefresh">Retry</button>
      </BaseErrorState>

      <template v-else>
        <BaseEmptyState
          v-if="filteredNotifications.length === 0"
          title="No notifications"
          description="No notifications match the selected filter yet."
        />

        <ul v-else class="notifications-page__list">
          <li v-for="item in filteredNotifications" :key="item.id" class="notifications-page__item" :data-unread="!item.is_read">
            <div class="notifications-page__item-head">
              <span class="notifications-page__type">{{ item.type }}</span>
              <span class="notifications-page__timestamp">{{ formatDate(item.created_at) }}</span>
            </div>

            <h3 class="notifications-page__item-title">{{ item.title || 'System notification' }}</h3>
            <p class="notifications-page__item-message">{{ item.message || 'No message provided.' }}</p>

            <div class="notifications-page__item-actions">
              <span class="notifications-page__state-pill" :class="item.is_read ? 'is-read' : 'is-unread'">
                {{ item.is_read ? 'Read' : 'Unread' }}
              </span>
              <button
                v-if="!item.is_read"
                type="button"
                class="notifications-page__action-btn"
                :disabled="isMutating"
                @click="onMarkAsRead(item.id)"
              >
                Mark read
              </button>
              <button
                v-if="canDelete"
                type="button"
                class="notifications-page__delete-btn"
                :disabled="isMutating"
                @click="onDelete(item.id)"
              >
                Delete
              </button>
            </div>
          </li>
        </ul>
      </template>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import { useAuthStore } from '../../../stores/auth.store';
import { notificationsService } from '../services/notifications.service';
import type { NotificationItem, NotificationPreferences, NotificationStatusFilter } from '../notifications.types';

const authStore = useAuthStore();
const statusFilter = ref<NotificationStatusFilter>('all');
const isMutating = ref(false);
const isPreferencesSaving = ref(false);
const preferencesMessage = ref('');

const notifications = computed(() => notificationsService.notifications.value);
const isLoading = computed(() => notificationsService.isLoading.value);
const isRefreshing = computed(() => notificationsService.isRefreshing.value);
const errorMessage = computed(() => notificationsService.errorMessage.value);
const unreadCount = computed(() => notificationsService.unreadCount.value);
const canDelete = computed(() => authStore.hasPermission('notifications.delete'));
const preferences = computed(() => notificationsService.preferences.value);
const draftPreferences = ref<NotificationPreferences>({
  'system.enabled': true,
  'realtime.enabled': true,
  'email.enabled': true,
  'activity.enabled': true,
});

const preferenceControls: Array<{ key: keyof NotificationPreferences; label: string }> = [
  { key: 'system.enabled', label: 'System notifications' },
  { key: 'realtime.enabled', label: 'Realtime notifications' },
  { key: 'email.enabled', label: 'Email notifications' },
  { key: 'activity.enabled', label: 'Activity notifications' },
];

const hasPreferenceChanges = computed(() =>
  preferenceControls.some(({ key }) => draftPreferences.value[key] !== preferences.value[key])
);

const filteredNotifications = computed<NotificationItem[]>(() => {
  if (statusFilter.value === 'unread') {
    return notifications.value.filter((item) => !item.is_read);
  }

  if (statusFilter.value === 'read') {
    return notifications.value.filter((item) => item.is_read);
  }

  return notifications.value;
});

const formatDate = (value: string | null): string => {
  if (!value) {
    return 'just now';
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed);
};

const onRefresh = async (): Promise<void> => {
  await notificationsService.refresh();
};

const onFilterChange = async (): Promise<void> => {
  await notificationsService.loadNotifications({
    status: statusFilter.value,
    limit: 50,
  });
  await notificationsService.loadUnreadCount();
};

const onMarkAsRead = async (notificationId: string): Promise<void> => {
  isMutating.value = true;
  try {
    await notificationsService.markAsRead(notificationId);
  } finally {
    isMutating.value = false;
  }
};

const onMarkAllAsRead = async (): Promise<void> => {
  isMutating.value = true;
  try {
    await notificationsService.markAllAsRead();
  } finally {
    isMutating.value = false;
  }
};

const onDelete = async (notificationId: string): Promise<void> => {
  isMutating.value = true;
  try {
    await notificationsService.deleteNotification(notificationId);
  } finally {
    isMutating.value = false;
  }
};

const onTogglePreference = (key: keyof NotificationPreferences, event: Event): void => {
  const target = event.target as HTMLInputElement | null;
  if (!target) {
    return;
  }

  draftPreferences.value = {
    ...draftPreferences.value,
    [key]: target.checked,
  };
};

const onSavePreferences = async (): Promise<void> => {
  isPreferencesSaving.value = true;
  preferencesMessage.value = '';
  try {
    await notificationsService.savePreferences(draftPreferences.value);
    draftPreferences.value = { ...notificationsService.preferences.value };
    preferencesMessage.value = 'Preferences saved.';
  } catch (error) {
    preferencesMessage.value = (error as { message?: string })?.message ?? 'Failed to save preferences.';
  } finally {
    isPreferencesSaving.value = false;
  }
};

onMounted(async () => {
  const currentUserId = authStore.user?.id ? Number(authStore.user.id) : undefined;
  notificationsService.initRealtimeBridge(currentUserId);
  await notificationsService.loadPreferences();
  draftPreferences.value = { ...notificationsService.preferences.value };
  await notificationsService.loadNotifications({
    status: statusFilter.value,
    limit: 50,
  });
  await notificationsService.loadUnreadCount();
});

onUnmounted(() => {
  // Keep realtime bridge alive at layout level; page should not force-dispose it.
});
</script>

<style scoped>
.notifications-page{display:grid;gap:12px}
.notifications-page__header{margin-top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.notifications-page__title{margin:0;font-size:18px;color:#f8fafc}
.notifications-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.notifications-page__header-actions{display:flex;align-items:center;gap:8px}
.notifications-page__badge{border-radius:999px;border:1px solid rgba(245,158,11,.5);background:rgba(245,158,11,.16);color:#fcd34d;padding:4px 9px;font-size:11px}
.notifications-page__content{margin-top:0;display:grid;gap:10px}
.notifications-page__preferences{display:grid;gap:10px;padding:10px;border:1px solid rgba(71,85,105,.5);border-radius:10px;background:rgba(15,23,42,.45)}
.notifications-page__preferences h3{margin:0;color:#f8fafc;font-size:14px}
.notifications-page__preferences-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
.notifications-page__toggle{display:flex;align-items:center;gap:8px;color:#cbd5e1;font-size:12px}
.notifications-page__preferences-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.notifications-page__preferences-message{font-size:12px;color:#94a3b8}
.notifications-page__toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px}
.notifications-page__filter-label{display:grid;gap:4px;color:#cbd5e1;font-size:12px}
.notifications-page__filter-select{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px}
.notifications-page__state{padding:14px 0}
.notifications-page__list{display:grid;gap:10px;list-style:none;padding:0;margin:0}
.notifications-page__item{border:1px solid rgba(71,85,105,.5);border-radius:10px;background:rgba(15,23,42,.6);padding:10px;display:grid;gap:8px}
.notifications-page__item[data-unread='true']{border-color:rgba(245,158,11,.55);background:rgba(120,53,15,.15)}
.notifications-page__item-head{display:flex;justify-content:space-between;gap:10px}
.notifications-page__type{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#67e8f9}
.notifications-page__timestamp{font-size:11px;color:#94a3b8}
.notifications-page__item-title{margin:0;font-size:14px;color:#f8fafc}
.notifications-page__item-message{margin:0;font-size:13px;color:#cbd5e1}
.notifications-page__item-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.notifications-page__state-pill{font-size:11px;border-radius:999px;padding:2px 8px;border:1px solid rgba(71,85,105,.5)}
.notifications-page__state-pill.is-read{color:#94a3b8;background:rgba(71,85,105,.3)}
.notifications-page__state-pill.is-unread{color:#fcd34d;background:rgba(245,158,11,.16);border-color:rgba(245,158,11,.45)}
.notifications-page__action-btn,.notifications-page__refresh-btn,.notifications-page__delete-btn{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
.notifications-page__delete-btn{border-color:rgba(239,68,68,.45);color:#fca5a5;background:rgba(127,29,29,.2)}
.notifications-page__action-btn:disabled,.notifications-page__refresh-btn:disabled,.notifications-page__delete-btn:disabled{opacity:.55;cursor:not-allowed}
@media (max-width:760px){.notifications-page__header{flex-direction:column;align-items:stretch}.notifications-page__toolbar{flex-direction:column;align-items:stretch}}
</style>
