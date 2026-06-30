<template>
  <section :class="['admin-layout', { 'is-sidebar-collapsed': isSidebarCollapsed }]">
    <aside class="admin-sidebar">
      <header class="admin-sidebar__header">
        <router-link class="admin-brand" to="/dashboard">
          <span class="admin-brand__dot" />
          <span class="admin-brand__name">{{ t('common.admin') }}</span>
        </router-link>

        <button
          type="button"
          class="admin-sidebar__toggle"
          :aria-label="isSidebarCollapsed ? t('common.navigation.expandSidebar') : t('common.navigation.collapseSidebar')"
          @click="isSidebarCollapsed = !isSidebarCollapsed"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
      </header>

      <nav class="admin-sidebar__nav">
        <section class="admin-sidebar__section">
          <h2 class="admin-sidebar__heading">{{ t('common.overview') }}</h2>
          <router-link
            v-for="item in visibleOverviewLinks"
            :key="item.to"
            class="admin-sidebar__link"
            :to="item.to"
          >
            <component :is="item.icon" />
            <span class="admin-sidebar__label">{{ t(item.labelKey) }}</span>
          </router-link>
        </section>

        <section class="admin-sidebar__section">
          <h2 class="admin-sidebar__heading">{{ t('common.management') }}</h2>
          <router-link
            v-for="item in visibleManagementLinks"
            :key="item.to"
            class="admin-sidebar__link"
            :to="item.to"
          >
            <component :is="item.icon" />
            <span class="admin-sidebar__label">{{ t(item.labelKey) }}</span>
          </router-link>
        </section>

        <section class="admin-sidebar__section">
          <h2 class="admin-sidebar__heading">{{ t('common.navigation.api') }}</h2>
          <template v-for="item in visibleApiLinks" :key="item.to ?? item.href">
            <router-link
              v-if="item.to"
              class="admin-sidebar__link"
              :to="item.to"
            >
              <component :is="item.icon" />
              <span class="admin-sidebar__label">{{ t(item.labelKey) }}</span>
            </router-link>
            <a
              v-else-if="item.href"
              class="admin-sidebar__link"
              :href="item.href"
              target="_blank"
              rel="noopener noreferrer"
              data-testid="api-docs-sidebar-link"
            >
              <component :is="item.icon" />
              <span class="admin-sidebar__label">{{ t(item.labelKey) }}</span>
            </a>
          </template>
        </section>
      </nav>
    </aside>

    <main class="admin-shell-main">
      <header class="admin-topbar">
        <div class="topbar-shell">
          <div class="topbar-shell__left">
            <h1 class="page-title">{{ pageTitle }}</h1>
            <p class="page-subtitle">{{ t('common.admin') }} / {{ pageTitle }}</p>
          </div>

          <div class="topbar-shell__center">
            <BaseTopbarSearch :placeholder="t('common.searchPlaceholder')" />
          </div>

          <div class="topbar-shell__right">
            <select
              v-if="tenantOptions.length > 0"
              v-model="selectedTenantId"
              class="topbar-shell__tenant-select"
              :aria-label="t('common.tenantSupport.selectTenant')"
              data-testid="admin-tenant-select"
            >
              <option value="">{{ t('common.tenantSupport.selectTenant') }}</option>
              <option v-for="tenant in tenantOptions" :key="tenant.id" :value="tenant.id">
                {{ tenant.name }}
              </option>
            </select>

            <div class="topbar-shell__metrics" :aria-label="t('common.topbar.realtimeCounters')">
              <BaseRealtimeStatus
                v-for="metric in realtimeMetrics"
                :key="metric.key"
                :label="metric.label"
                :count="metric.count"
                :active="metric.active"
                :title="resolveMetricTitle(metric)"
              />
            </div>

            <div class="topbar-shell__status" :aria-label="t('common.topbar.systemStatusActions')">
              <div class="topbar-notification-btn">
                <BaseIconButton :title="t('common.topbar.notifications')" @click="openNotifications">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2z" /></svg>
                </BaseIconButton>
                <span v-if="notificationsUnreadCount > 0" class="topbar-notification-btn__badge">
                  {{ notificationsUnreadCount > 99 ? '99+' : notificationsUnreadCount }}
                </span>
              </div>
              <div class="topbar-notification-btn">
                <BaseIconButton :title="t('common.topbar.messages')">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm1.8 4.5 6.2 4.1 6.2-4.1" /></svg>
                </BaseIconButton>
                <span v-if="chatUnreadCount !== null && chatUnreadCount > 0" class="topbar-notification-btn__badge">
                  {{ chatUnreadCount > 99 ? '99+' : chatUnreadCount }}
                </span>
              </div>
              <BaseIconButton :title="t('common.topbar.realtimeStatus')" :active="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 1 0 8 8h-2a6 6 0 1 1-6-6V4zm1 0v7h7A7 7 0 0 0 13 4z" /></svg>
              </BaseIconButton>
              <BaseIconButton :title="t('common.topbar.queueStatus')" :active="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v3H4V6zm0 5h16v3H4v-3zm0 5h10v3H4v-3z" /></svg>
              </BaseIconButton>
            </div>

            <BaseLanguageSwitcher v-model="selectedLocale" :locales="enabledLocales" />
            <BaseUserDropdown :name="userName" @logout="handleLogout" />
          </div>
        </div>
      </header>

      <section class="admin-shell-content">
        <router-view />
      </section>
    </main>
  </section>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, onUnmounted, ref, type Component } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import BaseIconButton from '../shared/components/ui/BaseIconButton.vue';
