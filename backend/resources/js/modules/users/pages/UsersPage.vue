<template>
  <section class="users-page">
    <header class="users-page__header c-card">
      <div>
        <h2 class="users-page__title">{{ t('common.usersPage.title') }}</h2>
        <p class="users-page__subtitle">{{ t('common.usersPage.subtitle') }}</p>
      </div>
      <div class="users-page__stats">
        <span class="users-page__stat">{{ t('common.labels.total') }}: {{ filteredUsers.length }}</span>
        <span v-if="isRefreshing" class="users-page__stat">{{ t('common.loading') }}...</span>
        <button v-if="can('users.create')" type="button" class="users-page__create-btn" @click="openCreateModal">{{ t('common.usersPage.createUser') }}</button>
      </div>
    </header>

    <UsersFilters
      :search="query.search"
      :role="query.role"
      :status="query.status"
      :roles="availableRoles"
      @update:search="onSearchChange"
      @update:role="onRoleChange"
      @update:status="onStatusChange"
    />

    <section class="c-card users-page__table-wrap">
      <div v-if="isLoading" class="users-page__state">
        <BaseLoader :label="t('common.usersPage.loadingUsers')" />
      </div>

      <BaseErrorState
        v-else-if="errorMessage"
        :title="t('common.usersPage.failedLoadUsers')"
        :description="errorMessage"
      >
        <button type="button" class="users-page__retry" @click="loadUsers">{{ t('common.actions.retry') }}</button>
      </BaseErrorState>

      <template v-else>
        <BaseTable :columns="tableColumns" :rows="paginatedUsers" row-key="id">
          <template #empty>
            <BaseEmptyState :title="t('common.usersPage.noUsersFound')" :description="t('common.usersPage.noUsersHint')" />
          </template>

          <template #cell:avatar="{ row }">
            <span class="users-avatar">{{ initials(row.name as string) }}</span>
          </template>

          <template #cell:name="{ row }">
            <div class="users-main-cell">
              <div class="users-main-cell__name">{{ row.name }}</div>
              <div class="users-main-cell__email">{{ row.email }}</div>
            </div>
          </template>

          <template #cell:roles="{ row }">
            <div class="users-badges">
              <span v-for="role in (row.roles as string[])" :key="role" class="users-badge users-badge--role">{{ role }}</span>
              <span v-if="(row.roles as string[]).length === 0" class="users-badge users-badge--muted">{{ t('common.labels.noRoles') }}</span>
            </div>
          </template>

          <template #cell:permissions_count="{ row }">
            {{ (row.permissions as string[]).length }}
          </template>

          <template #cell:status="{ row }">
            <span class="users-badge" :class="(row.status as string) === 'active' ? 'users-badge--active' : 'users-badge--inactive'">
              {{ row.status }}
            </span>
          </template>

          <template #cell:created_at="{ row }">
            {{ formatDate((row.created_at as string | null | undefined) ?? null) }}
          </template>

          <template #cell:actions="{ row }">
            <UsersRowActions
              :can-edit="can('users.edit')"
              :can-delete="can('users.delete')"
              @action="(action) => handleRowAction(action, row.id as number)"
            />
          </template>
        </BaseTable>

        <footer class="users-page__footer">
          <UsersPagination
            :current-page="query.page"
            :total-pages="totalPages"
            :per-page="query.perPage"
            :total-items="filteredUsers.length"
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
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import UsersFilters from '../components/UsersFilters.vue';
import UsersPagination from '../components/UsersPagination.vue';
import UsersRowActions from '../components/UsersRowActions.vue';
import { usersService } from '../services/users.service';
import type { UserListItem, UsersQuery } from '../types/users.types';
import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import BaseTable, { type BaseTableColumn } from '../../../shared/components/ui/BaseTable.vue';
import type { PaginationMeta } from '../../../types/response.types';
import UserCreateModal from '../components/UserCreateModal.vue';
import UserDetailsModal from '../components/UserDetailsModal.vue';
import UserEditModal from '../components/UserEditModal.vue';
import { useConfirm } from '../../../shared/confirm';
import { cacheStore, useCachedRequest } from '../../../shared/cache';
import { useModal } from '../../../shared/modal';
import { useOptimisticAction } from '../../../shared/optimistic';
import { useToast } from '../../../shared/toast';

