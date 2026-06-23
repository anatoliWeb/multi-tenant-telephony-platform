<template>
  <section class="dashboard-page">
    <div v-if="isLoading" class="c-card dashboard-placeholder">
      <h2 class="dashboard-widget__title">{{ t('common.loading') }}</h2>
      <p class="dashboard-widget__subtitle">{{ t('common.dashboardPage.loadingAnalytics') }}</p>
    </div>

    <div v-else-if="loadError" class="c-card dashboard-placeholder">
      <h2 class="dashboard-widget__title">{{ t('common.dashboardPage.unavailableTitle') }}</h2>
      <p class="dashboard-widget__subtitle">{{ loadError }}</p>
    </div>

    <template v-else>
      <div v-if="isRefreshing" class="dashboard-refresh-indicator">{{ t('common.loading') }}...</div>
      <section class="dashboard-stats">
        <BaseStatCard
          v-for="item in statCards"
          :key="item.key"
          :title="item.title"
          :value="item.value"
          :subtitle="item.subtitle"
          :trend="item.trend"
          :trend-direction="item.trendDirection"
          :meta="item.meta"
        />
      </section>

      <section class="dashboard-grid">
        <article class="c-card dashboard-widget dashboard-widget--chart-large">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.usersByRole') }}</h2>
            <span class="dashboard-widget__tag">{{ t('common.dashboardPage.analyticsTag') }}</span>
          </header>
          <div class="dashboard-widget__chart">
            <Doughnut :data="rolesChartData" :options="rolesChartOptions" />
          </div>
        </article>

        <article class="c-card dashboard-widget dashboard-widget--activity">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.recentActivity') }}</h2>
            <span class="dashboard-widget__tag">{{ t('common.dashboardPage.liveFeedReadyTag') }}</span>
          </header>
          <div class="activity-list">
            <div v-if="recentActivity.length === 0" class="activity-item">
              <div class="activity-avatar">-</div>
              <div class="activity-content">
                <div class="activity-title">{{ t('common.dashboardPage.noActivityYet') }}</div>
                <div class="activity-time">{{ t('common.dashboardPage.waitingForUpdates') }}</div>
              </div>
            </div>

            <div v-for="(activity, index) in recentActivity" :key="index" class="activity-item">
              <div class="activity-avatar">{{ activityInitial(activity) }}</div>
              <div class="activity-content">
                <div class="activity-title">{{ activityTitle(activity) }}</div>
                <div class="activity-time">{{ activityTime(activity) }}</div>
              </div>
              <span class="activity-kind">{{ t('common.dashboardPage.eventKind') }}</span>
            </div>
          </div>
        </article>

        <article class="c-card dashboard-widget">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.apiTokenUsage') }}</h2>
            <span class="dashboard-widget__tag">{{ t('common.dashboardPage.mockDataTag') }}</span>
          </header>
          <div class="dashboard-widget__chart dashboard-widget__chart--bar">
            <Bar :data="tokenChartData" :options="barChartOptions" />
          </div>
        </article>

        <article class="c-card dashboard-widget">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.systemActivity') }}</h2>
            <span class="dashboard-widget__tag">{{ t('common.dashboardPage.operationalTag') }}</span>
          </header>
          <div class="dashboard-widget__chart dashboard-widget__chart--bar">
            <Line :data="activityChartData" :options="lineChartOptions" />
          </div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid--bottom">
        <article class="c-card dashboard-widget">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.infrastructureStatus') }}</h2>
          </header>
          <div class="status-grid">
            <div v-for="status in systemStatus" :key="status.name" class="status-item">
              <span class="status-dot" :class="{ 'is-online': status.online }" />
              <span class="status-name">{{ status.name }}</span>
              <span class="status-value">{{ status.label }}</span>
            </div>
          </div>
        </article>

        <article class="c-card dashboard-widget">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.runtimeContext') }}</h2>
          </header>
          <div class="runtime-list">
            <div class="runtime-item"><span>{{ t('common.route') }}</span><strong>{{ route.fullPath }}</strong></div>
            <div class="runtime-item"><span>{{ t('common.locale') }}</span><strong>{{ locale.toUpperCase() }}</strong></div>
            <div class="runtime-item"><span>{{ t('common.environment') }}</span><strong>{{ mode }}</strong></div>
            <div class="runtime-item"><span>{{ t('common.apiEndpoint') }}</span><strong>{{ apiBase }}</strong></div>
            <div class="runtime-item"><span>{{ t('common.timestamp') }}</span><strong>{{ renderedAt }}</strong></div>
          </div>
        </article>

        <article v-if="canViewApiDocs" class="c-card dashboard-widget" data-testid="api-docs-card">
          <header class="dashboard-widget__header">
            <h2 class="dashboard-widget__title">{{ t('common.apiDocs.dashboardShortcutTitle') }}</h2>
            <span class="dashboard-widget__tag">OpenAPI</span>
          </header>
          <p class="dashboard-widget__subtitle">
            {{ t('common.apiDocs.dashboardShortcutDescription') }}
          </p>
          <div class="dashboard-actions">
            <a
              href="/docs/api/portal"
              class="dashboard-action-link"
              data-testid="api-docs-link"
              target="_blank"
              rel="noopener noreferrer"
            >
              {{ t('common.apiDocs.openDocs') }}
            </a>
          </div>
        </article>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
  type ChartData,
  type ChartOptions,
} from 'chart.js';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

