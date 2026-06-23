<template>
  <section class="tokens-page">
    <header class="tokens-page__header c-card">
      <div>
        <h2 class="tokens-page__title">{{ t('common.tokensPage.title') }}</h2>
        <p class="tokens-page__subtitle">{{ t('common.tokensPage.subtitle') }}</p>
      </div>
      <div class="tokens-page__header-actions">
        <span class="tokens-page__stat">{{ t('common.labels.total') }}: {{ filteredTokens.length }}</span>
        <span v-if="isRefreshing" class="tokens-page__stat">{{ t('common.loading') }}...</span>
        <button v-if="can('tokens.create')" type="button" class="tokens-page__create-btn" @click="openCreateTokenPanel">
          {{ t('common.tokensPage.createToken') }}
        </button>
      </div>
    </header>

    <TokensFilters
      :search="query.search"
      :owner="query.owner"
      :owners="availableOwners"
      :status="query.status"
      :recent="query.recent"
      :type="query.type"
      @update:search="onSearchChange"
      @update:owner="onOwnerChange"
      @update:status="onStatusChange"
      @update:recent="onRecentChange"
      @update:type="onTypeChange"
    />

    <section class="c-card tokens-page__table-wrap">
      <div v-if="isLoading" class="tokens-page__state"><BaseLoader :label="t('common.tokensPage.loadingTokens')" /></div>

      <BaseErrorState v-else-if="errorMessage" :title="t('common.tokensPage.failedLoadTokens')" :description="errorMessage">
        <button type="button" class="tokens-page__retry" @click="loadTokens">{{ t('common.actions.retry') }}</button>
      </BaseErrorState>

      <template v-else>
        <BaseTable :columns="tableColumns" :rows="paginatedTokens" row-key="id">
          <template #empty>
            <BaseEmptyState :title="t('common.tokensPage.noTokensFound')" :description="t('common.tokensPage.noTokensHint')" />
          </template>

          <template #cell:name="{ row }">
            <div class="tokens-main-cell">
              <div class="tokens-main-cell__name">{{ row.name }}</div>
              <div class="tokens-main-cell__meta">{{ t('common.tokensPage.idLabel') }}: {{ row.id }}</div>
            </div>
          </template>

          <template #cell:owner="{ row }">
            <div class="tokens-owner-cell">
              <span class="tokens-owner-cell__avatar">{{ initials((row.owner as { name: string }).name) }}</span>
              <span class="tokens-owner-cell__name">{{ (row.owner as { name: string }).name }}</span>
            </div>
          </template>

          <template #cell:scopes="{ row }">
            <div class="tokens-scopes">
              <span v-for="scope in previewScopes(row.scopes as string[])" :key="scope" class="tokens-badge tokens-badge--scope">{{ getScopeLabel(row as TokenListItem, scope) }}</span>
              <span v-if="(row.scopes as string[]).length > 2" class="tokens-badge tokens-badge--muted">+{{ (row.scopes as string[]).length - 2 }} {{ t('common.tokensPage.more') }}</span>
            </div>
          </template>

          <template #cell:last_used_at="{ row }">
            {{ formatLastUsed((row.last_used_at as string | null | undefined) ?? null) }}
          </template>

          <template #cell:created_at="{ row }">
            {{ formatDate((row.created_at as string | null | undefined) ?? null) }}
          </template>

          <template #cell:status="{ row }">
            <span class="tokens-badge" :class="statusClass(row.status as string)">{{ row.status }}</span>
          </template>

          <template #cell:actions="{ row }">
            <TokensRowActions
              :can-edit="can('tokens.edit') || can('tokens.create')"
              :can-delete="can('tokens.delete')"
              @action="(action) => handleRowAction(action, row.id as number, row.name as string)"
            />
          </template>
        </BaseTable>

        <footer class="tokens-page__footer">
          <UsersPagination
            :current-page="query.page"
            :total-pages="totalPages"
            :per-page="query.perPage"
            :total-items="filteredTokens.length"
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

