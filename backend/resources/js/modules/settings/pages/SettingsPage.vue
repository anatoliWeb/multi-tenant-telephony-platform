<template>
  <section class="settings-page">
    <header class="settings-page__header c-card">
      <div>
        <h2 class="settings-page__title">Settings</h2>
        <p class="settings-page__subtitle">Dynamic platform configuration with typed values, flags, and effective-resolution preview.</p>
      </div>
      <div class="settings-page__header-actions">
        <span class="settings-page__stat">{{ t('common.labels.total') }}: {{ totalItems }}</span>
        <button type="button" class="settings-page__create-btn" @click="openCreateModal">{{ t('common.actions.create') }}</button>
      </div>
    </header>

    <SettingsFilters
      :search="query.search"
      :group="query.group"
      :groups="groups"
      :type="query.type"
      :types="types"
      :channel="query.channel"
      :is-active="query.is_active"
      :is-public="query.is_public"
      :is-encrypted="query.is_encrypted"
      @update:search="onSearchChange"
      @update:group="onGroupChange"
      @update:type="onTypeChange"
      @update:channel="onChannelChange"
      @update:is-active="onActiveChange"
      @update:is-public="onPublicChange"
      @update:is-encrypted="onEncryptedChange"
    />

    <section class="c-card settings-page__table-wrap">
      <div v-if="isLoading" class="settings-page__state"><BaseLoader label="Loading settings..." /></div>

      <BaseErrorState v-else-if="errorMessage" title="Failed to load settings" :description="errorMessage">
        <button type="button" class="settings-page__retry" @click="loadSettings">Retry</button>
      </BaseErrorState>

      <template v-else>
        <BaseTable :columns="tableColumns" :rows="settings" row-key="id">
          <template #empty>
            <BaseEmptyState title="No settings found" description="Try changing filters or create the first setting in this group." />
          </template>

          <template #cell:key="{ row }">
            <div class="settings-main-cell">
              <div class="settings-main-cell__name">{{ row.label }}</div>
              <div class="settings-main-cell__meta">{{ row.key }}</div>
              <div class="settings-main-cell__desc">{{ row.description || '-' }}</div>
            </div>
          </template>

          <template #cell:group="{ row }">
            <span class="settings-badge settings-badge--group">{{ row.group }}</span>
          </template>

          <template #cell:scope="{ row }">
            <span class="settings-badge settings-badge--scope">{{ scopeLabel(row) }}</span>
          </template>

          <template #cell:type="{ row }">
            <span class="settings-badge">{{ row.type }}</span>
          </template>

          <template #cell:value="{ row }">
            <div class="settings-value">{{ stringify(effective[row.key]?.value ?? row.value) }}</div>
          </template>

          <template #cell:source="{ row }">
            <span class="settings-badge" :class="`is-${effective[row.key]?.source ?? 'global'}`">{{ effective[row.key]?.source ?? 'global' }}</span>
          </template>

          <template #cell:flags="{ row }">
            <div class="settings-flags">
              <span class="settings-badge" :class="{ 'is-on': row.is_frontend }">FE</span>
              <span class="settings-badge" :class="{ 'is-on': row.is_backend }">BE</span>
              <span class="settings-badge" :class="{ 'is-on': row.is_public }">PUB</span>
              <span class="settings-badge" :class="{ 'is-on': row.is_encrypted }">ENC</span>
              <span class="settings-badge" :class="{ 'is-on': row.is_active }">ACT</span>
            </div>
          </template>

          <template #cell:actions="{ row }">
            <SettingsRowActions @action="(action) => handleRowAction(action, Number(row.id))" />
          </template>
        </BaseTable>

        <footer class="settings-page__footer">
          <UsersPagination
            :current-page="query.page"
            :total-pages="totalPages"
            :per-page="query.per_page"
            :total-items="totalItems"
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

import BaseEmptyState from '../../../shared/components/ui/BaseEmptyState.vue';
import BaseErrorState from '../../../shared/components/ui/BaseErrorState.vue';
import BaseLoader from '../../../shared/components/ui/BaseLoader.vue';
import BaseTable, { type BaseTableColumn } from '../../../shared/components/ui/BaseTable.vue';
import { useConfirm } from '../../../shared/confirm';
import { useModal } from '../../../shared/modal';
import UsersPagination from '../../users/components/UsersPagination.vue';
import SettingsCreateModal from '../components/SettingsCreateModal.vue';
import SettingsDetailsModal from '../components/SettingsDetailsModal.vue';
import SettingsEditModal from '../components/SettingsEditModal.vue';
import SettingsFilters from '../components/SettingsFilters.vue';
import SettingsRowActions from '../components/SettingsRowActions.vue';
import { settingsService } from '../services/settings.service';
import type { EffectiveSetting, SettingValueType, SystemSettingRecord } from '../types/settings.types';

