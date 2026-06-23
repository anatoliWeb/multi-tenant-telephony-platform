<template>
  <BaseDropdown>
    <template #trigger="{ isOpen }">
      <button type="button" class="activity-actions-trigger" :class="{ 'is-open': isOpen }">{{ t('common.actions.actions') }}</button>
    </template>

    <template #default="{ close }">
      <div class="activity-actions-panel">
        <button type="button" class="activity-actions-panel__item" @click="onAction(close)">{{ t('common.actions.viewDetails') }}</button>
      </div>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';

const emit = defineEmits<{ details: [] }>();
const { t } = useI18n({ useScope: 'global' });

const onAction = (close: () => void): void => {
  emit('details');
  close();
};
</script>

<style scoped>
.activity-actions-trigger{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
.activity-actions-trigger.is-open,.activity-actions-trigger:hover{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.activity-actions-panel{min-width:130px;display:grid;gap:2px}
.activity-actions-panel__item{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.activity-actions-panel__item:hover{background:rgba(51,65,85,.72)}
</style>