import TokensFilters from '../components/TokensFilters.vue';
import TokenCreateModal from '../components/TokenCreateModal.vue';
import TokenDetailsModal from '../components/TokenDetailsModal.vue';
import TokensRowActions from '../components/TokensRowActions.vue';
import { tokensService } from '../services/tokens.service';
import type { TokenListItem, TokensQuery } from '../types/tokens.types';
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
 * API tokens module.
 *
 * SECURITY UX PRINCIPLE:
 * Token screens should foreground ownership, scope visibility, and revocation
 * pathways. UI hints improve operator confidence, but backend authorization and
 * token lifecycle enforcement remain the true security boundary.
 */
const isLoading = ref(true);
const isRefreshing = ref(false);
const errorMessage = ref('');
const tokens = ref<TokenListItem[]>([]);
const currentUserPermissions = ref<string[]>([]);
const modal = useModal();
const confirm = useConfirm();
const optimistic = useOptimisticAction();
const toast = useToast();
const { t, locale } = useI18n({ useScope: 'global' });
const TOKENS_CACHE_KEY = 'tokens.list';
const TOKENS_META_CACHE_KEY = 'tokens.meta';

const query = ref<TokensQuery>({
  search: '',
  owner: 'all',
  status: 'all',
  recent: 'all',
  type: 'all',
  page: 1,
  perPage: 10,
});

let searchDebounce: ReturnType<typeof setTimeout> | undefined;

const tableColumns = computed<BaseTableColumn[]>(() => [
  { key: 'name', label: t('common.tokensTable.tokenName') },
  { key: 'owner', label: t('common.tokensTable.owner'), width: '180px' },
  { key: 'scopes', label: t('common.tokensTable.scopes') },
  { key: 'last_used_at', label: t('common.tokensTable.lastUsed'), width: '120px' },
  { key: 'created_at', label: t('common.tokensTable.createdDate'), width: '130px' },
  { key: 'status', label: t('common.tokensTable.status'), width: '110px', align: 'center' },
  { key: 'actions', label: t('common.tokensTable.actions'), width: '110px', align: 'right' },
]);

const availableOwners = computed(() => [...new Set(tokens.value.map((token) => token.owner.name))].sort((a, b) => a.localeCompare(b)));

const filteredTokens = computed(() => {
  const search = query.value.search.trim().toLowerCase();

  return tokens.value.filter((token) => {
    const searchMatch =
      search.length === 0 ||
      token.name.toLowerCase().includes(search) ||
      token.owner.name.toLowerCase().includes(search);

    const ownerMatch = query.value.owner === 'all' || token.owner.name === query.value.owner;
    const statusMatch = query.value.status === 'all' || token.status === query.value.status;
    const typeMatch = query.value.type === 'all' || token.type === query.value.type;

    const recentMatch =
      query.value.recent === 'all' ||
      (query.value.recent === 'recent' ? isRecentlyUsed(token) : !isRecentlyUsed(token));

    return searchMatch && ownerMatch && statusMatch && typeMatch && recentMatch;
  });
});

const totalPages = computed(() => Math.max(Math.ceil(filteredTokens.value.length / query.value.perPage), 1));

const paginatedTokens = computed(() => {
  const start = (query.value.page - 1) * query.value.perPage;
  return filteredTokens.value.slice(start, start + query.value.perPage);
});

const visibleRange = computed(() => {
  const total = filteredTokens.value.length;
  if (total === 0) return { start: 0, end: 0 };
  const start = (query.value.page - 1) * query.value.perPage + 1;
  const end = Math.min(query.value.page * query.value.perPage, total);
  return { start, end };
});

const can = (permission: string): boolean => currentUserPermissions.value.includes(permission);

const initials = (name: string): string =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

const previewScopes = (scopes: string[]): string[] => scopes.slice(0, 2);
const getScopeLabel = (token: TokenListItem, scope: string): string => token.scope_labels?.[scope] ?? scope;

const statusClass = (status: string): string => {
  if (status === 'active') return 'tokens-badge--active';
  if (status === 'revoked') return 'tokens-badge--revoked';
  return 'tokens-badge--expired';
};

const isRecentlyUsed = (token: TokenListItem): boolean => {
  const sourceDate = token.last_used_at ?? token.created_at;
  if (!sourceDate) return false;

  const parsed = new Date(sourceDate);
  if (Number.isNaN(parsed.getTime())) return false;

  const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
  return parsed.getTime() >= sevenDaysAgo;
};

