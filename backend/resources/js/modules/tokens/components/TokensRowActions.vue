<template>
  <BaseDropdown>
    <template #trigger="{ isOpen }">
      <button type="button" class="token-actions-trigger" :class="{ 'is-open': isOpen }">{{ t('common.actions.actions') }}</button>
    </template>

    <template #default="{ close }">
      <div class="token-actions-panel">
        <button type="button" class="token-actions-panel__item" @click="onAction('view', close)">{{ t('common.actions.view') }}</button>
        <button v-if="canEdit" type="button" class="token-actions-panel__item" @click="onAction('regenerate', close)">{{ t('common.actions.regenerate') }}</button>
        <button v-if="canEdit" type="button" class="token-actions-panel__item" @click="onAction('revoke', close)">{{ t('common.actions.revoke') }}</button>
        <button v-if="canDelete" type="button" class="token-actions-panel__item token-actions-panel__item--danger" @click="onAction('delete', close)">{{ t('common.actions.delete') }}</button>
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
const emit = defineEmits<{ action: [action: 'view' | 'regenerate' | 'revoke' | 'delete'] }>();
const { t } = useI18n({ useScope: 'global' });

const onAction = (action: 'view' | 'regenerate' | 'revoke' | 'delete', close: () => void): void => {
  emit('action', action);
  close();
};
</script>

<style scoped>
.token-actions-trigger{height:30px;border-radius:8px;border:1px solid rgba(71,85,105,.6);background:rgba(15,23,42,.7);color:#e2e8f0;padding:0 10px;font-size:12px}
.token-actions-trigger.is-open,.token-actions-trigger:hover{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.token-actions-panel{min-width:140px;display:grid;gap:2px}
.token-actions-panel__item{width:100%;text-align:left;border:0;border-radius:7px;background:transparent;color:#e2e8f0;padding:8px 10px;font-size:12px}
.token-actions-panel__item:hover{background:rgba(51,65,85,.72)}
.token-actions-panel__item--danger{color:#fda4af}
</style>

