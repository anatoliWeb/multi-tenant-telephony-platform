<template>
  <section class="roles-page">
    <header class="roles-page__header c-card">
      <div>
        <h2 class="roles-page__title">{{ t('common.rolesPage.title') }}</h2>
        <p class="roles-page__subtitle">{{ t('common.rolesPage.subtitle') }}</p>
      </div>
      <div class="roles-page__header-actions">
        <span class="roles-page__stat">{{ t('common.labels.total') }}: {{ filteredRoles.length }}</span>
        <span v-if="isRefreshing" class="roles-page__stat">{{ t('common.loading') }}...</span>
        <button v-if="can('roles.create')" type="button" class="roles-page__create-btn" @click="openCreateModal">{{ t('common.rolesPage.createRole') }}</button>
      </div>
    </header>

    <RolesFilters
      :search="query.search"
      :type="query.type"
      :status="query.status"
      @update:search="onSearchChange"
      @update:type="onTypeChange"
      @update:status="onStatusChange"
    />

    <section class="c-card roles-page__table-wrap">
      <div v-if="isLoading" class="roles-page__state"><BaseLoader :label="t('common.rolesPage.loadingRoles')" /></div>

      <BaseErrorState v-else-if="errorMessage" :title="t('common.rolesPage.failedLoadRoles')" :description="errorMessage">
        <button type="button" class="roles-page__retry" @click="loadRoles">{{ t('common.actions.retry') }}</button>
      </BaseErrorState>

      <template v-else>
        <BaseTable :columns="tableColumns" :rows="paginatedRoles" row-key="id">
          <template #empty>
            <BaseEmptyState :title="t('common.rolesPage.noRolesFound')" :description="t('common.rolesPage.noRolesHint')" />
          </template>

          <template #cell:role="{ row }">
            <div class="roles-main-cell">
              <div class="roles-main-cell__name">{{ row.label }}</div>
              <div class="roles-main-cell__desc">{{ row.description || '-' }}</div>
            </div>
          </template>

          <template #cell:permissions_preview="{ row }">
            <div class="roles-preview">
              <span v-for="permission in previewPermissions(row.permissions as string[])" :key="permission" class="roles-badge roles-badge--permission">{{ permissionDisplayLabel(row as RoleListItem, permission) }}</span>
              <span v-if="(row.permissions as string[]).length > 2" class="roles-badge roles-badge--muted">+{{ (row.permissions as string[]).length - 2 }} {{ t('common.rolesPage.more') }}</span>
            </div>
          </template>

          <template #cell:permissions_count="{ row }">{{ row.permissions_count }}</template>
          <template #cell:users_count="{ row }">{{ row.users_count }}</template>

          <template #cell:type="{ row }">
            <span class="roles-badge" :class="roleTypeClass(row.name as string, row.type as string)">{{ row.type }}</span>
          </template>

          <template #cell:status="{ row }">
            <span class="roles-badge" :class="(row.status as string) === 'active' ? 'roles-badge--active' : 'roles-badge--inactive'">{{ row.status }}</span>
          </template>

          <template #cell:created_at="{ row }">{{ formatDate((row.created_at as string | null | undefined) ?? null) }}</template>

          <template #cell:actions="{ row }">
            <RolesRowActions
              :can-edit="can('roles.edit')"
              :can-delete="can('roles.delete')"
              :can-permissions="can('roles.permissions') || can('permissions.edit')"
              @action="(action) => handleRowAction(action, row.id as number)"
            />
          </template>
        </BaseTable>

        <footer class="roles-page__footer">
          <UsersPagination
            :current-page="query.page"
            :total-pages="totalPages"
            :per-page="query.perPage"
            :total-items="filteredRoles.length"
            :range-start="visibleRange.start"
            :range-end="visibleRange.end"
            @change="onPageChange"
            @update:per-page="onPerPageChange"
          />
        </footer>
      </template>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import RolesFilters from '../components/RolesFilters.vue';