import BaseLanguageSwitcher from '../shared/components/ui/BaseLanguageSwitcher.vue';
import BaseRealtimeStatus from '../shared/components/ui/BaseRealtimeStatus.vue';
import BaseTopbarSearch from '../shared/components/ui/BaseTopbarSearch.vue';
import BaseUserDropdown from '../shared/components/ui/BaseUserDropdown.vue';
import { getEnabledLocales } from '../shared/i18n';
import type { LocaleCode } from '../shared/i18n/config';
import { realtimeClient } from '../shared/services/realtime/realtime.client';
import { REALTIME_CHANNELS } from '../shared/services/realtime/realtime.channels';
import type { RealtimeStatusMetric } from '../shared/services/realtime/realtime.types';
import { notificationsService } from '../modules/notifications/services/notifications.service';
import { chatAdminService } from '../modules/chat-admin/services/chat-admin.service';
import { useAuthStore } from '../stores/auth.store';
import { useTenantStore } from '../stores/tenant.store';
import { useTranslationStore } from '../stores/translation.store';
import type { TenantMembershipSummary, TenantSummary } from '../types/tenant.types';

const route = useRoute();
const router = useRouter();
const { t } = useI18n({ useScope: 'global' });
const translationStore = useTranslationStore();
const authStore = useAuthStore();
const tenantStore = useTenantStore();
const { memberships, activeTenantId } = storeToRefs(tenantStore);

const isSidebarCollapsed = ref(false);
const enabledLocales = getEnabledLocales();
const userName = computed(() => authStore.user?.name ?? 'Admin User');
const realtimeMetrics = ref<RealtimeStatusMetric[]>([]);
const notificationsUnreadCount = computed(() => notificationsService.unreadCount.value);
const chatUnreadCount = ref<number | null>(null);
let unsubscribeStatus: (() => void) | null = null;
let unsubscribeNotifications: (() => void) | null = null;
let unsubscribeOnlinePresence: (() => void) | null = null;
let unsubscribeDashboardPresence: (() => void) | null = null;

type NavItem = {
  to?: string
  href?: string
  labelKey: string
  icon: Component
  permission?: string
  permissions?: string[]
}

const selectedLocale = computed<LocaleCode>({
  get: () => translationStore.locale as LocaleCode,
  set: (value) => {
    void translationStore.switchLocale(value);
  },
});

const routeTitleMap: Record<string, string> = {
  dashboard: 'common.dashboard',
  'dashboard-page': 'common.dashboard',
  users: 'common.users',
  roles: 'common.roles',
  permissions: 'common.permissions',
  tokens: 'common.tokens',
  activity: 'common.activity',
  settings: 'common.settings',
  profile: 'common.profile',
  billing: 'common.billing',
  translations: 'common.translations',
  notifications: 'common.notifications',
  'chat-admin-monitoring': 'common.chat',
  tenants: 'common.tenants',
  contacts: 'common.contacts',
  extensions: 'common.extensions',
  'ring-groups': 'common.ringGroups',
  'call-queues': 'common.callQueues',
  ivr: 'common.ivr',
  'phone-numbers': 'common.phoneNumbers',
  'call-logs': 'common.callLogs',
};

