<template>
  <label class="topbar-search">
    <span class="topbar-search__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" />
      </svg>
    </span>
    <input
      class="topbar-search__input"
      type="text"
      :placeholder="placeholder"
    />
  </label>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
/**
 * Reusable topbar search field.
 *
 * WHY CENTRALIZE:
 * Keeping search visuals and spacing in one component lets us reuse the same
 * shell-level UX in multiple modules (dashboard, users, tokens) without style
 * drift, while preserving migration compatibility with the existing SCSS theme.
 */
interface Props {
  placeholder?: string;
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });
const placeholder = computed(() => props.placeholder ?? t('common.searchPlaceholder'));
</script>

<style scoped>
.topbar-search {
  height: 38px;
  display: flex;
  align-items: center;
  gap: 8px;
  border-radius: 10px;
  border: 1px solid rgba(148, 163, 184, 0.3);
  background: rgba(15, 23, 42, 0.65);
  padding: 0 12px;
}

.topbar-search__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
}

.topbar-search__icon svg {
  width: 15px;
  height: 15px;
  fill: currentColor;
}

.topbar-search__input {
  width: 100%;
  border: 0;
  background: transparent;
  color: #f1f5f9;
  font-size: 14px;
  outline: none;
}

.topbar-search__input::placeholder {
  color: #64748b;
}
</style>