type BoolFilter = 'all' | 'true' | 'false';
type ChannelFilter = '' | 'frontend' | 'backend';

const isLoading = ref(true);
const errorMessage = ref('');
const settings = ref<SystemSettingRecord[]>([]);
const effective = ref<Record<string, EffectiveSetting>>({});
const groups = ref<string[]>([]);
const types = ref<SettingValueType[]>([]);
const totalItems = ref(0);
const totalPages = ref(1);
const query = ref({
  search: '',
  group: '',
  type: '',
  channel: '' as ChannelFilter,
  is_active: 'all' as BoolFilter,
  is_public: 'all' as BoolFilter,
  is_encrypted: 'all' as BoolFilter,
  page: 1,
  per_page: 10,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;
const modal = useModal();
const confirm = useConfirm();
const { t, locale } = useI18n({ useScope: 'global' });

const tableColumns = computed<BaseTableColumn[]>(() => [
  { key: 'key', label: t('common.settings') },
  { key: 'group', label: t('common.permissionsTable.module'), width: '130px', align: 'center' },
  { key: 'scope', label: t('common.route'), width: '130px', align: 'center' },
  { key: 'type', label: t('common.permissionsTable.type'), width: '110px', align: 'center' },
  { key: 'value', label: t('common.runtimeContext') },
  { key: 'source', label: t('common.systemActivity'), width: '120px', align: 'center' },
  { key: 'flags', label: 'Flags', width: '160px', align: 'center' },
  { key: 'actions', label: t('common.actions.actions'), width: '90px', align: 'right' },
]);

const visibleRange = computed(() => {
  if (totalItems.value === 0) return { start: 0, end: 0 };
  const start = (query.value.page - 1) * query.value.per_page + 1;
  const end = Math.min(query.value.page * query.value.per_page, totalItems.value);
  return { start, end };
});

const loadSettings = async (): Promise<void> => {
  try {
    isLoading.value = true;
    errorMessage.value = '';
    const payload = await settingsService.fetchSettings({
      search: query.value.search,
      group: query.value.group || undefined,
      type: query.value.type || undefined,
      channel: query.value.channel || undefined,
      is_active: query.value.is_active,
      is_public: query.value.is_public,
      is_encrypted: query.value.is_encrypted,
      page: query.value.page,
      per_page: query.value.per_page,
    });

    settings.value = payload.settings;
    effective.value = payload.effective;
    groups.value = payload.groups;
    types.value = payload.types;
    totalItems.value = payload.meta?.total ?? payload.settings.length;
    totalPages.value = payload.meta?.last_page ?? 1;
    if (payload.meta?.current_page) {
      query.value.page = payload.meta.current_page;
    }
  } catch (error) {
    errorMessage.value = (error as { message?: string }).message ?? t('common.generic.somethingWentWrong');
  } finally {
    isLoading.value = false;
  }
};

const onSearchChange = (value: string): void => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    query.value.search = value;
    query.value.page = 1;
    void loadSettings();
  }, 250);
};

const onGroupChange = (value: string): void => { query.value.group = value; query.value.page = 1; void loadSettings(); };
const onTypeChange = (value: string): void => { query.value.type = value; query.value.page = 1; void loadSettings(); };
const onChannelChange = (value: ChannelFilter): void => { query.value.channel = value; query.value.page = 1; void loadSettings(); };
const onActiveChange = (value: BoolFilter): void => { query.value.is_active = value; query.value.page = 1; void loadSettings(); };
const onPublicChange = (value: BoolFilter): void => { query.value.is_public = value; query.value.page = 1; void loadSettings(); };
const onEncryptedChange = (value: BoolFilter): void => { query.value.is_encrypted = value; query.value.page = 1; void loadSettings(); };
const onPageChange = (page: number): void => { query.value.page = Math.min(Math.max(page, 1), totalPages.value); void loadSettings(); };
const onPerPageChange = (size: number): void => { query.value.per_page = size; query.value.page = 1; void loadSettings(); };