import { api } from '../../../services/api/client';
import { useAuthStore } from '../../../stores/auth.store';
import { cacheStore, useCachedRequest } from '../../../shared/cache';
import BaseStatCard from '../../../shared/components/dashboard/BaseStatCard.vue';
import { realtimeClient } from '../../../shared/services/realtime/realtime.client';

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LineElement, LinearScale, PointElement, Tooltip);

interface ActivityItem {
  user?: { email?: string; name?: string } | null;
  description?: string;
  action?: string;
  created_at?: string;
}

interface StatsData {
  users: number;
  admins: number;
  managers: number;
  tokens: number;
  users_with_direct_permissions: number;
  recent_activity: ActivityItem[];
}

interface MetaData {
  current_user: { id: number; name: string; email: string; roles: Array<{ id: number; name: string }> } | null;
  current_user_permissions: string[];
}

const route = useRoute();
const { t, locale } = useI18n({ useScope: 'global' });
const authStore = useAuthStore();

const isLoading = ref(true);
const isRefreshing = ref(false);
const loadError = ref('');
const stats = ref<StatsData | null>(null);
const meta = ref<MetaData | null>(null);

const mode = import.meta.env.MODE;
const DASHBOARD_STATS_CACHE_KEY = 'dashboard.stats';
const DASHBOARD_META_BOOTSTRAP_CACHE_KEY = 'dashboard.meta.bootstrap';
const apiBase = import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || '/api';
const renderedAt = new Date().toISOString();
const REALTIME_REFRESH_DEBOUNCE_MS = 1500;
let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let unsubscribeRealtimeNotifications: (() => void) | null = null;

const statCards = computed(() => [
  {
    key: 'users',
    title: t('common.users'),
    subtitle: 'Active accounts in platform',
    value: stats.value?.users ?? 0,
    trend: '+12% this month',
    trendDirection: 'up' as const,
    meta: 'Includes admins and managers',
  },
  {
    key: 'admins',
    title: 'Admins',
    subtitle: 'Core system operators',
    value: stats.value?.admins ?? 0,
    trend: '+1 new',
    trendDirection: 'up' as const,
    meta: 'High-privilege accounts',
  },
  {
    key: 'managers',
    title: 'Managers',
    subtitle: 'Delegated management users',
    value: stats.value?.managers ?? 0,
    trend: 'stable',
    trendDirection: 'neutral' as const,
    meta: 'Role-based operations',
  },
  {
    key: 'tokens',
    title: t('common.tokens'),
    subtitle: 'Issued integration keys',
    value: stats.value?.tokens ?? 0,
    trend: '+8.3% this week',
    trendDirection: 'up' as const,
    meta: 'Client API connectivity',
  },
  {
    key: 'direct_permissions',
    title: t('common.permissions'),
    subtitle: 'Non-role permission assignments',
    value: stats.value?.users_with_direct_permissions ?? 0,
    trend: '-2 optimized',
    trendDirection: 'down' as const,
    meta: 'Security policy cleanup',
  },
]);

const recentActivity = computed(() => stats.value?.recent_activity ?? []);
const rolesCount = computed(() => meta.value?.current_user?.roles?.length ?? 0);
const permissionsCount = computed(() => meta.value?.current_user_permissions?.length ?? 0);
const currentUserPermissionsCount = computed(() => meta.value?.current_user_permissions?.length ?? 0);
const canViewApiDocs = computed(() => authStore.hasPermission('api.docs.view'));

void rolesCount;
void permissionsCount;
void currentUserPermissionsCount;

const chartTextColor = '#cbd5e1';
const chartGridColor = 'rgba(148, 163, 184, 0.18)';