/**
 * Users module page (CRUD blueprint).
 *
 * WHY THIS MATTERS:
 * This page defines the reusable admin pattern for future modules (roles,
 * permissions, tokens): filters + table + permission-aware actions + states.
 * Keeping this structure modular prevents ad-hoc CRUD implementations.
 */
const isLoading = ref(true);
const isRefreshing = ref(false);
const errorMessage = ref('');
const users = ref<UserListItem[]>([]);
const currentUserPermissions = ref<string[]>([]);
const backendMeta = ref<PaginationMeta | null>(null);
const modal = useModal();
const confirm = useConfirm();
const optimistic = useOptimisticAction();
const toast = useToast();
const { t, locale } = useI18n({ useScope: 'global' });
const USERS_CACHE_KEY = 'users.list';
const USERS_META_CACHE_KEY = 'users.meta';

const query = ref<UsersQuery>({
  search: '',
  role: 'all',
  status: 'all',
  page: 1,
  perPage: 10,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;

const tableColumns = computed<BaseTableColumn[]>(() => [
  { key: 'avatar', label: t('common.usersTable.avatar'), width: '72px', align: 'center' },
  { key: 'name', label: t('common.usersTable.name') },
  { key: 'roles', label: t('common.usersTable.roles') },
  { key: 'permissions_count', label: t('common.usersTable.permissions'), width: '110px', align: 'center' },
  { key: 'status', label: t('common.usersTable.status'), width: '120px', align: 'center' },
  { key: 'created_at', label: t('common.usersTable.createdDate'), width: '140px' },
  { key: 'actions', label: t('common.usersTable.actions'), width: '110px', align: 'right' },
]);

const availableRoles = computed(() => {
  return [...new Set(users.value.flatMap((item) => item.roles))].sort((a, b) => a.localeCompare(b));
});

const filteredUsers = computed(() => {
  const search = query.value.search.trim().toLowerCase();

  return users.value.filter((user) => {
    const searchMatch =
      search.length === 0 ||
      user.name.toLowerCase().includes(search) ||
      user.email.toLowerCase().includes(search);

    const roleMatch = query.value.role === 'all' || user.roles.includes(query.value.role);
    const statusMatch = query.value.status === 'all' || user.status === query.value.status;

    return searchMatch && roleMatch && statusMatch;
  });
});

const totalPages = computed(() => {
  const useBackendMeta = hasServerPagination.value && !hasActiveFilters.value;
  if (useBackendMeta && backendMeta.value) {
    return Math.max(backendMeta.value.last_page, 1);
  }

  return Math.max(Math.ceil(filteredUsers.value.length / query.value.perPage), 1);
});

const paginatedUsers = computed(() => {
  const useBackendMeta = hasServerPagination.value && !hasActiveFilters.value;

  if (useBackendMeta) {
    // Backend-pagination-ready branch: if API returns already paginated rows,
    // UI keeps consuming server meta without changing table/pager contracts.
    return filteredUsers.value;
  }

  const start = (query.value.page - 1) * query.value.perPage;
  return filteredUsers.value.slice(start, start + query.value.perPage);
});

const visibleRange = computed(() => {
  const total = filteredUsers.value.length;
  if (total === 0) {
    return { start: 0, end: 0 };
  }

  const start = (query.value.page - 1) * query.value.perPage + 1;
  const end = Math.min(query.value.page * query.value.perPage, total);
  return { start, end };
});

const hasActiveFilters = computed(() => {
  return query.value.search.length > 0 || query.value.role !== 'all' || query.value.status !== 'all';
});

const hasServerPagination = computed(() => {
  return !!backendMeta.value;
});

const can = (permission: string): boolean => {
  // Permission-aware rendering keeps destructive actions hidden by default.
  // Backend remains source-of-truth, UI only reflects allowed capabilities.
  return currentUserPermissions.value.includes(permission);
};

const initials = (name: string): string => {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
};

const formatDate = (value: string | null): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';

  return new Intl.DateTimeFormat(locale.value, {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  }).format(parsed);
};

const onSearchChange = (value: string): void => {
  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    query.value.search = value;
    query.value.page = 1;
  }, 250);
};

