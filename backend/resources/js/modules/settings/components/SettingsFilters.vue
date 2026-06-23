<template>
  <section class="settings-filters c-card">
    <div class="settings-filters__search">
      <label class="settings-filters__label">Search</label>
      <div class="settings-filters__search-box">
        <input :value="search" class="settings-filters__input" placeholder="Search key, label, group, description" @input="emit('update:search', ($event.target as HTMLInputElement).value)" />
      </div>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Channel</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ channelLabel(channel) }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button v-for="option in channels" :key="option.value" type="button" class="settings-filters__option" :class="{ 'is-active': option.value === channel }" @click="emit('update:channel', option.value)">{{ option.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Group</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ group || 'All groups' }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button type="button" class="settings-filters__option" :class="{ 'is-active': group === '' }" @click="emit('update:group', '')">All groups</button>
          <button v-for="value in groups" :key="value" type="button" class="settings-filters__option" :class="{ 'is-active': value === group }" @click="emit('update:group', value)">{{ value }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Type</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ type || 'All types' }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button type="button" class="settings-filters__option" :class="{ 'is-active': type === '' }" @click="emit('update:type', '')">All types</button>
          <button v-for="value in types" :key="value" type="button" class="settings-filters__option" :class="{ 'is-active': value === type }" @click="emit('update:type', value)">{{ value }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Active</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ boolLabel(isActive) }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button v-for="option in boolOptions" :key="`active-${option.value}`" type="button" class="settings-filters__option" :class="{ 'is-active': option.value === isActive }" @click="emit('update:isActive', option.value)">{{ option.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Public</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ boolLabel(isPublic) }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button v-for="option in boolOptions" :key="`public-${option.value}`" type="button" class="settings-filters__option" :class="{ 'is-active': option.value === isPublic }" @click="emit('update:isPublic', option.value)">{{ option.label }}</button>
        </template>
      </BaseDropdown>
    </div>

    <div class="settings-filters__group">
      <label class="settings-filters__label">Encrypted</label>
      <BaseDropdown placement="bottom-start">
        <template #trigger="{ isOpen }">
          <button type="button" class="settings-filters__trigger" :class="{ 'is-open': isOpen }">{{ boolLabel(isEncrypted) }} <span>{{ isOpen ? '^' : 'v' }}</span></button>
        </template>
        <template #default>
          <button v-for="option in boolOptions" :key="`encrypted-${option.value}`" type="button" class="settings-filters__option" :class="{ 'is-active': option.value === isEncrypted }" @click="emit('update:isEncrypted', option.value)">{{ option.label }}</button>
        </template>
      </BaseDropdown>
    </div>
  </section>
</template>

<script setup lang="ts">
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';
import type { SettingValueType } from '../types/settings.types';

type BoolFilter = 'all' | 'true' | 'false';
type ChannelFilter = '' | 'frontend' | 'backend';

defineProps<{
  search: string;
  group: string;
  groups: string[];
  type: string;
  types: SettingValueType[];
  channel: ChannelFilter;
  isActive: BoolFilter;
  isPublic: BoolFilter;
  isEncrypted: BoolFilter;
}>();

const emit = defineEmits<{
  'update:search': [value: string];
  'update:group': [value: string];
  'update:type': [value: string];
  'update:channel': [value: ChannelFilter];
  'update:isActive': [value: BoolFilter];
  'update:isPublic': [value: BoolFilter];
  'update:isEncrypted': [value: BoolFilter];
}>();

const channels: Array<{ value: ChannelFilter; label: string }> = [
  { value: '', label: 'All channels' },
  { value: 'frontend', label: 'Frontend' },
  { value: 'backend', label: 'Backend' },
];

const boolOptions: Array<{ value: BoolFilter; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'true', label: 'Yes' },
  { value: 'false', label: 'No' },
];

const channelLabel = (value: ChannelFilter): string => channels.find((option) => option.value === value)?.label ?? 'All channels';
const boolLabel = (value: BoolFilter): string => boolOptions.find((option) => option.value === value)?.label ?? 'All';
</script>

<style scoped>
.settings-filters{margin-top:0;display:grid;grid-template-columns:minmax(280px,1fr) repeat(6,minmax(120px,1fr));gap:10px;align-items:end}
.settings-filters__label{display:block;margin-bottom:5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}
.settings-filters__search-box{height:36px;border-radius:9px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);display:flex;align-items:center;padding:0 10px}
.settings-filters__input{width:100%;border:0;background:transparent;color:#e2e8f0;font-size:13px;outline:none}
.settings-filters__input::placeholder{color:#64748b}
.settings-filters__trigger{width:100%;height:36px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;justify-content:space-between;gap:8px}
.settings-filters__trigger:hover,.settings-filters__trigger.is-open{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.settings-filters__option{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.settings-filters__option:hover{background:rgba(51,65,85,.75)}
.settings-filters__option.is-active{background:rgba(51,65,85,.95);color:#fff;font-weight:700}
@media (max-width:1320px){.settings-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){.settings-filters{grid-template-columns:1fr}}
</style>


