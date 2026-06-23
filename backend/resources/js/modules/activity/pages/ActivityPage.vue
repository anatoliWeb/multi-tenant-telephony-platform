<template>
  <section class="activity-page">
    <header class="activity-page__header c-card">
      <div>
        <h2 class="activity-page__title">Activity Logs</h2>
        <p class="activity-page__subtitle">Track audit events, security actions, and admin operations across all modules.</p>
      </div>
      <span class="activity-page__stat">Total: {{ paginationMeta.total }}</span>
    </header>

    <ActivityFilters
      :search="query.search"
      :module="query.module"
      :modules="availableModules"
      :action-type="query.actionType"
      :action-types="availableActionTypes"
      :status="query.status"
      :user="query.user"
      :users="availableUsers"
      :date-range="query.dateRange"
      @update:search="onSearchChange"
      @update:module="onModuleChange"
      @update:action-type="onActionTypeChange"
      @update:status="onStatusChange"
      @update:user="onUserChange"
      @update:date-range="onDateRangeChange"
    />

    <section class="c-card activity-page__table-wrap">
      <div v-if="isLoading" class="activity-page__state"><BaseLoader label="Loading activity..." /></div>

      <BaseErrorState v-else-if="errorMessage" title="Failed to load activity logs" :description="errorMessage">
        <button type="button" class="activity-page__retry" @click="loadActivity">Retry</button>
      </BaseErrorState>

      <template v-else>
        <BaseTable :columns="tableColumns" :rows="logs" row-key="id">
          <template #empty>
            <BaseEmptyState title="No activity events" description="No audit entries match the selected filters." />
          </template>

          <template #cell:user="{ row }">
            <div class="activity-user-cell">
              <span class="activity-user-cell__avatar">{{ initials((row.user as { name?: string; email?: string } | null)?.name || 'System') }}</span>
              <div>
                <div class="activity-user-cell__name">{{ (row.user as { name?: string } | null)?.name || 'System' }}</div>
                <div class="activity-user-cell__meta">{{ (row.user as { email?: string } | null)?.email || 'system@local' }}</div>
              </div>
            </div>
          </template>

          <template #cell:action="{ row }">
            <span class="activity-badge" :class="eventClass(row.action as string)">{{ row.action }}</span>
          </template>

          <template #cell:module="{ row }">
            <span class="activity-badge activity-badge--module">{{ row.module }}</span>
          </template>

          <template #cell:entity="{ row }">
            <div class="activity-entity-cell">
              <div class="activity-entity-cell__name">{{ row.entity }}</div>
              <div class="activity-entity-cell__desc">{{ row.description }}</div>
            </div>
          </template>

          <template #cell:status="{ row }">
            <span class="activity-badge" :class="statusClass(row.status as string)">{{ row.status }}</span>
          </template>

          <template #cell:ip_address="{ row }">
            {{ (row.ip_address as string | null) || '-' }}
          </template>

          <template #cell:created_at="{ row }">
            {{ formatDate((row.created_at as string | null | undefined) ?? null) }}
          </template>

          <template #cell:actions="{ row }">
            <ActivityRowActions v-if="canViewDetails" @details="openDetailsPanel(row.id as string, row.action as string)" />
            <span v-else class="activity-page__action-muted">Restricted</span>
          </template>
        </BaseTable>

        <footer class="activity-page__footer">
          <UsersPagination
            :current-page="query.page"
            :total-pages="paginationMeta.last_page"
            :per-page="query.perPage"
            :total-items="paginationMeta.total"
            :range-start="visibleRange.start"
            :range-end="visibleRange.end"
            @change="onPageChange"
            @update:per-page="onPerPageChange"
          />
        </footer>
      </template>
    </section>

    <section class="c-card activity-page__timeline-placeholder">
      <h3 class="activity-page__timeline-title">Realtime Timeline Coming Next</h3>
      <p class="activity-page__timeline-text">This module is prepared for websocket-fed live event streams and detailed audit side-panels.</p>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