const onRoleChange = (value: string): void => {
  query.value.role = value;
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
  // CRUD entrypoint strategy:
  // - create flows use modal (focused short form)
  // - edit/details use drawer (context-preserving side workflow)
  // This keeps interactions consistent across modules.
  modal.open({
    component: UserCreateModal,
    title: t('common.usersPage.createUserTitle'),
    subtitle: t('common.usersPage.createUserSubtitle'),
    size: 'lg',
    props: {
      onCreated: (item: UserListItem) => {
        users.value = [item, ...users.value];
        syncUsersCache();
        cacheStore.invalidatePrefix('dashboard.');
      },
    },
  });
};

const handleRowAction = async (action: 'view' | 'edit' | 'delete', userId: number): Promise<void> => {
  const user = users.value.find((item) => item.id === userId);
  if (!user) return;

  if (action === 'view') {
    modal.open({
      component: UserDetailsModal,
      title: t('common.usersPage.userDetails'),
      subtitle: `${user.name} (${user.email})`,
      size: 'md',
      props: { user },
    });
    return;
  }

  if (action === 'edit') {
    modal.open({
      component: UserEditModal,
      title: t('common.usersPage.editUser'),
      subtitle: `Update ${user.name}`,
      size: 'lg',
      props: {
        user,
        onUpdated: (updated: UserListItem) => {
          users.value = users.value.map((item) => (item.id === updated.id ? updated : item));
          syncUsersCache();
        },
      },
    });
    return;
  }

  const accepted = await confirm.open({
    title: t('common.usersPage.deleteUserTitle'),
    message: t('common.usersPage.deleteUserMessage', { name: user.name }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.actions.cancel'),
    variant: 'danger',
    destructive: true,
  });

  if (!accepted) return;

  const snapshot = [...users.value];
  // Optimistic delete contract:
  // apply local mutation immediately, rollback from snapshot on failure.
  await optimistic.run({
    key: `user-delete-${user.id}`,
    apply: () => {
      users.value = users.value.filter((item) => item.id !== user.id);
    },
    action: async () => {
      await new Promise((resolve) => setTimeout(resolve, 220));
      return true;
    },
    rollback: () => {
      users.value = snapshot;
    },
    onSuccess: () => {
      toast.success({ title: t('common.usersPage.userDeleted'), message: t('common.usersPage.userRemoved', { name: user.name }) });
      syncUsersCache();
      cacheStore.invalidatePrefix('dashboard.');
    },
    onError: () => {
      toast.error({ title: t('common.usersPage.deleteFailed'), message: t('common.usersPage.deleteRollback') });
    },
  });
};

const isPaginationMeta = (meta: unknown): meta is PaginationMeta => {
  return (
    typeof meta === 'object' &&
    meta !== null &&
    typeof (meta as PaginationMeta).current_page === 'number' &&
    typeof (meta as PaginationMeta).last_page === 'number' &&
    typeof (meta as PaginationMeta).per_page === 'number' &&
    typeof (meta as PaginationMeta).total === 'number'
  );
};

const loadUsers = async (): Promise<void> => {
  try {
    const hasCache = cacheStore.has(USERS_CACHE_KEY) && cacheStore.has(USERS_META_CACHE_KEY);
    if (!hasCache) {
      isLoading.value = true;
    }

    isRefreshing.value = false;
    errorMessage.value = '';

    const [usersResult, permissionsResult] = await Promise.all([
      useCachedRequest({
        key: USERS_CACHE_KEY,
        ttl: 60_000,
        request: () => usersService.fetchUsers(),
        onBackgroundUpdate: (freshData) => {
          users.value = freshData.items;
          if (isPaginationMeta(freshData.meta)) {
            backendMeta.value = freshData.meta;
          }
        },
      }),
      useCachedRequest({
        key: USERS_META_CACHE_KEY,
        ttl: 60_000,
        request: () => usersService.fetchPermissionsMeta(),
        onBackgroundUpdate: (freshData) => {
          currentUserPermissions.value = freshData.current_user_permissions;
        },
      }),
    ]);

    users.value = usersResult.data.items;
    currentUserPermissions.value = permissionsResult.data.current_user_permissions;
    isRefreshing.value = usersResult.revalidating || permissionsResult.revalidating;

    if (isPaginationMeta(usersResult.data.meta)) {
      backendMeta.value = usersResult.data.meta;
      query.value.perPage = usersResult.data.meta.per_page;
      query.value.page = usersResult.data.meta.current_page;
    } else {
      backendMeta.value = null;
    }
  } catch (error) {
    errorMessage.value = (error as { message?: string })?.message ?? 'Unable to fetch users list.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadUsers();
});

const syncUsersCache = (): void => {
  cacheStore.set(USERS_CACHE_KEY, {
    items: users.value,
    meta: backendMeta.value ?? undefined,
  });
};
</script>

<style scoped>
.users-page {
  display: grid;
  gap: 12px;
}

.users-page__header {
  margin-top: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.users-page__title {
  margin: 0;
  font-size: 18px;
  color: #f8fafc;
}

.users-page__subtitle {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: 13px;
}

.users-page__stat {
  border-radius: 999px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  padding: 4px 9px;
  font-size: 11px;
  color: #cbd5e1;
}
.users-page__create-btn{height:32px;border-radius:8px;border:1px solid rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe;padding:0 11px;font-size:12px;font-weight:600}
.users-page__create-btn:hover{background:rgba(59,130,246,.26)}

.users-page__table-wrap {
  margin-top: 0;
  display: grid;
  gap: 10px;
}

.users-page__state {
  padding: 14px 0;
}

.users-page__retry {
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(71, 85, 105, 0.55);
  background: rgba(15, 23, 42, 0.7);
  color: #e2e8f0;
  padding: 0 11px;
}

.users-main-cell {
  min-width: 200px;
}

.users-main-cell__name {
  color: #f8fafc;
  font-weight: 600;
}

.users-main-cell__email {
  color: #94a3b8;
  font-size: 12px;
}

.users-avatar {
  width: 30px;
  height: 30px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(59, 130, 246, 0.2);
  color: #bfdbfe;
  font-size: 11px;
  font-weight: 700;
}

.users-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.users-badge {
  border-radius: 999px;
  font-size: 11px;
  padding: 2px 8px;
  border: 1px solid rgba(71, 85, 105, 0.6);
}

.users-badge--role {
  background: rgba(59, 130, 246, 0.18);
  color: #bfdbfe;
  border-color: rgba(59, 130, 246, 0.4);
}

.users-badge--muted {
  color: #94a3b8;
}

.users-badge--active {
  background: rgba(16, 185, 129, 0.16);
  color: #6ee7b7;
  border-color: rgba(16, 185, 129, 0.45);
}

.users-badge--inactive {
  background: rgba(239, 68, 68, 0.16);
  color: #fca5a5;
  border-color: rgba(239, 68, 68, 0.45);
}

.users-page__footer {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 760px) {
  .users-page__header {
    flex-direction: column;
  }

  .users-page__footer {
    justify-content: flex-start;
  }
}
</style>