const IconGrid = defineIcon('M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z');
const IconUsers = defineIcon('M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM8 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3zM16 13c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4zM8 14c-.29 0-.62.02-.97.05C5.31 14.23 2 15.1 2 17v3h4v-3c0-1.1.58-2.07 1.55-2.78A8.4 8.4 0 0 1 8 14z');
const IconShield = defineIcon('M12 2 4 5v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V5l-8-3z');
const IconKey = defineIcon('M7 14a5 5 0 1 1 4.9 4H10v3H7v-3H4v-3h3.1A5 5 0 0 1 7 14zm10-2h2v2h-2v2h-2v-2h-2v-2h2v-2h2v2z');
const IconToken = defineIcon('M12 2 3 7v10l9 5 9-5V7l-9-5zm0 2.2 6.8 3.8L12 11.8 5.2 8 12 4.2zm-7 5.5 6 3.4v7.4l-6-3.3V9.7zm8 10.8v-7.4l6-3.4v7.5l-6 3.3z');
const IconDocs = defineIcon('M6 2h8l4 4v16H6V2zm8 1.5V7h3.5L14 3.5zM8 10h8v1.5H8V10zm0 3h8v1.5H8V13zm0 3h5v1.5H8V16z');
const IconTranslate = defineIcon('M5 4h10v2H9.6l-.1.4c-.4 1.4-1 2.8-1.8 4a18 18 0 0 0 2.6 2.5l-1.4 1.4a20 20 0 0 1-2.3-2.2 14 14 0 0 1-3.2 2.4L2.5 13A11.4 11.4 0 0 0 5.2 11a13 13 0 0 1-1.8-3.8H1V5h4V4zm2.3 3.2h-2a10.2 10.2 0 0 0 1.3 2.4 9.6 9.6 0 0 0 .7-2.4zM17 10l5 12h-2.2l-1.2-3h-5.2l-1.2 3H10l5-12h2zm-2.9 7h3.8L16 12.2 14.1 17z');
const IconBell = defineIcon('M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm6-6v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2z');
const IconChat = defineIcon('M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z');
const IconBuilding = defineIcon('M4 20h16V8l-4-4H4v16zm4-2H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V8h2v2zm4 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V8h2v2zm4 8h-2v-6h2v6z');
const IconBook = defineIcon('M5 5.5A2.5 2.5 0 0 1 7.5 3H20v16H7.5A2.5 2.5 0 0 0 5 21.5V5.5zm2.5-.5A1.5 1.5 0 0 0 6 6.5v11A3.5 3.5 0 0 1 7.5 17H18V5H7.5z');
const IconPhone = defineIcon('M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11 11 0 0 0 3.46.55 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.3 21 3 13.7 3 4a1 1 0 0 1 1-1h3.49a1 1 0 0 1 1 1c0 1.2.19 2.36.55 3.46a1 1 0 0 1-.24 1l-2.2 2.34z');
const IconPhoneNumbers = defineIcon('M6 4h12v16H6V4zm2 2v3h8V6H8zm0 5v2h8v-2H8zm0 4v2h5v-2H8z');
const IconRingGroup = defineIcon('M12 3a9 9 0 1 0 9 9h-2a7 7 0 1 1-7-7V3zm0 4a5 5 0 1 0 5 5h-2a3 3 0 1 1-3-3V7zm0 4h.01');
const IconCallQueue = defineIcon('M4 6h16v3H4V6zm0 5h16v3H4v-3zm0 5h10v3H4v-3z');
const IconWave = defineIcon('M4 12a8 8 0 0 1 16 0h-2a6 6 0 1 0-12 0H4zm4 0a4 4 0 0 1 8 0h-2a2 2 0 1 0-4 0H8zm4 0h.01');

const overviewLinks: NavItem[] = [
  {
    to: '/dashboard',
    labelKey: 'common.dashboard',
    icon: IconGrid,
  },
];

