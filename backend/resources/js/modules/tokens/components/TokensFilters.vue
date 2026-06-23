<template>
  <section class="tokens-filters c-card">
    <div class="tokens-filters__search">
      <label for="tokens-search" class="tokens-filters__label">{{ t('common.labels.search') }}</label>
      <div class="tokens-filters__search-box">
        <span class="tokens-filters__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" /></svg>
        </span>
        <input id="tokens-search" :value="search" type="text" class="tokens-filters__input" :placeholder="t('common.tokensPage.searchPlaceholder')" @input="onSearchInput" />
      </div>
    </div>

    <div class="tokens-filters__group">
      <label class="tokens-filters__label">{{ t('common.tokensTable.owner') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="tokens-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ ownerLabel }}</span><span class="tokens-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default="{ close }">
          <button v-for="item in ownerOptions" :key="item.value" type="button" class="tokens-filters__option" :class="{ 'is-active': item.value === owner }" @click="selectOwner(item.value, close)">{{ item.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="tokens-filters__group">
      <label class="tokens-filters__label">{{ t('common.labels.status') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="tokens-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ statusLabel }}</span><span class="tokens-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default="{ close }">
          <button v-for="item in statusOptions" :key="item.value" type="button" class="tokens-filters__option" :class="{ 'is-active': item.value === status }" @click="selectStatus(item.value, close)">{{ item.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="tokens-filters__group">
      <label class="tokens-filters__label">{{ t('common.tokensPage.recentUse') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="tokens-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ recentLabel }}</span><span class="tokens-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default="{ close }">
          <button v-for="item in recentOptions" :key="item.value" type="button" class="tokens-filters__option" :class="{ 'is-active': item.value === recent }" @click="selectRecent(item.value, close)">{{ item.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="tokens-filters__group">
      <label class="tokens-filters__label">{{ t('common.tokensTable.type') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="tokens-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ typeLabel }}</span><span class="tokens-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default="{ close }">
          <button v-for="item in typeOptions" :key="item.value" type="button" class="tokens-filters__option" :class="{ 'is-active': item.value === type }" @click="selectType(item.value, close)">{{ item.label }}</button>
        </template>
      </BaseDropdown>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

interface Props {
  search: string;
  owner: string;
  owners: string[];
  status: 'all' | 'active' | 'revoked' | 'expired';
  recent: 'all' | 'recent' | 'stale';
  type: 'all' | 'system' | 'user';
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });

const emit = defineEmits<{
  'update:search': [value: string];
  'update:owner': [value: string];
  'update:status': [value: 'all' | 'active' | 'revoked' | 'expired'];
  'update:recent': [value: 'all' | 'recent' | 'stale'];
  'update:type': [value: 'all' | 'system' | 'user'];
}>();

const ownerOptions = computed(() => [
  { value: 'all', label: t('common.tokensPage.allOwners') },
  ...props.owners.map((item) => ({ value: item, label: item })),
]);

const statusOptions = [
  { value: 'all', label: t('common.labels.allStatuses') },
  { value: 'active', label: t('common.labels.active') },
  { value: 'revoked', label: t('common.tokensPage.revoked') },
  { value: 'expired', label: t('common.tokensPage.expired') },
] as const;

const recentOptions = [
  { value: 'all', label: t('common.tokensPage.allActivity') },
  { value: 'recent', label: t('common.tokensPage.recentlyUsed') },
  { value: 'stale', label: t('common.tokensPage.stale') },
] as const;

const typeOptions = [
  { value: 'all', label: t('common.tokensPage.allTypes') },
  { value: 'system', label: t('common.tokensPage.system') },
  { value: 'user', label: t('common.tokensPage.user') },
] as const;

const ownerLabel = computed(() => ownerOptions.value.find((item) => item.value === props.owner)?.label ?? t('common.tokensPage.allOwners'));
const statusLabel = computed(() => statusOptions.find((item) => item.value === props.status)?.label ?? t('common.labels.allStatuses'));
const recentLabel = computed(() => recentOptions.find((item) => item.value === props.recent)?.label ?? t('common.tokensPage.allActivity'));
const typeLabel = computed(() => typeOptions.find((item) => item.value === props.type)?.label ?? t('common.tokensPage.allTypes'));

const onSearchInput = (event: Event): void => emit('update:search', (event.target as HTMLInputElement).value);
const selectOwner = (value: string, close: () => void): void => { emit('update:owner', value); close(); };
const selectStatus = (value: 'all' | 'active' | 'revoked' | 'expired', close: () => void): void => { emit('update:status', value); close(); };
const selectRecent = (value: 'all' | 'recent' | 'stale', close: () => void): void => { emit('update:recent', value); close(); };
const selectType = (value: 'all' | 'system' | 'user', close: () => void): void => { emit('update:type', value); close(); };
</script>

<style scoped>
.tokens-filters{margin-top:0;display:grid;grid-template-columns:minmax(260px,1fr) 160px 140px 150px 120px;gap:10px;align-items:end}
.tokens-filters__label{display:block;margin-bottom:5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}
.tokens-filters__search-box{height:36px;border-radius:9px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);display:flex;align-items:center;gap:8px;padding:0 10px;transition:border-color .2s ease,box-shadow .2s ease}
.tokens-filters__search-box:focus-within{border-color:rgba(96,165,250,.6);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.tokens-filters__search-icon{display:inline-flex;color:#64748b}.tokens-filters__search-icon svg{width:14px;height:14px;fill:currentColor}
.tokens-filters__input{width:100%;border:0;background:transparent;color:#e2e8f0;font-size:13px;outline:none}.tokens-filters__input::placeholder{color:#64748b}
.tokens-filters__trigger{width:100%;height:36px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;justify-content:space-between;gap:8px}
.tokens-filters__trigger:hover,.tokens-filters__trigger.is-open{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.tokens-filters__trigger-caret{color:#94a3b8;font-size:11px}
.tokens-filters__option{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.tokens-filters__option:hover{background:rgba(51,65,85,.75)}
.tokens-filters__option.is-active{background:rgba(51,65,85,.95);color:#fff;font-weight:700}
@media (max-width:1180px){.tokens-filters{grid-template-columns:1fr}}
</style>