import RolesRowActions from '../components/RolesRowActions.vue';
import RoleCreateModal from '../components/RoleCreateModal.vue';
import RoleDetailsModal from '../components/RoleDetailsModal.vue';
import RoleEditModal from '../components/RoleEditModal.vue';
import { rolesService } from '../services/roles.service';
import type { RoleListItem, RolesQuery } from '../types/roles.types';
import UsersPagination from '../../users/components/UsersPagination.vue';
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import BaseTable, { type BaseTableColumn } from '../../../shared/components/ui/BaseTable.vue';
import { cacheStore, useCachedRequest } from '../../../shared/cache';
import { useConfirm } from '../../../shared/confirm';
import { useModal } from '../../../shared/modal';
import { useOptimisticAction } from '../../../shared/optimistic';
import { useToast } from '../../../shared/toast';

/**
 * Roles module page (enterprise RBAC blueprint).
 *
 * ARCHITECTURE NOTE:
 * This module extends CRUD scaffolding into access-management flows by combining
 * role metadata, permission previews, and permission-aware actions. It provides
 * a scalable base for future permissions matrix and organization-level RBAC.
 */
const isLoading = ref(true);
const isRefreshing = ref(false);
const errorMessage = ref('');
const roles = ref<RoleListItem[]>([]);
const currentUserPermissions = ref<string[]>([]);
const modal = useModal();
const confirm = useConfirm();
const optimistic = useOptimisticAction();
const toast = useToast();
const { t, locale } = useI18n({ useScope: 'global' });
const rolesCacheKey = computed(() => `roles.list.${locale.value}`);
const rolesMetaCacheKey = computed(() => `roles.meta.${locale.value}`);

const query = ref<RolesQuery>({
  search: '',
  type: 'all',
  status: 'all',
  page: 1,
  perPage: 10,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;

const tableColumns = computed<BaseTableColumn[]>(() => [
  { key: 'role', label: t('common.rolesTable.role') },
  { key: 'permissions_preview', label: t('common.rolesTable.permissionsPreview') },
  { key: 'permissions_count', label: t('common.rolesTable.permissions'), width: '110px', align: 'center' },
  { key: 'users_count', label: t('common.rolesTable.users'), width: '90px', align: 'center' },
  { key: 'type', label: t('common.rolesTable.type'), width: '110px', align: 'center' },
  { key: 'status', label: t('common.rolesTable.status'), width: '110px', align: 'center' },
  { key: 'created_at', label: t('common.rolesTable.createdDate'), width: '130px' },
  { key: 'actions', label: t('common.rolesTable.actions'), width: '120px', align: 'right' },
]);

const filteredRoles = computed(() => {
  const search = query.value.search.trim().toLowerCase();

  return roles.value.filter((role) => {
    const searchMatch =
      search.length === 0 ||
      role.name.toLowerCase().includes(search) ||
      role.label.toLowerCase().includes(search) ||
      (role.description ?? '').toLowerCase().includes(search) ||
      role.permissions.some((permission) => permission.toLowerCase().includes(search)) ||
      Object.values(role.permissions_labels ?? {}).some((permissionLabel) => permissionLabel.toLowerCase().includes(search));

    const typeMatch = query.value.type === 'all' || role.type === query.value.type;
    const statusMatch = query.value.status === 'all' || role.status === query.value.status;

    return searchMatch && typeMatch && statusMatch;
  });
});

const totalPages = computed(() => Math.max(Math.ceil(filteredRoles.value.length / query.value.perPage), 1));

const paginatedRoles = computed(() => {
  const start = (query.value.page - 1) * query.value.perPage;
  return filteredRoles.value.slice(start, start + query.value.perPage);
});

const visibleRange = computed(() => {
  const total = filteredRoles.value.length;
  if (total === 0) return { start: 0, end: 0 };

  const start = (query.value.page - 1) * query.value.perPage + 1;
  const end = Math.min(query.value.page * query.value.perPage, total);
  return { start, end };
});

const can = (permission: string): boolean => currentUserPermissions.value.includes(permission);

const previewPermissions = (permissions: string[]): string[] => permissions.slice(0, 2);
const permissionDisplayLabel = (role: RoleListItem, permissionName: string): string => {
  return role.permissions_labels?.[permissionName] ?? permissionName;
};

const formatDate = (value: string | null): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat(locale.value, { month: 'short', day: '2-digit', year: 'numeric' }).format(parsed);
};

const roleTypeClass = (name: string, type: string): string => {
  const normalized = name.toLowerCase();
  if (normalized === 'admin') return 'roles-badge--admin';
  if (normalized === 'manager') return 'roles-badge--manager';
  if (normalized === 'user') return 'roles-badge--user';
  return type === 'system' ? 'roles-badge--system' : 'roles-badge--custom';
};

const onSearchChange = (value: string): void => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    query.value.search = value;
    query.value.page = 1;
  }, 250);
};

