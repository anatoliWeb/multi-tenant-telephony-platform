<template>
  <BaseDropdown align="right">
    <template #trigger>
      <button type="button" class="actions-trigger">{{ t('common.actions.actions') }}</button>
    </template>

    <template #default="{ close }">
      <button type="button" class="action-btn" @click="emitAction('edit', close)">{{ t('common.actions.edit') }}</button>
      <button type="button" class="action-btn action-btn--danger" @click="emitAction('delete', close)">{{ t('common.actions.delete') }}</button>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

const emit = defineEmits<{
  action: [action: 'edit' | 'delete'];
}>();

const { t } = useI18n({ useScope: 'global' });

const emitAction = (action: 'edit' | 'delete', close: () => void): void => {
  emit('action', action);
  close();
};
</script>

<style scoped>
.actions-trigger{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.75);color:#e2e8f0;padding:0 10px;font-size:12px}
.actions-trigger:hover{border-color:rgba(96,165,250,.5)}
.action-btn{width:100%;text-align:left;border:0;background:transparent;color:#e2e8f0;border-radius:8px;padding:8px 10px;font-size:12px}
.action-btn:hover{background:rgba(51,65,85,.75)}
.action-btn--danger{color:#fca5a5}
</style>

