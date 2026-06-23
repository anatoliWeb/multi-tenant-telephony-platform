<template>
  <section class="users-filters c-card">
    <div class="users-filters__search">
      <label for="users-search" class="users-filters__label">{{ t('common.labels.search') }}</label>
      <div class="users-filters__search-box">
        <span class="users-filters__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15.8 14.4h-.7l-.3-.3a6 6 0 1 0-.8.8l.3.3v.7L19 21l2-2-5.2-4.6zm-5.3 0a4.2 4.2 0 1 1 0-8.4 4.2 4.2 0 0 1 0 8.4z" /></svg>
        </span>
        <input
          id="users-search"
          :value="search"
          type="text"
          class="users-filters__input"
          :placeholder="t('common.searchPlaceholder')"
          @input="onSearchInput"
        />
      </div>
    </div>

    <div class="users-filters__group">
      <label class="users-filters__label">{{ t('common.labels.role') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="users-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ roleLabel }}</span>
            <span class="users-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in roleOptions"
            :key="item.value"
            type="button"
            class="users-filters__option"
            :class="{ 'is-active': item.value === role }"
            @click="selectRole(item.value, close)"
          >
            {{ item.label }}
          </button>
        </template>
      </BaseDropdown>
    </div>

    <div class="users-filters__group">
      <label class="users-filters__label">{{ t('common.labels.status') }}</label>
      <BaseDropdown>
        <template #trigger="{ isOpen }">
          <button type="button" class="users-filters__trigger" :class="{ 'is-open': isOpen }">
            <span>{{ statusLabel }}</span>
            <span class="users-filters__trigger-caret">{{ isOpen ? '^' : 'v' }}</span>
          </button>
        </template>

        <template #default="{ close }">
          <button
            v-for="item in statusOptions"
            :key="item.value"
            type="button"
            class="users-filters__option"
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

/**
 * Compact reusable filter-toolbar.
 *
 * WHY:
 * Filter bars should keep the main search field dominant while secondary
 * controls stay compact. Reusing BaseDropdown here guarantees consistent menu
 * behavior and positioning across every admin module.
 */
interface Props {
  search: string;
  role: string;
  status: 'all' | 'active' | 'inactive';
  roles: string[];
}

const props = defineProps<Props>();
const { t } = useI18n({ useScope: 'global' });

const emit = defineEmits<{
  'update:search': [value: string];
  'update:role': [value: string];
  'update:status': [value: 'all' | 'active' | 'inactive'];
}>();

const roleOptions = computed(() => [
  { value: 'all', label: t('common.labels.allRoles') },
  ...props.roles.map((item) => ({ value: item, label: item })),
]);

const statusOptions = [
  { value: 'all', label: t('common.labels.allStatuses') },
  { value: 'active', label: t('common.labels.active') },
  { value: 'inactive', label: t('common.labels.inactive') },
] as const;

const roleLabel = computed(() => roleOptions.value.find((item) => item.value === props.role)?.label ?? t('common.labels.allRoles'));
const statusLabel = computed(() => statusOptions.find((item) => item.value === props.status)?.label ?? t('common.labels.allStatuses'));

const onSearchInput = (event: Event): void => {
  emit('update:search', (event.target as HTMLInputElement).value);
};

const selectRole = (value: string, close: () => void): void => {
  emit('update:role', value);
  close();
};

const selectStatus = (value: 'all' | 'active' | 'inactive', close: () => void): void => {
  emit('update:status', value);
  close();
};
</script>

<style scoped>
.users-filters {
  margin-top: 0;
  display: grid;
  grid-template-columns: minmax(240px, 1fr) 170px 150px;
  gap: 10px;
  align-items: end;
}

.users-filters__label {
  display: block;
  margin-bottom: 5px;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  font-weight: 700;
}

.users-filters__search-box {
  height: 36px;
  border-radius: 9px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  background: rgba(15, 23, 42, 0.7);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.users-filters__search-box:focus-within {
  border-color: rgba(96, 165, 250, 0.6);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.users-filters__search-icon {
  display: inline-flex;
  color: #64748b;
}

.users-filters__search-icon svg {
  width: 14px;
  height: 14px;
  fill: currentColor;
}

.users-filters__input {
  width: 100%;
  border: 0;
  background: transparent;
  color: #e2e8f0;
  font-size: 13px;
  outline: none;
}

.users-filters__input::placeholder {
  color: #64748b;
}

.users-filters__trigger {
  width: 100%;
  height: 36px;
  border-radius: 8px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  background: rgba(15, 23, 42, 0.7);
  color: #e2e8f0;
  padding: 0 10px;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.users-filters__trigger:hover,
.users-filters__trigger.is-open {
  border-color: rgba(96, 165, 250, 0.5);
  background: rgba(51, 65, 85, 0.8);
}

.users-filters__trigger-caret {
  color: #94a3b8;
  font-size: 11px;
}

.users-filters__option {
  width: 100%;
  text-align: left;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #e2e8f0;
  padding: 8px 10px;
  font-size: 12px;
}

.users-filters__option:hover {
  background: rgba(51, 65, 85, 0.75);
}

.users-filters__option.is-active {
  background: rgba(51, 65, 85, 0.95);
  color: #ffffff;
  font-weight: 700;
}

@media (max-width: 980px) {
  .users-filters {
    grid-template-columns: 1fr;
  }
}
</style>