import ActivityFilters from '../components/ActivityFilters.vue';
import ActivityDetailsDrawer from '../components/ActivityDetailsDrawer.vue';
import ActivityRowActions from '../components/ActivityRowActions.vue';
import { activityService } from '../services/activity.service';
import type { ActivityListFilters, ActivityListMeta, ActivityLogItem, ActivityQuery } from '../types/activity.types';
import UsersPagination from '../../users/components/UsersPagination.vue';
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import BaseTable, { type BaseTableColumn } from '../../../shared/components/ui/BaseTable.vue';
import { useDrawer } from '../../../shared/drawer';
import { realtimeClient } from '../../../shared/services/realtime/realtime.client';
import type { ActivityStreamPayload } from '../../../shared/services/realtime/realtime.types';

/**
 * Activity logs module.
 *
 * AUDIT UX STRATEGY:
 * Enterprise monitoring needs dense but readable timelines. This page groups
 * events by module/action/status for fast triage while keeping a stable table+
 * filters architecture that can be upgraded to live websocket feeds later.
 */
const isLoading = ref(true);
const errorMessage = ref('');
const logs = ref<ActivityLogItem[]>([]);
const currentUserPermissions = ref<string[]>([]);
const paginationMeta = ref<ActivityListMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
});
const drawer = useDrawer();

const query = ref<ActivityQuery>({
  search: '',
  module: 'all',
  actionType: 'all',
  status: 'all',
  user: 'all',
  dateRange: 'all',
  page: 1,
  perPage: 10,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;
let unsubscribeActivityStream: (() => void) | null = null;

const tableColumns: BaseTableColumn[] = [
  { key: 'user', label: 'User', width: '220px' },
  { key: 'action', label: 'Action', width: '170px' },
  { key: 'module', label: 'Module', width: '130px', align: 'center' },
  { key: 'entity', label: 'Entity' },
  { key: 'status', label: 'Status', width: '100px', align: 'center' },
  { key: 'ip_address', label: 'IP Address', width: '130px' },
  { key: 'created_at', label: 'Created date', width: '130px' },
  { key: 'actions', label: 'Actions', width: '110px', align: 'right' },
];

const availableModules = computed(() => [...new Set(logs.value.map((item) => item.module))].sort());
const availableActionTypes = computed(() => [...new Set(logs.value.map((item) => item.action))].sort());
const availableUsers = computed(() => {
  return [...new Map(
    logs.value
      .filter((item) => item.user?.id !== undefined && item.user?.id !== null)
      .map((item) => [String(item.user?.id), { value: String(item.user?.id), label: item.user?.name || 'System' }]),
  ).values()];
});

const canViewDetails = computed(() => {
  return (
    currentUserPermissions.value.includes('activity.view') ||
    currentUserPermissions.value.includes('activity.view_all') ||
    currentUserPermissions.value.includes('activity.view_team')
  );
});

const visibleRange = computed(() => {
  const total = paginationMeta.value.total;
  if (total === 0) return { start: 0, end: 0 };
  const start = (paginationMeta.value.current_page - 1) * paginationMeta.value.per_page + 1;
  const end = Math.min(paginationMeta.value.current_page * paginationMeta.value.per_page, total);
  return { start, end };
});

const initials = (value: string): string =>
  value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

const statusClass = (status: string): string => {
  if (status === 'success') return 'activity-badge--success';
  if (status === 'warning') return 'activity-badge--warning';
  return 'activity-badge--error';
};

const eventClass = (action: string): string => {
  const normalized = action.toLowerCase();
  if (normalized.includes('create') || normalized.includes('login') || normalized.includes('assign')) return 'activity-badge--event-created';
  if (normalized.includes('update') || normalized.includes('edit')) return 'activity-badge--event-updated';
  if (normalized.includes('delete') || normalized.includes('revoke') || normalized.includes('logout')) return 'activity-badge--event-deleted';
  return 'activity-badge--event-default';
};

const formatDate = (value: string | null): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(parsed);
};

const onSearchChange = (value: string): void => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    query.value.search = value;
    query.value.page = 1;
    void loadActivity();
  }, 250);
};