const managementLinks: NavItem[] = [
  {
    to: '/users',
    labelKey: 'common.users',
    icon: IconUsers,
    permission: 'users.view',
  },
  {
    to: '/roles',
    labelKey: 'common.roles',
    icon: IconShield,
    permission: 'roles.view',
  },
  {
    to: '/permissions',
    labelKey: 'common.permissions',
    icon: IconKey,
    permission: 'permissions.view',
  },
  {
    to: '/translations',
    labelKey: 'common.translations',
    icon: IconTranslate,
    permission: 'translations.view',
  },
  {
    to: '/notifications',
    labelKey: 'common.notifications',
    icon: IconBell,
    permission: 'notifications.view',
  },
  {
    to: '/chat',
    labelKey: 'common.chat',
    icon: IconChat,
    permissions: ['chat.admin.view', 'chat.admin.view_metadata'],
  },
  {
    to: '/tenants',
    labelKey: 'common.tenants',
    icon: IconBuilding,
    permission: 'tenants.view',
  },
  {
    to: '/contacts',
    labelKey: 'common.contacts',
    icon: IconBook,
    permission: 'contacts.view',
  },
  {
    to: '/extensions',
    labelKey: 'common.extensions',
    icon: IconPhone,
    permission: 'extensions.view',
  },
  {
    to: '/ring-groups',
    labelKey: 'common.ringGroups',
    icon: IconRingGroup,
    permission: 'ring_groups.view',
  },
  {
    to: '/call-queues',
    labelKey: 'common.callQueues',
    icon: IconCallQueue,
    permission: 'call_queues.view',
  },
  {
    to: '/ivr',
    labelKey: 'common.ivr',
    icon: IconCallQueue,
    permission: 'ivr.view',
  },
  {
    to: '/phone-numbers',
    labelKey: 'common.phoneNumbers',
    icon: IconPhoneNumbers,
    permission: 'phone_numbers.view',
  },
  {
    to: '/call-logs',
    labelKey: 'common.callLogs',
    icon: IconWave,
    permission: 'call_logs.view',
  },
];

const apiLinks: NavItem[] = [
  {
    to: '/tokens',
    labelKey: 'common.tokens',
    icon: IconToken,
    permission: 'tokens.view',
  },
  {
    href: '/docs/api/portal',
    labelKey: 'common.apiDocs.sidebarLabel',
    icon: IconDocs,
    permission: 'api.docs.view',
  },
];

const canAccessNavItem = (item: NavItem): boolean => {
  if (item.permission) {
    return authStore.hasPlatformPermission(item.permission);
  }

  if (item.permissions && item.permissions.length > 0) {
    return authStore.hasAnyPlatformPermission(item.permissions);
  }

  return true;
};

const visibleOverviewLinks = computed(() => overviewLinks.filter(canAccessNavItem));
const visibleManagementLinks = computed(() => managementLinks.filter(canAccessNavItem));
const visibleApiLinks = computed(() => apiLinks.filter(canAccessNavItem));
const tenantOptions = computed(() => memberships.value.map((item) => {
  if (isTenantMembership(item)) {
    return item.tenant;
  }

  return item;
}).filter((item): item is TenantSummary => Boolean(item)));
const selectedTenantId = computed({
  get: () => activeTenantId.value ?? '',
  set: (value: string) => {
    if (!value) {
      tenantStore.clearSelection();
      return;
    }

    void tenantStore.switchTenant(value);
  },
});

const pageTitle = computed(() => {
  const routeName = String(route.name ?? 'dashboard');
  const key = routeTitleMap[routeName];
  return key ? t(key) : ((route.meta.title as string | undefined) ?? t('common.admin'));
});

const resolveMetricTitle = (metric: RealtimeStatusMetric): string => {
  switch (metric.key) {
    case 'backend_online':
      return 'WS: WebSocket connection state';
    case 'frontend_online':
      return 'EV: Realtime events received';
    case 'presence_online':
      return 'ON: Unique online users across joined presence channels';
    case 'presence_dashboard':
      return 'PG: Joined presence groups/channels';
    default:
      return metric.label;
  }
};

