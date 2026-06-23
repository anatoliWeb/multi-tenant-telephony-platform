<template>
  <section class="permissions-filters c-card">
    <div class="permissions-filters__search">
      <label for="permissions-search" class="permissions-filters__label">{{ t('common.labels.search') }}</label>
      <div class="permissions-filters__search-box">
        <span class="permissions-filters__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" /></svg>
        </span>
        <input
          id="permissions-search"
          :value="search"
          type="text"
          class="permissions-filters__input"
          :placeholder="t('common.permissionsPage.searchPlaceholder')"
          @input="onSearchInput"
        />
      </div>
    </div>

    <div class="permissions-filters__group">
      <label class="permissions-filters__label">{{ t('common.permissionsTable.module') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="permissions-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ moduleLabel }}</span>
            <span class="permissions-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in moduleOptions"
            :key="item.value"
            type="button"
            class="permissions-filters__option"
            :class="{ 'is-active': item.value === module }"
            @click="selectModule(item.value, close)"
          >
            {{ item.label }}
          </button>
        </template>
      </BaseDropdown>
    </div>

    <div class="permissions-filters__group">
      <label class="permissions-filters__label">{{ t('common.permissionsTable.type') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="permissions-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ typeLabel }}</span>
            <span class="permissions-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in typeOptions"
            :key="item.value"
            type="button"
            class="permissions-filters__option"
            :class="{ 'is-active': item.value === type }"
            @click="selectType(item.value, close)"
          >
            {{ item.label }}
          </button>
        </template>
      </BaseDropdown>
    </div>

    <div class="permissions-filters__group">
      <label class="permissions-filters__label">{{ t('common.permissionsPage.usage') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="permissions-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ usageLabel }}</span>
            <span class="permissions-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in usageOptions"
            :key="item.value"
            type="button"
            class="permissions-filters__option"
            :class="{ 'is-active': item.value === usage }"
            @click="selectUsage(item.value, close)"
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
  module: string;
  modules: string[];
  type: 'all' | 'read' | 'write' | 'manage';
  usage: 'all' | 'used' | 'unused';
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });

const emit = defineEmits<{
  'update:search': [value: string];
  'update:module': [value: string];
  'update:type': [value: 'all' | 'read' | 'write' | 'manage'];
  'update:usage': [value: 'all' | 'used' | 'unused'];
}>();

const moduleOptions = computed(() => [
  { value: 'all', label: t('common.permissionsPage.allModules') },
  ...props.modules.map((item) => ({ value: item, label: item })),
]);

const typeOptions = [
  { value: 'all', label: t('common.permissionsPage.allTypes') },
  { value: 'read', label: t('common.permissionsPage.read') },
  { value: 'write', label: t('common.permissionsPage.write') },
  { value: 'manage', label: t('common.permissionsPage.manage') },
] as const;

const usageOptions = [
  { value: 'all', label: t('common.permissionsPage.allUsage') },
  { value: 'used', label: t('common.permissionsPage.used') },
  { value: 'unused', label: t('common.permissionsPage.unused') },
] as const;

const moduleLabel = computed(() => moduleOptions.value.find((item) => item.value === props.module)?.label ?? t('common.permissionsPage.allModules'));
const typeLabel = computed(() => typeOptions.find((item) => item.value === props.type)?.label ?? t('common.permissionsPage.allTypes'));
const usageLabel = computed(() => usageOptions.find((item) => item.value === props.usage)?.label ?? t('common.permissionsPage.allUsage'));

const onSearchInput = (event: Event): void => {
  emit('update:search', (event.target as HTMLInputElement).value);
};

const selectModule = (value: string, close: () => void): void => {
  emit('update:module', value);
  close();
};

const selectType = (value: 'all' | 'read' | 'write' | 'manage', close: () => void): void => {
  emit('update:type', value);
  close();
};

const selectUsage = (value: 'all' | 'used' | 'unused', close: () => void): void => {
  emit('update:usage', value);
  close();
};
</script>

<style scoped>
.permissions-filters{margin-top:0;display:grid;grid-template-columns:minmax(260px,1fr) 170px 140px 140px;gap:10px;align-items:end}
.permissions-filters__label{display:block;margin-bottom:5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}
.permissions-filters__search-box{height:36px;border-radius:9px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);display:flex;align-items:center;gap:8px;padding:0 10px;transition:border-color .2s ease,box-shadow .2s ease}
.permissions-filters__search-box:focus-within{border-color:rgba(96,165,250,.6);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.permissions-filters__search-icon{display:inline-flex;color:#64748b}
.permissions-filters__search-icon svg{width:14px;height:14px;fill:currentColor}
.permissions-filters__input{width:100%;border:0;background:transparent;color:#e2e8f0;font-size:13px;outline:none}
.permissions-filters__input::placeholder{color:#64748b}
.permissions-filters__trigger{width:100%;height:36px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;justify-content:space-between;gap:8px}
.permissions-filters__trigger:hover,.permissions-filters__trigger.is-open{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.permissions-filters__trigger-caret{color:#94a3b8;font-size:11px}
.permissions-filters__option{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.permissions-filters__option:hover{background:rgba(51,65,85,.75)}
.permissions-filters__option.is-active{background:rgba(51,65,85,.95);color:#fff;font-weight:700}
@media (max-width:1050px){.permissions-filters{grid-template-columns:1fr}}
</style>