const formatDate = (value: string | null): string => {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat(locale.value, { month: 'short', day: '2-digit', year: 'numeric' }).format(parsed);
};

const formatLastUsed = (value: string | null): string => {
  if (!value) return t('common.tokensPage.never');
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return t('common.tokensPage.unknown');
  return new Intl.DateTimeFormat(locale.value, { month: 'short', day: '2-digit' }).format(parsed);
};

const onSearchChange = (value: string): void => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    query.value.search = value;
    query.value.page = 1;
  }, 250);
};

const onOwnerChange = (value: string): void => {
  query.value.owner = value;
  query.value.page = 1;
};

const onStatusChange = (value: 'all' | 'active' | 'revoked' | 'expired'): void => {
  query.value.status = value;
  query.value.page = 1;
};

const onRecentChange = (value: 'all' | 'recent' | 'stale'): void => {
  query.value.recent = value;
  query.value.page = 1;
};

const onTypeChange = (value: 'all' | 'system' | 'user'): void => {
  query.value.type = value;
  query.value.page = 1;
};

const onPageChange = (page: number): void => {
  query.value.page = Math.min(Math.max(page, 1), totalPages.value);
};

const onPerPageChange = (size: number): void => {
  query.value.perPage = size;
  query.value.page = 1;
};

const openCreateTokenPanel = (): void => {
  modal.open({
    component: TokenCreateModal,
    title: t('common.tokensPage.createTokenTitle'),
    subtitle: t('common.tokensPage.createTokenSubtitle'),
    size: 'lg',
    props: {
      onCreated: (item: TokenListItem) => {
        tokens.value = [item, ...tokens.value];
        syncTokensCache();
        cacheStore.invalidatePrefix('dashboard.');
      },
    },
  });
};

const handleRowAction = async (action: 'view' | 'regenerate' | 'revoke' | 'delete', tokenId: number, tokenName: string): Promise<void> => {
  const token = tokens.value.find((item) => item.id === tokenId);
  if (!token) return;

  if (action === 'view') {
    modal.open({
      component: TokenDetailsModal,
      title: t('common.tokensPage.tokenDetails'),
      subtitle: tokenName,
      size: 'md',
      props: { token },
    });
    return;
  }

  if (action === 'regenerate' || action === 'revoke') {
    const updated: TokenListItem = {
      ...token,
      status: action === 'revoke' ? 'revoked' : 'active',
      created_at: action === 'regenerate' ? new Date().toISOString() : token.created_at,
    };
    tokens.value = tokens.value.map((item) => (item.id === token.id ? updated : item));
    syncTokensCache();
    toast.success({
      title: action === 'regenerate' ? t('common.tokensPage.tokenRegenerated') : t('common.tokensPage.tokenRevoked'),
      message: action === 'regenerate'
        ? t('common.tokensPage.tokenRegeneratedMessage', { name: tokenName })
        : t('common.tokensPage.tokenRevokedMessage', { name: tokenName }),
    });
    return;
  }

  const accepted = await confirm.open({
    title: t('common.tokensPage.deleteTokenTitle'),
    message: t('common.tokensPage.deleteTokenMessage', { name: tokenName }),
    confirmLabel: t('common.actions.delete'),
    cancelLabel: t('common.actions.cancel'),
    variant: 'danger',
    destructive: true,
  });

  if (!accepted) return;

  const snapshot = [...tokens.value];
  await optimistic.run({
    key: `token-delete-${token.id}`,
    apply: () => {
      tokens.value = tokens.value.filter((item) => item.id !== token.id);
    },
    action: async () => {
      await new Promise((resolve) => setTimeout(resolve, 220));
      return true;
    },
    rollback: () => {
      tokens.value = snapshot;
    },
    onSuccess: () => toast.success({ title: t('common.tokensPage.tokenDeleted'), message: t('common.tokensPage.tokenDeletedMessage', { name: tokenName }) }),
    onError: () => toast.error({ title: t('common.tokensPage.deleteFailed'), message: t('common.tokensPage.deleteRollback') }),
  });
  syncTokensCache();
  cacheStore.invalidatePrefix('dashboard.');
};