const rolesChartData = computed<ChartData<'doughnut'>>(() => ({
  labels: ['Admins', 'Managers', 'Other Users'],
  datasets: [
    {
      data: [
        stats.value?.admins ?? 0,
        stats.value?.managers ?? 0,
        Math.max((stats.value?.users ?? 0) - (stats.value?.admins ?? 0) - (stats.value?.managers ?? 0), 0),
      ],
      backgroundColor: ['#38bdf8', '#818cf8', '#34d399'],
      borderColor: '#0f172a',
      borderWidth: 2,
    },
  ],
}));

const tokenChartData = computed<ChartData<'bar'>>(() => ({
  labels: ['Read', 'Write', 'Admin', 'Webhook'],
  datasets: [
    {
      label: 'Token usage',
      data: [12, 8, 4, Math.max((stats.value?.tokens ?? 0) - 24, 1)],
      backgroundColor: 'rgba(56, 189, 248, 0.5)',
      borderColor: '#38bdf8',
      borderRadius: 6,
      borderWidth: 1,
    },
  ],
}));

const activityChartData = computed<ChartData<'line'>>(() => ({
  labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  datasets: [
    {
      label: 'Queue jobs',
      data: [22, 31, 28, 35, 42, 37, 40],
      borderColor: '#22d3ee',
      backgroundColor: 'rgba(34, 211, 238, 0.18)',
      tension: 0.35,
      fill: true,
      pointRadius: 2,
      pointHoverRadius: 4,
    },
    {
      label: 'Realtime connections',
      data: [8, 12, 11, 14, 18, 17, 20],
      borderColor: '#a78bfa',
      backgroundColor: 'rgba(167, 139, 250, 0.12)',
      tension: 0.35,
      fill: true,
      pointRadius: 2,
      pointHoverRadius: 4,
    },
  ],
}));

const baseChartPlugins = {
  legend: {
    labels: {
      color: chartTextColor,
      boxWidth: 10,
      boxHeight: 10,
      useBorderRadius: true,
      borderRadius: 3,
      font: {
        size: 11,
      },
    },
  },
};

const rolesChartOptions: ChartOptions<'doughnut'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: baseChartPlugins,
};

const barChartOptions: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      ticks: { color: chartTextColor },
      grid: { color: chartGridColor },
    },
    y: {
      ticks: { color: chartTextColor },
      grid: { color: chartGridColor },
    },
  },
  plugins: baseChartPlugins,
};

const lineChartOptions: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      ticks: { color: chartTextColor },
      grid: { color: chartGridColor },
    },
    y: {
      ticks: { color: chartTextColor },
      grid: { color: chartGridColor },
    },
  },
  plugins: baseChartPlugins,
};

const systemStatus = computed(() => [
  { name: t('common.dashboardPage.statusApi'), label: t('common.dashboardPage.statusOnline'), online: true },
  { name: t('common.dashboardPage.statusQueue'), label: t('common.dashboardPage.statusRunning'), online: true },
  { name: t('common.dashboardPage.statusRealtime'), label: t('common.dashboardPage.statusReady'), online: true },
  { name: t('common.dashboardPage.statusRedis'), label: t('common.dashboardPage.statusConnected'), online: true },
  { name: t('common.dashboardPage.statusMySql'), label: t('common.dashboardPage.statusConnected'), online: true },
]);

const activityInitial = (activity: ActivityItem): string => {
  const base = activity.user?.name || activity.user?.email || 'U';
  return base.charAt(0).toUpperCase();
};

const activityTitle = (activity: ActivityItem): string => {
  const actor = activity.user?.email || activity.user?.name;
  const action = activity.description || activity.action || 'Updated';
  return actor ? `${actor} ${action}` : action;
};

const activityTime = (activity: ActivityItem): string => {
  return activity.created_at ?? t('common.dashboardPage.justNow');
};

const scheduleRealtimeRefresh = (): void => {
  if (document.hidden) {
    return;
  }

  if (realtimeRefreshTimer) {
    clearTimeout(realtimeRefreshTimer);
  }

  realtimeRefreshTimer = setTimeout(() => {
    void loadDashboard(true);
  }, REALTIME_REFRESH_DEBOUNCE_MS);
};

