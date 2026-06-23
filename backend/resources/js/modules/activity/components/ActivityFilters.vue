<template>
  <section class="activity-filters c-card">
    <div class="activity-filters__search">
      <label for="activity-search" class="activity-filters__label">Search</label>
      <div class="activity-filters__search-box">
        <span class="activity-filters__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" /></svg>
        </span>
        <input id="activity-search" :value="search" type="text" class="activity-filters__input" placeholder="Search user, action, entity, module" @input="onSearchInput" />
      </div>
    </div>

    <div class="activity-filters__group">
      <label class="activity-filters__label">Module</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }"><button type="button" class="activity-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ moduleLabel }}</span><span class="activity-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button></template>
        <template #default="{ close }"><button v-for="item in moduleOptions" :key="item.value" type="button" class="activity-filters__option" :class="{ 'is-active': item.value === module }" @click="selectModule(item.value, close)">{{ item.label }}</button></template>
      </BaseDropdown>
    </div>

    <div class="activity-filters__group">
      <label class="activity-filters__label">Action</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }"><button type="button" class="activity-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ actionTypeLabel }}</span><span class="activity-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button></template>
        <template #default="{ close }"><button v-for="item in actionTypeOptions" :key="item.value" type="button" class="activity-filters__option" :class="{ 'is-active': item.value === actionType }" @click="selectActionType(item.value, close)">{{ item.label }}</button></template>
      </BaseDropdown>
    </div>

    <div class="activity-filters__group">
      <label class="activity-filters__label">Status</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }"><button type="button" class="activity-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ statusLabel }}</span><span class="activity-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button></template>
        <template #default="{ close }"><button v-for="item in statusOptions" :key="item.value" type="button" class="activity-filters__option" :class="{ 'is-active': item.value === status }" @click="selectStatus(item.value, close)">{{ item.label }}</button></template>
      </BaseDropdown>
    </div>

    <div class="activity-filters__group">
      <label class="activity-filters__label">User</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }"><button type="button" class="activity-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ userLabel }}</span><span class="activity-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button></template>
        <template #default="{ close }"><button v-for="item in userOptions" :key="item.value" type="button" class="activity-filters__option" :class="{ 'is-active': item.value === user }" @click="selectUser(item.value, close)">{{ item.label }}</button></template>
      </BaseDropdown>
    </div>

    <div class="activity-filters__group">
      <label class="activity-filters__label">Date range</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }"><button type="button" class="activity-filters__trigger" :class="{ 'is-open': isOpen }"><span>{{ dateRangeLabel }}</span><span class="activity-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span></button></template>
        <template #default="{ close }"><button v-for="item in dateRangeOptions" :key="item.value" type="button" class="activity-filters__option" :class="{ 'is-active': item.value === dateRange }" @click="selectDateRange(item.value, close)">{{ item.label }}</button></template>
      </BaseDropdown>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

interface Props {
  search: string;
  module: string;
  modules: string[];
  actionType: string;
  actionTypes: string[];
  status: 'all' | 'success' | 'warning' | 'error';
  user: string;
  users: Array<{ value: string; label: string }>;
  dateRange: 'all' | 'today' | '7d' | '30d';
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'update:search': [value: string];
  'update:module': [value: string];
  'update:action-type': [value: string];
  'update:status': [value: 'all' | 'success' | 'warning' | 'error'];
  'update:user': [value: string];
  'update:date-range': [value: 'all' | 'today' | '7d' | '30d'];
}>();

const moduleOptions = computed(() => [{ value: 'all', label: 'All modules' }, ...props.modules.map((item) => ({ value: item, label: item }))]);
const actionTypeOptions = computed(() => [{ value: 'all', label: 'All actions' }, ...props.actionTypes.map((item) => ({ value: item, label: item }))]);
const statusOptions = [
  { value: 'all', label: 'All statuses' },
  { value: 'success', label: 'Success' },
  { value: 'warning', label: 'Warning' },
  { value: 'error', label: 'Error' },
] as const;
const userOptions = computed(() => [{ value: 'all', label: 'All users' }, ...props.users]);
const dateRangeOptions = [
  { value: 'all', label: 'All time' },
  { value: 'today', label: 'Today' },
  { value: '7d', label: 'Last 7 days' },
  { value: '30d', label: 'Last 30 days' },
] as const;

const moduleLabel = computed(() => moduleOptions.value.find((item) => item.value === props.module)?.label ?? 'All modules');
const actionTypeLabel = computed(() => actionTypeOptions.value.find((item) => item.value === props.actionType)?.label ?? 'All actions');
const statusLabel = computed(() => statusOptions.find((item) => item.value === props.status)?.label ?? 'All statuses');
const userLabel = computed(() => userOptions.value.find((item) => item.value === props.user)?.label ?? 'All users');
const dateRangeLabel = computed(() => dateRangeOptions.find((item) => item.value === props.dateRange)?.label ?? 'All time');

const onSearchInput = (event: Event): void => emit('update:search', (event.target as HTMLInputElement).value);
const selectModule = (value: string, close: () => void): void => { emit('update:module', value); close(); };
const selectActionType = (value: string, close: () => void): void => { emit('update:action-type', value); close(); };
const selectStatus = (value: 'all' | 'success' | 'warning' | 'error', close: () => void): void => { emit('update:status', value); close(); };
const selectUser = (value: string, close: () => void): void => { emit('update:user', value); close(); };
const selectDateRange = (value: 'all' | 'today' | '7d' | '30d', close: () => void): void => { emit('update:date-range', value); close(); };
</script>

<style scoped>
.activity-filters{margin-top:0;display:grid;grid-template-columns:minmax(260px,1fr) 140px 160px 130px 140px 150px;gap:10px;align-items:end}
.activity-filters__label{display:block;margin-bottom:5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}
.activity-filters__search-box{height:36px;border-radius:9px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);display:flex;align-items:center;gap:8px;padding:0 10px;transition:border-color .2s ease,box-shadow .2s ease}
.activity-filters__search-box:focus-within{border-color:rgba(96,165,250,.6);box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.activity-filters__search-icon{display:inline-flex;color:#64748b}.activity-filters__search-icon svg{width:14px;height:14px;fill:currentColor}
.activity-filters__input{width:100%;border:0;background:transparent;color:#e2e8f0;font-size:13px;outline:none}.activity-filters__input::placeholder{color:#64748b}
.activity-filters__trigger{width:100%;height:36px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;justify-content:space-between;gap:8px}
.activity-filters__trigger:hover,.activity-filters__trigger.is-open{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.activity-filters__trigger-caret{color:#94a3b8;font-size:11px}
.activity-filters__option{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.activity-filters__option:hover{background:rgba(51,65,85,.75)}
.activity-filters__option.is-active{background:rgba(51,65,85,.95);color:#fff;font-weight:700}
@media (max-width:1280px){.activity-filters{grid-template-columns:1fr}}
</style>
