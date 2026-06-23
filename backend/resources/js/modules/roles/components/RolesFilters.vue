<template>
  <section class="roles-filters c-card">
    <div class="roles-filters__search">
      <label for="roles-search" class="roles-filters__label">{{ t('common.labels.search') }}</label>
      <div class="roles-filters__search-box">
        <span class="roles-filters__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" /></svg>
        </span>
        <input
          id="roles-search"
          :value="search"
          type="text"
          class="roles-filters__input"
          :placeholder="t('common.rolesPage.searchPlaceholder')"
          @input="onSearchInput"
        />
      </div>
    </div>

    <div class="roles-filters__group">
      <label class="roles-filters__label">{{ t('common.rolesTable.type') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="roles-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ typeLabel }}</span>
            <span class="roles-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in typeOptions"
            :key="item.value"
            type="button"
            class="roles-filters__option"
            :class="{ 'is-active': item.value === type }"
            @click="selectType(item.value, close)"
          >
            {{ item.label }}
          </button>
        </template>
      </BaseDropdown>
    </div>

    <div class="roles-filters__group">
      <label class="roles-filters__label">{{ t('common.labels.status') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="roles-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ statusLabel }}</span>
            <span class="roles-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in statusOptions"
            :key="item.value"
            type="button"
            class="roles-filters__option"
            :class="{ 'is-active': item.value === status }"
            @click="selectStatus(item.value, close)"
          >
            {{ item.label }}
          </button>
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
  type: 'all' | 'system' | 'custom';
  status: 'all' | 'active' | 'inactive';
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });

const emit = defineEmits<{
  'update:search': [value: string];
  'update:type': [value: 'all' | 'system' | 'custom'];
  'update:status': [value: 'all' | 'active' | 'inactive'];
}>();

const typeOptions = [
  { value: 'all', label: t('common.rolesPage.allRoles') },
  { value: 'system', label: t('common.rolesPage.systemRoles') },
  { value: 'custom', label: t('common.rolesPage.customRoles') },
] as const;

const statusOptions = [
  { value: 'all', label: t('common.labels.allStatuses') },
  { value: 'active', label: t('common.labels.active') },
  { value: 'inactive', label: t('common.labels.inactive') },
] as const;

const typeLabel = computed(() => typeOptions.find((item) => item.value === props.type)?.label ?? t('common.rolesPage.allRoles'));
const statusLabel = computed(() => statusOptions.find((item) => item.value === props.status)?.label ?? t('common.labels.allStatuses'));

const onSearchInput = (event: Event): void => {
  emit('update:search', (event.target as HTMLInputElement).value);
};

const selectType = (value: 'all' | 'system' | 'custom', close: () => void): void => {
  emit('update:type', value);
  close();
};

const selectStatus = (value: 'all' | 'active' | 'inactive', close: () => void): void => {
  emit('update:status', value);
  close();
};
</script>

<style scoped>
.roles-filters {
  margin-top: 0;
  display: grid;
  grid-template-columns: minmax(240px, 1fr) 170px 150px;
  gap: 10px;
  align-items: end;
}
.roles-filters__label { display:block; margin-bottom:5px; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; font-weight:700; }
.roles-filters__search-box { height:36px; border-radius:9px; border:1px solid rgba(71,85,105,.6); background:rgba(15,23,42,.7); display:flex; align-items:center; gap:8px; padding:0 10px; transition:border-color .2s ease, box-shadow .2s ease; }
.roles-filters__search-box:focus-within { border-color:rgba(96,165,250,.6); box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.roles-filters__search-icon { display:inline-flex; color:#64748b; }
.roles-filters__search-icon svg { width:14px; height:14px; fill:currentColor; }
.roles-filters__input { width:100%; border:0; background:transparent; color:#e2e8f0; font-size:13px; outline:none; }
.roles-filters__input::placeholder { color:#64748b; }
.roles-filters__trigger { width:100%; height:36px; border-radius:8px; border:1px solid rgba(71,85,105,.6); background:rgba(15,23,42,.7); color:#e2e8f0; padding:0 10px; font-size:12px; display:inline-flex; align-items:center; justify-content:space-between; gap:8px; }
.roles-filters__trigger:hover,.roles-filters__trigger.is-open { border-color:rgba(96,165,250,.5); background:rgba(51,65,85,.8); }
.roles-filters__trigger-caret { color:#94a3b8; font-size:11px; }
.roles-filters__option { width:100%; text-align:left; border:0; border-radius:7px; background:transparent; color:#e2e8f0; padding:8px 10px; font-size:12px; }
.roles-filters__option:hover { background:rgba(51,65,85,.75); }
.roles-filters__option.is-active { background:rgba(51,65,85,.95); color:#fff; font-weight:700; }
@media (max-width:980px){ .roles-filters{ grid-template-columns:1fr; } }
</style>