const onModuleChange = (value: string): void => { query.value.module = value; query.value.page = 1; void loadActivity(); };
const onActionTypeChange = (value: string): void => { query.value.actionType = value; query.value.page = 1; void loadActivity(); };
const onStatusChange = (value: 'all' | 'success' | 'warning' | 'error'): void => { query.value.status = value; query.value.page = 1; void loadActivity(); };
const onUserChange = (value: string): void => { query.value.user = value; query.value.page = 1; void loadActivity(); };
const onDateRangeChange = (value: 'all' | 'today' | '7d' | '30d'): void => { query.value.dateRange = value; query.value.page = 1; void loadActivity(); };
const onPageChange = (page: number): void => { query.value.page = Math.max(page, 1); void loadActivity(); };
const onPerPageChange = (size: number): void => { query.value.perPage = size; query.value.page = 1; void loadActivity(); };

const openDetailsPanel = (id: string, action: string): void => {
  const log = logs.value.find((item) => item.id === id);
  if (!log) return;

  drawer.open({
    component: ActivityDetailsDrawer,
    title: 'Activity Details',
    subtitle: `${action} (${id})`,
    size: 'lg',
    position: 'right',
    props: { log },
  });
};

const loadActivity = async (): Promise<void> => {
  try {
    isLoading.value = true;
    errorMessage.value = '';

    const filters: ActivityListFilters = {
      page: query.value.page,
      per_page: query.value.perPage,
    };

    if (query.value.search.trim() !== '') {
      filters.search = query.value.search.trim();
    }

    if (query.value.actionType !== 'all') {
      filters.action = query.value.actionType;
    }

    if (query.value.module !== 'all') {
      filters.subject_type = query.value.module;
    }

    if (query.value.user !== 'all') {
      const userId = Number(query.value.user);
      if (!Number.isNaN(userId) && userId > 0) {
        filters.user_id = userId;
      }
    }

    const now = new Date();
    if (query.value.dateRange === 'today') {
      const today = now.toISOString().slice(0, 10);
      filters.date_from = today;
      filters.date_to = today;
    } else if (query.value.dateRange === '7d' || query.value.dateRange === '30d') {
      const days = query.value.dateRange === '7d' ? 7 : 30;
      const from = new Date(now.getTime() - days * 24 * 60 * 60 * 1000);
      filters.date_from = from.toISOString().slice(0, 10);
      filters.date_to = now.toISOString().slice(0, 10);
    }

    const [items, meta] = await Promise.all([
      activityService.fetchActivity(filters),
      activityService.fetchActivityMeta(),
    ]);

    logs.value = items.items;
    paginationMeta.value = items.meta;
    query.value.page = items.meta.current_page;
    query.value.perPage = items.meta.per_page;
    currentUserPermissions.value = meta.current_user_permissions;
  } catch (error) {
    errorMessage.value = (error as { message?: string })?.message ?? 'Unable to fetch activity logs.';
  } finally {
    isLoading.value = false;
  }
};