const onTypeChange = (value: 'all' | 'system' | 'custom'): void => {
  query.value.type = value;
  query.value.page = 1;
};

const onStatusChange = (value: 'all' | 'active' | 'inactive'): void => {
  query.value.status = value;
  query.value.page = 1;
};

const onPageChange = (page: number): void => {
  query.value.page = Math.min(Math.max(page, 1), totalPages.value);
};

const onPerPageChange = (size: number): void => {
  query.value.perPage = size;
  query.value.page = 1;
};

const openCreateModal = (): void => {
  modal.open({
    component: RoleCreateModal,
    title: t('common.rolesPage.createRoleTitle'),
    subtitle: t('common.rolesPage.createRoleSubtitle'),
    size: 'lg',
    props: {
      onCreated: (item: RoleListItem) => {
        roles.value = [item, ...roles.value];
        syncRolesCache();
        cacheStore.invalidatePrefix('dashboard.');
      },
    },
  });
};

const handleRowAction = async (action: 'view' | 'edit' | 'permissions' | 'delete', roleId: number): Promise<void> => {
  const role = roles.value.find((item) => item.id === roleId);
  if (!role) return;

  if (action === 'view' || action === 'permissions') {
    modal.open({
      component: RoleDetailsModal,
      title: action === 'view' ? t('common.rolesPage.roleDetails') : t('common.rolesPage.rolePermissions'),
      subtitle: role.label,
      size: 'md',
      props: { role },
    });
    return;
  }

  if (action === 'edit') {
    modal.open({
      component: RoleEditModal,
      title: t('common.rolesPage.editRole'),
      subtitle: role.label,
      size: 'lg',
      props: {
        role,
        onUpdated: (updated: RoleListItem) => {
          roles.value = roles.value.map((item) => (item.id === updated.id ? updated : item));
          syncRolesCache();
        },
      },
    });
    return;
  }

  const accepted = await confirm.open({
    title: t('common.rolesPage.deleteRoleTitle'),
    message: t('common.rolesPage.deleteRoleMessage', { name: role.label }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.actions.cancel'),
    variant: 'danger',
    destructive: true,
  });

  if (!accepted) return;

  const snapshot = [...roles.value];
  await optimistic.run({
    key: `role-delete-${role.id}`,
    apply: () => {
      roles.value = roles.value.filter((item) => item.id !== role.id);
    },
    action: async () => {
      await new Promise((resolve) => setTimeout(resolve, 220));
      return true;
    },
    rollback: () => {
      roles.value = snapshot;
    },
    onSuccess: () => toast.success({ title: t('common.rolesPage.roleDeleted'), message: t('common.rolesPage.roleRemoved', { name: role.label }) }),
    onError: () => toast.error({ title: t('common.rolesPage.deleteFailed'), message: t('common.rolesPage.deleteRollback') }),
  });
  syncRolesCache();
  cacheStore.invalidatePrefix('dashboard.');
};

const loadRoles = async (): Promise<void> => {
  try {
    const hasCache = cacheStore.has(rolesCacheKey.value) && cacheStore.has(rolesMetaCacheKey.value);
    if (!hasCache) {
      isLoading.value = true;
    }
    isRefreshing.value = false;
    errorMessage.value = '';

    const [rolesResult, metaResult] = await Promise.all([
      useCachedRequest({
        key: rolesCacheKey.value,
        ttl: 90_000,
        request: () => rolesService.fetchRoles(),
        onBackgroundUpdate: (freshData) => {
          roles.value = freshData;
        },
      }),
      useCachedRequest({
        key: rolesMetaCacheKey.value,
        ttl: 90_000,
        request: () => rolesService.fetchPermissionsMeta(),
        onBackgroundUpdate: (freshData) => {
          currentUserPermissions.value = freshData.current_user_permissions;
        },
      }),
    ]);

    roles.value = rolesResult.data;
    currentUserPermissions.value = metaResult.data.current_user_permissions;
    isRefreshing.value = rolesResult.revalidating || metaResult.revalidating;
  } catch (error) {
    errorMessage.value = (error as { message?: string })?.message ?? 'Unable to fetch roles list.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadRoles();
});

watch(locale, () => {
  void loadRoles();
});

const syncRolesCache = (): void => {
  cacheStore.set(rolesCacheKey.value, [...roles.value]);
};
</script>

<style scoped>
.roles-page{display:grid;gap:12px}
.roles-page__header{margin-top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.roles-page__title{margin:0;font-size:18px;color:#f8fafc}
.roles-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.roles-page__stat{border-radius:999px;border:1px solid rgba(71,85,105,.6);padding:4px 9px;font-size:11px;color:#cbd5e1}
.roles-page__header-actions{display:flex;align-items:center;gap:8px}
.roles-page__create-btn{height:32px;border-radius:8px;border:1px solid rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe;padding:0 11px;font-size:12px;font-weight:600}
.roles-page__create-btn:hover{background:rgba(59,130,246,.26)}
.roles-page__table-wrap{margin-top:0;display:grid;gap:10px}
.roles-page__state{padding:14px 0}
.roles-page__retry{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 11px}
.roles-main-cell{min-width:200px}
.roles-main-cell__name{color:#f8fafc;font-weight:600}
.roles-main-cell__desc{color:#94a3b8;font-size:12px}
.roles-preview{display:flex;flex-wrap:wrap;gap:6px}
.roles-badge{border-radius:999px;font-size:11px;padding:2px 8px;border:1px solid rgba(71,85,105,.6)}
.roles-badge--permission{background:rgba(59,130,246,.18);color:#bfdbfe;border-color:rgba(59,130,246,.4)}
.roles-badge--muted{color:#94a3b8}
.roles-badge--active{background:rgba(16,185,129,.16);color:#6ee7b7;border-color:rgba(16,185,129,.45)}
.roles-badge--inactive{background:rgba(239,68,68,.16);color:#fca5a5;border-color:rgba(239,68,68,.45)}
.roles-badge--admin{background:rgba(244,114,182,.18);color:#f9a8d4;border-color:rgba(244,114,182,.42)}
.roles-badge--manager{background:rgba(34,211,238,.16);color:#67e8f9;border-color:rgba(34,211,238,.42)}
.roles-badge--user{background:rgba(132,204,22,.16);color:#bef264;border-color:rgba(132,204,22,.42)}
.roles-badge--system{background:rgba(59,130,246,.16);color:#93c5fd;border-color:rgba(59,130,246,.42)}
.roles-badge--custom{background:rgba(245,158,11,.16);color:#fcd34d;border-color:rgba(245,158,11,.42)}
.roles-page__footer{display:flex;justify-content:flex-end}
@media (max-width:760px){.roles-page__header{flex-direction:column}.roles-page__footer{justify-content:flex-start}}
</style>
