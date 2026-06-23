<template>
  <BaseDropdown placement="bottom-end">
    <template #trigger="{ isOpen }">
      <button type="button" class="settings-actions__trigger" :class="{ 'is-open': isOpen }">{{ t('common.actions.actions') }}</button>
    </template>
    <template #default>
      <button type="button" class="settings-actions__option" @click="emit('action', 'view')">{{ t('common.actions.view') }}</button>
      <button type="button" class="settings-actions__option" @click="emit('action', 'edit')">{{ t('common.actions.edit') }}</button>
      <button type="button" class="settings-actions__option is-danger" @click="emit('action', 'delete')">{{ t('common.actions.delete') }}</button>
    </template>
  </BaseDropdown>
</template>

<script setup lang="ts">
import BaseDropdown from '../../../shared/components/ui/BaseDropdown.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n({ useScope: 'global' });

const emit = defineEmits<{
  action: [action: 'view' | 'edit' | 'delete'];
}>();
</script>

<style scoped>
.settings-actions__trigger{height:30px;min-width:30px;border-radius:8px;border:1px solid rgba(71,85,105,.55);background:rgba(15,23,42,.65);color:#cbd5e1;padding:0 8px;font-size:14px}
.settings-actions__trigger:hover,.settings-actions__trigger.is-open{border-color:rgba(96,165,250,.5);background:rgba(51,65,85,.8)}
.settings-actions__option{width:100%;text-align:left;border:0;background:transparent;color:#e2e8f0;padding:8px 10px;border-radius:8px}
.settings-actions__option:hover{background:rgba(51,65,85,.75)}
.settings-actions__option.is-danger{color:#fca5a5}
</style>