const loadDashboard = async (force = false): Promise<void> => {
  try {
    const hasCache = cacheStore.has(DASHBOARD_STATS_CACHE_KEY) && cacheStore.has(DASHBOARD_META_BOOTSTRAP_CACHE_KEY);
    if (!hasCache) {
      isLoading.value = true;
    }
    isRefreshing.value = false;
    loadError.value = '';

    const [statsResult, metaResult] = await Promise.all([
      useCachedRequest({
        key: DASHBOARD_STATS_CACHE_KEY,
        ttl: 60_000,
        force,
        request: async () => {
          const response = await api.get<StatsData>('/v1/stats');
          return response.data ?? null;
        },
        onBackgroundUpdate: (freshData) => {
          stats.value = freshData;
        },
      }),
      useCachedRequest({
        key: DASHBOARD_META_BOOTSTRAP_CACHE_KEY,
        ttl: 120_000,
        force,
        request: async () => {
          const response = await api.get<MetaData>('/v1/meta/bootstrap');
          return response.data ?? null;
        },
        onBackgroundUpdate: (freshData) => {
          meta.value = freshData;
        },
      }),
    ]);

    stats.value = statsResult.data;
    meta.value = metaResult.data;
    isRefreshing.value = statsResult.revalidating || metaResult.revalidating;
  } catch (error) {
    const message = (error as { message?: string })?.message ?? 'Failed to load dashboard data';
    loadError.value = message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  void loadDashboard();
  unsubscribeRealtimeNotifications = realtimeClient.onSystemNotification(() => {
    scheduleRealtimeRefresh();
  });
});

onUnmounted(() => {
  unsubscribeRealtimeNotifications?.();
  unsubscribeRealtimeNotifications = null;

  if (realtimeRefreshTimer) {
    clearTimeout(realtimeRefreshTimer);
    realtimeRefreshTimer = null;
  }
});
</script>

<style scoped>
.dashboard-page {
  display: grid;
  gap: 16px;
}

.dashboard-stats {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 12px;
}

.dashboard-refresh-indicator {
  color: #94a3b8;
  font-size: 12px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 12px;
}

.dashboard-grid--bottom {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.dashboard-widget {
  margin-top: 0;
  display: grid;
  gap: 12px;
}

.dashboard-widget__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.dashboard-widget__title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #f8fafc;
}

.dashboard-widget__subtitle {
  margin: 4px 0 0;
  color: #94a3b8;
}

.dashboard-widget__tag {
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 999px;
  padding: 3px 8px;
  color: #cbd5e1;
  font-size: 11px;
}

.dashboard-widget__chart {
  min-height: 230px;
  position: relative;
}

.dashboard-widget__chart--bar {
  min-height: 210px;
}

.dashboard-widget--activity {
  grid-row: span 2;
}

.activity-list {
  display: grid;
  gap: 10px;
}

.activity-item {
  border: 1px solid rgba(71, 85, 105, 0.55);
  background: rgba(15, 23, 42, 0.55);
  border-radius: 10px;
  padding: 10px;
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: center;
  gap: 10px;
}

.activity-avatar {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: rgba(56, 189, 248, 0.2);
  color: #67e8f9;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
}

.activity-title {
  color: #e2e8f0;
  font-size: 12px;
  margin-bottom: 2px;
}

.activity-time {
  color: #94a3b8;
  font-size: 11px;
}

.activity-kind {
  border-radius: 999px;
  padding: 2px 7px;
  font-size: 10px;
  text-transform: uppercase;
  color: #cbd5e1;
  border: 1px solid rgba(148, 163, 184, 0.35);
}

.status-grid {
  display: grid;
  gap: 8px;
}

.status-item {
  border: 1px solid rgba(71, 85, 105, 0.45);
  background: rgba(15, 23, 42, 0.55);
  border-radius: 8px;
  padding: 8px 10px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 8px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #ef4444;
}

.status-dot.is-online {
  background: #10b981;
  box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
}

.status-name {
  color: #cbd5e1;
  font-size: 12px;
}

.status-value {
  color: #f8fafc;
  font-size: 12px;
  font-weight: 600;
}

.runtime-list {
  display: grid;
  gap: 8px;
}

.runtime-item {
  border: 1px solid rgba(71, 85, 105, 0.45);
  background: rgba(15, 23, 42, 0.5);
  border-radius: 8px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.runtime-item span {
  color: #94a3b8;
  font-size: 12px;
}

.runtime-item strong {
  color: #f1f5f9;
  font-size: 12px;
}

.dashboard-actions {
  display: flex;
  align-items: center;
}

.dashboard-action-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(56, 189, 248, 0.45);
  border-radius: 8px;
  padding: 8px 12px;
  color: #67e8f9;
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.dashboard-action-link:hover {
  background: rgba(56, 189, 248, 0.12);
  border-color: rgba(103, 232, 249, 0.7);
}

.dashboard-placeholder {
  margin-top: 0;
}

@media (max-width: 1300px) {
  .dashboard-stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 1024px) {
  .dashboard-grid,
  .dashboard-grid--bottom {
    grid-template-columns: 1fr;
  }

  .dashboard-widget--activity {
    grid-row: auto;
  }
}

@media (max-width: 760px) {
  .dashboard-stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 560px) {
  .dashboard-stats {
    grid-template-columns: 1fr;
  }
}
</style>