const scopeLabel = (item: SystemSettingRecord): string => {
  if (item.scope.type === 'user') return `user:${item.scope.user?.name ?? item.scope.user_id}`;
  if (item.scope.type === 'role') return `role:${item.scope.role?.name ?? item.scope.role_id}`;
  if (item.scope.type === 'permission') return `perm:${item.scope.permission?.name ?? item.scope.permission_id}`;
  return 'global';
};

const stringify = (value: unknown): string => {
  if (value === null || value === undefined) return '-';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
};

const openCreateModal = (): void => {
  modal.open({
    component: SettingsCreateModal,
    title: `${t('common.actions.create')} ${t('common.settings')}`,
    subtitle: 'Add typed runtime setting entry.',
    size: 'xl',
    props: {
      onSaved: () => { void loadSettings(); },
    },
  });
};

const handleRowAction = async (action: 'view' | 'edit' | 'delete', settingId: number): Promise<void> => {
  const setting = settings.value.find((item) => item.id === settingId);
  if (!setting) return;

  if (action === 'view') {
    modal.open({
      component: SettingsDetailsModal,
      title: `${t('common.actions.view')} ${t('common.settings')}`,
      subtitle: setting.key,
      size: 'xl',
      props: {
        setting,
      },
    });
    return;
  }

  if (action === 'edit') {
    modal.open({
      component: SettingsEditModal,
      title: `${t('common.actions.edit')} ${t('common.settings')}`,
      subtitle: setting.key,
      size: 'xl',
      props: {
        setting,
        onSaved: () => { void loadSettings(); },
      },
    });
    return;
  }

  const accepted = await confirm.open({
    title: `${t('common.actions.delete')} ${t('common.settings')}`,
    message: `Delete "${setting.key}"? This cannot be undone.`,
    variant: 'danger',
    destructive: true,
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.actions.cancel'),
  });
  if (!accepted) return;
  await settingsService.deleteSetting(setting.id);
  await loadSettings();
};

onMounted(() => {
  void loadSettings();
});

watch(locale, () => {
  void loadSettings();
});
</script>

<style scoped>
.settings-page{display:grid;gap:12px}
.settings-page__header{margin-top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.settings-page__title{margin:0;font-size:18px;color:#f8fafc}
.settings-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.settings-page__header-actions{display:flex;align-items:center;gap:8px}
.settings-page__stat{border-radius:999px;border:1px solid rgba(71,85,105,.6);padding:4px 9px;font-size:11px;color:#cbd5e1}
.settings-page__create-btn{height:32px;border-radius:8px;border:1px solid rgba(59,130,246,.55);background:rgba(59,130,246,.2);color:#bfdbfe;padding:0 11px;font-size:12px;font-weight:600}
.settings-page__table-wrap{margin-top:0;display:grid;gap:10px}
.settings-page__state{padding:14px 0}
.settings-page__retry{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 11px}
.settings-page__footer{display:flex;justify-content:flex-end}
.settings-main-cell{min-width:220px}
.settings-main-cell__name{color:#f8fafc;font-weight:600}
.settings-main-cell__meta{color:#94a3b8;font-size:12px}
.settings-main-cell__desc{color:#94a3b8;font-size:12px;line-height:1.3}
.settings-badge{display:inline-block;border-radius:999px;font-size:11px;padding:2px 8px;border:1px solid rgba(71,85,105,.6);color:#cbd5e1}
.settings-badge--group{background:rgba(59,130,246,.18);color:#bfdbfe;border-color:rgba(59,130,246,.4)}
.settings-badge--scope{background:rgba(34,211,238,.14);color:#67e8f9;border-color:rgba(34,211,238,.38)}
.settings-value{max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.settings-flags{display:flex;flex-wrap:wrap;gap:4px;justify-content:center}
.settings-badge.is-on{background:rgba(16,185,129,.16);color:#6ee7b7;border-color:rgba(16,185,129,.45)}
.settings-badge.is-global{background:rgba(71,85,105,.35)}
.settings-badge.is-user{background:rgba(59,130,246,.22);color:#bfdbfe}
.settings-badge.is-role{background:rgba(245,158,11,.22);color:#fcd34d}
.settings-badge.is-permission{background:rgba(16,185,129,.22);color:#6ee7b7}
@media (max-width:760px){.settings-page__header{flex-direction:column}.settings-page__footer{justify-content:flex-start}}
</style>