const applyRealtimeActivityEvent = (payload: ActivityStreamPayload): void => {
  const id = String(payload.id);
  const exists = logs.value.some((entry) => entry.id === id);
  if (exists) {
    return;
  }

  const action = payload.action;
  const description = payload.description ?? action.replaceAll('_', ' ');
  const module = action.replaceAll('.', '_').split('_')[0] || 'system';
  const entityParts = action.replaceAll('.', '_').split('_');
  const entity = entityParts.length > 1 ? entityParts.slice(1).join('_') : 'event';
  const lower = `${action} ${description}`.toLowerCase();
  const status: ActivityLogItem['status'] = lower.includes('failed') || lower.includes('error') || lower.includes('denied')
    ? 'error'
    : (lower.includes('revoked') || lower.includes('deleted') || lower.includes('expired') ? 'warning' : 'success');

  const next: ActivityLogItem = {
    id,
    user: payload.user,
    action,
    module,
    entity,
    description,
    status,
    ip_address: null,
    created_at: payload.created_at,
    meta: payload.meta ?? {},
  };

  logs.value = [next, ...logs.value].slice(0, query.value.perPage);
  paginationMeta.value = {
    ...paginationMeta.value,
    total: paginationMeta.value.total + 1,
  };
};

onMounted(() => {
  void loadActivity();
  unsubscribeActivityStream = realtimeClient.onActivityLogged((payload) => {
    applyRealtimeActivityEvent(payload);
  });
});

onUnmounted(() => {
  unsubscribeActivityStream?.();
  unsubscribeActivityStream = null;
});
</script>

<style scoped>
.activity-page{display:grid;gap:12px}
.activity-page__header{margin-top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.activity-page__title{margin:0;font-size:18px;color:#f8fafc}
.activity-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.activity-page__stat{border-radius:999px;border:1px solid rgba(71,85,105,.6);padding:4px 9px;font-size:11px;color:#cbd5e1}
.activity-page__table-wrap{margin-top:0;display:grid;gap:10px}
.activity-page__state{padding:14px 0}
.activity-page__retry{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 11px}
.activity-user-cell{display:flex;align-items:center;gap:8px}
.activity-user-cell__avatar{width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:rgba(59,130,246,.2);color:#bfdbfe;font-size:10px;font-weight:700}
.activity-user-cell__name{color:#f8fafc;font-size:12px;font-weight:600}
.activity-user-cell__meta{color:#94a3b8;font-size:11px}
.activity-badge{border-radius:999px;font-size:11px;padding:2px 8px;border:1px solid rgba(71,85,105,.6)}
.activity-badge--module{background:rgba(59,130,246,.18);color:#bfdbfe;border-color:rgba(59,130,246,.4)}
.activity-badge--event-created{background:rgba(34,197,94,.16);color:#86efac;border-color:rgba(34,197,94,.42)}
.activity-badge--event-updated{background:rgba(59,130,246,.16);color:#93c5fd;border-color:rgba(59,130,246,.42)}
.activity-badge--event-deleted{background:rgba(245,158,11,.16);color:#fcd34d;border-color:rgba(245,158,11,.42)}
.activity-badge--event-default{background:rgba(148,163,184,.16);color:#cbd5e1;border-color:rgba(148,163,184,.42)}
.activity-badge--success{background:rgba(16,185,129,.16);color:#6ee7b7;border-color:rgba(16,185,129,.45)}
.activity-badge--warning{background:rgba(245,158,11,.16);color:#fcd34d;border-color:rgba(245,158,11,.45)}
.activity-badge--error{background:rgba(239,68,68,.16);color:#fca5a5;border-color:rgba(239,68,68,.45)}
.activity-entity-cell{min-width:180px}
.activity-entity-cell__name{color:#e2e8f0;font-size:12px;font-weight:600}
.activity-entity-cell__desc{color:#94a3b8;font-size:11px}
.activity-page__action-muted{color:#64748b;font-size:12px}
.activity-page__footer{display:flex;justify-content:flex-end}
.activity-page__timeline-placeholder{margin-top:0;border:1px dashed rgba(71,85,105,.6)}
.activity-page__timeline-title{margin:0;color:#f8fafc;font-size:15px}
.activity-page__timeline-text{margin:6px 0 0;color:#94a3b8;font-size:13px}
@media (max-width:760px){.activity-page__header{flex-direction:column}.activity-page__footer{justify-content:flex-start}}
</style>