onMounted(async () => {
  const hasSession = await authStore.hydrateSession();
  if (!hasSession) {
    await router.replace('/login');
    return;
  }

  realtimeClient.connect();
  const currentUserId = authStore.user?.id ? Number(authStore.user.id) : undefined;
  notificationsService.initRealtimeBridge(currentUserId);
  void notificationsService.loadUnreadCount();
  await loadChatUnreadCount();
  realtimeMetrics.value = realtimeClient.getMetrics();
  unsubscribeStatus = realtimeClient.onStatusChange((state) => {
    realtimeMetrics.value = realtimeClient.getMetrics();
  });
  unsubscribeNotifications = realtimeClient.onSystemNotification((payload) => {
    realtimeMetrics.value = realtimeClient.getMetrics();
    void notificationsService.loadUnreadCount();
    void loadChatUnreadCount();
  });
  unsubscribeOnlinePresence = realtimeClient.joinPresence(REALTIME_CHANNELS.presenceOnline, {
    here: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    joining: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    leaving: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    error: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
  });
  unsubscribeDashboardPresence = realtimeClient.joinPresence(REALTIME_CHANNELS.presenceDashboard, {
    here: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    joining: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    leaving: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
    error: () => {
      realtimeMetrics.value = realtimeClient.getMetrics();
    },
  });
});

onUnmounted(() => {
  unsubscribeStatus?.();
  unsubscribeStatus = null;
  unsubscribeNotifications?.();
  unsubscribeNotifications = null;
  unsubscribeOnlinePresence?.();
  unsubscribeOnlinePresence = null;
  unsubscribeDashboardPresence?.();
  unsubscribeDashboardPresence = null;
  realtimeClient.disconnect();
});

const handleLogout = async (): Promise<void> => {
  await authStore.logout();
};

const openNotifications = async (): Promise<void> => {
  if (!authStore.hasPlatformPermission('notifications.view')) {
    return;
  }

  await router.push('/notifications');
};

const loadChatUnreadCount = async (): Promise<void> => {
  if (!authStore.hasAnyPlatformPermission(['chat.view', 'chat.conversations.view', 'chat.admin.view'])) {
    chatUnreadCount.value = null;
    return;
  }

  try {
    chatUnreadCount.value = await chatAdminService.getUnreadConversationsCount();
  } catch {
    // Keep null to indicate unavailable count instead of masking auth/runtime errors as zero.
    chatUnreadCount.value = null;
  }
};

function defineIcon(path: string) {
  return defineComponent({
    setup() {
      return () => h('svg', { viewBox: '0 0 24 24' }, [h('path', { d: path })]);
    },
  });
}

function isTenantMembership(item: TenantMembershipSummary | TenantSummary): item is TenantMembershipSummary {
  return Object.prototype.hasOwnProperty.call(item, 'tenant');
}
</script>

<style scoped>
svg {
  width: 16px;
  height: 16px;
  fill: currentColor;
}

.admin-shell-main {
  margin-left: var(--sidebar-width);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left 0.2s ease;
}

.admin-topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  border-bottom: 1px solid rgba(71, 85, 105, 0.45);
  background: rgba(15, 23, 42, 0.94);
  backdrop-filter: blur(10px);
}

.topbar-shell {
  min-height: 64px;
  padding: 12px 16px;
  display: grid;
  grid-template-columns: minmax(190px, 1fr) minmax(240px, 1.3fr) auto;
  align-items: center;
  gap: 12px;
}

.topbar-shell__left {
  min-width: 0;
}

.topbar-shell__center {
  min-width: 0;
}

.topbar-shell__right {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}

.topbar-shell__metrics,
.topbar-shell__status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.topbar-shell__tenant-select {
  min-width: 180px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.75);
  color: #e2e8f0;
  padding: 8px 10px;
}

.topbar-notification-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.topbar-notification-btn__badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 1px solid rgba(15, 23, 42, 0.9);
  background: #f97316;
  color: #fff7ed;
  font-size: 10px;
  line-height: 14px;
  padding: 0 4px;
  text-align: center;
}

.admin-shell-content {
  flex: 1;
  padding: 16px;
}

@media (max-width: 1180px) {
  .topbar-shell {
    grid-template-columns: minmax(180px, 1fr) auto;
  }

  .topbar-shell__center {
    grid-column: 1 / -1;
    grid-row: 2;
  }
}

@media (max-width: 860px) {
  .topbar-shell {
    grid-template-columns: 1fr;
  }

  .topbar-shell__right {
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .admin-shell-content {
    padding: 12px;
  }
}
</style>