const loadTokens = async (): Promise<void> => {
  try {
    const hasCache = cacheStore.has(TOKENS_CACHE_KEY) && cacheStore.has(TOKENS_META_CACHE_KEY);
    if (!hasCache) {
      isLoading.value = true;
    }
    isRefreshing.value = false;
    errorMessage.value = '';

    const [tokensResult, metaResult] = await Promise.all([
      useCachedRequest({
        key: TOKENS_CACHE_KEY,
        ttl: 90_000,
        request: () => tokensService.fetchTokens(),
        onBackgroundUpdate: (freshData) => {
          tokens.value = freshData;
        },
      }),
      useCachedRequest({
        key: TOKENS_META_CACHE_KEY,
        ttl: 90_000,
        request: () => tokensService.fetchTokensMeta(),
        onBackgroundUpdate: (freshData) => {
          currentUserPermissions.value = freshData.current_user_permissions;
        },
      }),
    ]);

    tokens.value = tokensResult.data;
    currentUserPermissions.value = metaResult.data.current_user_permissions;
    isRefreshing.value = tokensResult.revalidating || metaResult.revalidating;
  } catch (error) {
    errorMessage.value = (error as { message?: string })?.message ?? 'Unable to fetch tokens list.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadTokens();
});

const syncTokensCache = (): void => {
  cacheStore.set(TOKENS_CACHE_KEY, [...tokens.value]);
};
</script>

<style scoped>
.tokens-page{display:grid;gap:12px}
.tokens-page__header{margin-top:0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.tokens-page__title{margin:0;font-size:18px;color:#f8fafc}
.tokens-page__subtitle{margin:6px 0 0;color:#94a3b8;font-size:13px}
.tokens-page__header-actions{display:flex;align-items:center;gap:8px}
.tokens-page__stat{border-radius:999px;border:1px solid rgba(71,85,105,.6);padding:4px 9px;font-size:11px;color:#cbd5e1}
.tokens-page__create-btn{height:32px;border-radius:8px;border:1px solid rgba(245,158,11,.55);background:rgba(245,158,11,.18);color:#fde68a;padding:0 11px;font-size:12px;font-weight:600}
.tokens-page__create-btn:hover{background:rgba(245,158,11,.24)}
.tokens-page__table-wrap{margin-top:0;display:grid;gap:10px}
.tokens-page__state{padding:14px 0}
.tokens-page__retry{height:32px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 11px}
.tokens-main-cell{min-width:180px}
.tokens-main-cell__name{color:#f8fafc;font-weight:600}
.tokens-main-cell__meta{color:#94a3b8;font-size:11px}
.tokens-owner-cell{display:inline-flex;align-items:center;gap:8px}
.tokens-owner-cell__avatar{width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:rgba(59,130,246,.2);color:#bfdbfe;font-size:10px;font-weight:700}
.tokens-owner-cell__name{color:#e2e8f0;font-size:12px}
.tokens-scopes{display:flex;flex-wrap:wrap;gap:6px}
.tokens-badge{border-radius:999px;font-size:11px;padding:2px 8px;border:1px solid rgba(71,85,105,.6)}
.tokens-badge--scope{background:rgba(34,211,238,.14);color:#67e8f9;border-color:rgba(34,211,238,.38)}
.tokens-badge--muted{color:#94a3b8}
.tokens-badge--active{background:rgba(16,185,129,.16);color:#6ee7b7;border-color:rgba(16,185,129,.45)}
.tokens-badge--revoked{background:rgba(239,68,68,.16);color:#fca5a5;border-color:rgba(239,68,68,.45)}
.tokens-badge--expired{background:rgba(245,158,11,.16);color:#fcd34d;border-color:rgba(245,158,11,.45)}
.tokens-page__footer{display:flex;justify-content:flex-end}
@media (max-width:760px){.tokens-page__header{flex-direction:column}.tokens-page__header-actions{width:100%;justify-content:space-between}.tokens-page__footer{justify-content:flex-start}}
</style>
