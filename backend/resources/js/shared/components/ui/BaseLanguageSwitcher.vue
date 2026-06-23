<template>
  <BaseDropdown v-if="shouldRender">
    <template #trigger="{ isOpen }">
      <button type="button" class="topbar-dropdown-trigger" :class="{ 'is-open': isOpen }">
        <span>{{ activeLocale.code.toUpperCase() }}</span>
        <span class="topbar-dropdown-trigger__caret">{{ isOpen ? '^' : 'v' }}</span>
      </button>
    </template>

    <template #default="{ close }">
      <button
        v-for="localeItem in locales"
        :key="localeItem.code"
        type="button"
        class="locale-option"
        :class="{ 'locale-option--active': localeItem.code === modelValue }"
        @click="selectLocale(localeItem.code, close)"
      >
        <span>{{ localeItem.label }}</span>
        <span class="locale-option__code">{{ localeItem.code.toUpperCase() }}</span>
      </button>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import type { LocaleCode, LocaleConfigItem } from '../../../shared/i18n/config';
import BaseDropdown from './BaseDropdown.vue';

interface Props {
  modelValue: LocaleCode;
  locales: LocaleConfigItem[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [value: LocaleCode] }>();

const shouldRender = computed(() => props.locales.length > 1);
const activeLocale = computed(() => {
  return props.locales.find((item) => item.code === props.modelValue) ?? props.locales[0];
});

const selectLocale = (locale: LocaleCode, close: () => void): void => {
  emit('update:modelValue', locale);
  close();
};
</script>

<style scoped>
.topbar-dropdown-trigger {
  height: 34px;
  border-radius: 9px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 23, 42, 0.72);
  color: #e2e8f0;
  padding: 0 10px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.topbar-dropdown-trigger:hover,
.topbar-dropdown-trigger.is-open {
  background: rgba(51, 65, 85, 0.82);
  border-color: rgba(96, 165, 250, 0.5);
}

.topbar-dropdown-trigger__caret {
  color: #94a3b8;
  font-size: 11px;
}

.locale-option {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 9px 10px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #e2e8f0;
  text-align: left;
  font-size: 13px;
  transition: background-color 0.2s ease;
}

.locale-option:hover {
  background: rgba(51, 65, 85, 0.72);
}

.locale-option--active {
  background: rgba(51, 65, 85, 0.95);
  color: #ffffff;
  font-weight: 600;
}

.locale-option__code {
  color: #94a3b8;
  font-size: 11px;
}
</style>
