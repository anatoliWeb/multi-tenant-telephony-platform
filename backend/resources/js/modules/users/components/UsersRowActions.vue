<template>
  <BaseDropdown>
    <template #trigger="{ isOpen }">
      <button type="button" class="row-actions-trigger" :class="{ 'is-open': isOpen }">{{ t('common.actions.actions') }}</button>
    </template>

    <template #default="{ close }">
      <div class="row-actions-panel">
        <button type="button" class="row-actions-panel__item" @click="onAction('view', close)">{{ t('common.actions.view') }}</button>
        <button v-if="canEdit" type="button" class="row-actions-panel__item" @click="onAction('edit', close)">{{ t('common.actions.edit') }}</button>
        <button v-if="canDelete" type="button" class="row-actions-panel__item row-actions-panel__item--danger" @click="onAction('delete', close)">
          {{ t('common.actions.delete') }}
        </button>
      </div>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

interface Props {
  canEdit: boolean;
  canDelete: boolean;
}

defineProps<Props>();

const emit = defineEmits<{ action: [action: 'view' | 'edit' | 'delete'] }>();
const { t } = useI18n({ useScope: 'global' });

const onAction = (action: 'view' | 'edit' | 'delete', close: () => void): void => {
  emit('action', action);
  close();
};
</script>

<style scoped>
.row-actions-trigger {
  height: 30px;
  border-radius: 8px;
  border: 1px solid rgba(71, 85, 105, 0.6);
  background: rgba(15, 23, 42, 0.7);
  color: #e2e8f0;
  padding: 0 10px;
  font-size: 12px;
}

.row-actions-trigger.is-open,
.row-actions-trigger:hover {
  border-color: rgba(96, 165, 250, 0.5);
  background: rgba(51, 65, 85, 0.8);
}

.row-actions-panel {
  min-width: 120px;
  display: grid;
  gap: 2px;
}

.row-actions-panel__item {
  width: 100%;
  text-align: left;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #e2e8f0;
  padding: 8px 10px;
  font-size: 12px;
}

.row-actions-panel__item:hover {
  background: rgba(51, 65, 85, 0.72);
}

.row-actions-panel__item--danger {
  color: #fda4af;
}
</style>
